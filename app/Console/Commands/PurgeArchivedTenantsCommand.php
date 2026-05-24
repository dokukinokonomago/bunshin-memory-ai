<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Memory;
use App\Models\PersonalAccessToken;
use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantMemberInvitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class PurgeArchivedTenantsCommand extends Command
{
    protected $signature = 'bunshin:purge-archived-tenants
        {tenant? : Tenant public id or slug to purge}
        {--dry-run : Report eligible tenants and estimated counts without changing data}
        {--limit=50 : Maximum tenants to process}';

    protected $description = 'Permanently purge archived tenants after their retention window.';

    public function handle(): int
    {
        $limit = $this->limitOption();

        if ($limit === null) {
            return self::FAILURE;
        }

        $target = $this->argumentString('tenant');

        if ($target !== null && ! $this->targetExists($target)) {
            $this->error('Tenant target not found.');

            return self::FAILURE;
        }

        $tenants = $this->eligibleTenantQuery($target)
            ->orderBy('scheduled_deletion_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No eligible tenants found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->renderDryRun($tenants);

            return self::SUCCESS;
        }

        $purged = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $summary = $this->purgeTenant($tenant);

                if ($summary === null) {
                    $this->line(sprintf(
                        'Skipped tenant %s: no longer eligible.',
                        $tenant->public_id ?? $tenant->slug,
                    ));

                    continue;
                }

                $purged++;
                $this->info(sprintf(
                    'Purged tenant %s (%s).',
                    $summary['tenant_public_id'],
                    $summary['tombstone_slug'],
                ));
            } catch (Throwable $exception) {
                $failed++;
                $this->logFailure($tenant, $exception);
                $this->error(sprintf(
                    'Failed to purge tenant %s: %s',
                    $tenant->public_id ?? $tenant->slug,
                    $exception::class,
                ));
            }
        }

        $this->line(sprintf('Purge complete. Purged: %d. Failed: %d.', $purged, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function limitOption(): ?int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if (! is_int($limit) || $limit < 1 || $limit > 500) {
            $this->error('The --limit option must be an integer between 1 and 500.');

            return null;
        }

        return $limit;
    }

    private function argumentString(string $name): ?string
    {
        $value = $this->argument($name);
        $value = is_array($value) ? reset($value) : $value;
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function targetExists(string $target): bool
    {
        return Tenant::query()
            ->where('public_id', $target)
            ->orWhere('slug', $target)
            ->exists();
    }

    /**
     * @return Builder<Tenant>
     */
    private function eligibleTenantQuery(?string $target): Builder
    {
        return Tenant::query()
            ->whereNotNull('archived_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->whereNull('purged_at')
            ->when($target !== null, static function (Builder $query) use ($target): void {
                $query->where(static function (Builder $query) use ($target): void {
                    $query
                        ->where('public_id', $target)
                        ->orWhere('slug', $target);
                });
            });
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function renderDryRun(Collection $tenants): void
    {
        $rows = $tenants
            ->map(function (Tenant $tenant): array {
                $counts = $this->countsForTenant($tenant);

                return [
                    $tenant->public_id,
                    $tenant->slug,
                    $tenant->scheduled_deletion_at?->toAtomString(),
                    $counts['memories_deleted'],
                    $counts['categories_deleted'],
                    $counts['tags_deleted'],
                    $counts['invitations_deleted'],
                    $counts['users_anonymized'],
                    $counts['security_events_scrubbed'],
                ];
            })
            ->all();

        $this->warn('Dry run: no rows changed.');
        $this->table([
            'Public ID',
            'Slug',
            'Scheduled deletion',
            'Memories',
            'Categories',
            'Tags',
            'Invitations',
            'Users',
            'Security events',
        ], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function purgeTenant(Tenant $tenant): ?array
    {
        return DB::transaction(function () use ($tenant): ?array {
            /** @var Tenant|null $lockedTenant */
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTenant instanceof Tenant || ! $this->isEligible($lockedTenant)) {
                return null;
            }

            $counts = $this->countsForTenant($lockedTenant);
            $now = now();
            $tombstoneSlug = $this->tombstoneSlug($lockedTenant);
            $userIds = $this->tenantUserIds($lockedTenant);
            $userEmails = $this->tenantUserEmails($lockedTenant);
            $memoryIds = $this->tenantMemoryIds($lockedTenant);

            DB::table('memory_tag')
                ->whereIn('memory_id', $memoryIds)
                ->delete();

            Memory::withTrashed()
                ->where('tenant_id', $lockedTenant->id)
                ->forceDelete();

            Category::query()
                ->where('tenant_id', $lockedTenant->id)
                ->delete();

            Tag::query()
                ->where('tenant_id', $lockedTenant->id)
                ->delete();

            PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();

            SecretUnlockToken::query()
                ->whereIn('user_id', $userIds)
                ->delete();

            DB::table('password_reset_tokens')
                ->whereIn('email', $userEmails)
                ->delete();

            DB::table('sessions')
                ->whereIn('user_id', $userIds)
                ->delete();

            TenantMemberInvitation::query()
                ->where('tenant_id', $lockedTenant->id)
                ->delete();

            SecurityEvent::query()
                ->where('tenant_id', $lockedTenant->id)
                ->update([
                    'subject_email' => null,
                    'ip_address' => null,
                    'user_agent' => null,
                    'metadata' => null,
                ]);

            User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->each(function (User $user) use ($now): void {
                    $user->forceFill([
                        'tenant_id' => null,
                        'role' => User::ROLE_MEMBER,
                        'account_status' => User::ACCOUNT_STATUS_DISABLED,
                        'name' => 'Purged User',
                        'email' => $this->anonymizedUserEmail($user),
                        'pending_email' => null,
                        'pending_email_requested_at' => null,
                        'email_verified_at' => null,
                        'password' => Hash::make(Str::random(48)),
                        'remember_token' => null,
                        'secret_unlock_password' => null,
                        'deleted_at' => $now,
                        'anonymized_at' => $now,
                    ])->save();
                });

            $lockedTenant->forceFill([
                'name' => 'Purged Tenant',
                'slug' => $tombstoneSlug,
                'archive_reason' => null,
                'purged_at' => $now,
            ])->save();

            SecurityEvent::query()->create([
                'tenant_id' => $lockedTenant->id,
                'user_id' => null,
                'event_type' => SecurityEvent::TYPE_TENANT_PURGE,
                'outcome' => SecurityEvent::OUTCOME_SUCCESS,
                'metadata' => $counts,
                'created_at' => $now,
            ]);

            return [
                'tenant_public_id' => (string) $lockedTenant->public_id,
                'tombstone_slug' => $tombstoneSlug,
                ...$counts,
            ];
        });
    }

    private function isEligible(Tenant $tenant): bool
    {
        return $tenant->archived_at !== null
            && $tenant->scheduled_deletion_at !== null
            && $tenant->scheduled_deletion_at->lessThanOrEqualTo(now())
            && $tenant->purged_at === null;
    }

    /**
     * @return array{
     *     memories_deleted: int,
     *     memory_tag_rows_deleted: int,
     *     categories_deleted: int,
     *     tags_deleted: int,
     *     personal_access_tokens_deleted: int,
     *     secret_unlock_tokens_deleted: int,
     *     password_reset_tokens_deleted: int,
     *     sessions_deleted: int,
     *     invitations_deleted: int,
     *     users_anonymized: int,
     *     security_events_scrubbed: int
     * }
     */
    private function countsForTenant(Tenant $tenant): array
    {
        $userIds = $this->tenantUserIds($tenant);
        $userEmails = $this->tenantUserEmails($tenant);
        $memoryIds = $this->tenantMemoryIds($tenant);

        return [
            'memories_deleted' => Memory::withTrashed()->where('tenant_id', $tenant->id)->count(),
            'memory_tag_rows_deleted' => DB::table('memory_tag')->whereIn('memory_id', $memoryIds)->count(),
            'categories_deleted' => Category::query()->where('tenant_id', $tenant->id)->count(),
            'tags_deleted' => Tag::query()->where('tenant_id', $tenant->id)->count(),
            'personal_access_tokens_deleted' => PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->count(),
            'secret_unlock_tokens_deleted' => SecretUnlockToken::query()
                ->whereIn('user_id', $userIds)
                ->count(),
            'password_reset_tokens_deleted' => DB::table('password_reset_tokens')
                ->whereIn('email', $userEmails)
                ->count(),
            'sessions_deleted' => DB::table('sessions')
                ->whereIn('user_id', $userIds)
                ->count(),
            'invitations_deleted' => TenantMemberInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->count(),
            'users_anonymized' => count($userIds),
            'security_events_scrubbed' => SecurityEvent::query()
                ->where('tenant_id', $tenant->id)
                ->count(),
        ];
    }

    /**
     * @return list<int>
     */
    private function tenantUserIds(Tenant $tenant): array
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<string>
     */
    private function tenantUserEmails(Tenant $tenant): array
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->pluck('email')
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function tenantMemoryIds(Tenant $tenant): array
    {
        return Memory::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function tombstoneSlug(Tenant $tenant): string
    {
        return 'purged-tenant-'.$tenant->id.'-'.Str::lower((string) Str::ulid());
    }

    private function anonymizedUserEmail(User $user): string
    {
        return 'purged-user-'.$user->id.'-'.Str::lower((string) Str::ulid()).'@purged.local';
    }

    private function logFailure(Tenant $tenant, Throwable $exception): void
    {
        try {
            SecurityEvent::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => null,
                'event_type' => SecurityEvent::TYPE_TENANT_PURGE,
                'outcome' => SecurityEvent::OUTCOME_FAILURE,
                'metadata' => [
                    'reason' => 'exception',
                    'exception_class' => $exception::class,
                ],
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Keep the command moving even if failure auditing itself fails.
        }
    }
}
