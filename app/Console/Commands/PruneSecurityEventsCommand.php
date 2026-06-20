<?php

namespace App\Console\Commands;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

class PruneSecurityEventsCommand extends Command
{
    protected $signature = 'bunshin:prune-security-events
        {tenant? : Tenant public id or slug to target tenant-bound security events}
        {--dry-run : Report candidate counts without deleting rows}
        {--limit=5000 : Maximum security_events rows to delete}';

    protected $description = 'Prune security event audit rows according to retention policy.';

    public function handle(): int
    {
        $retentionDays = $this->retentionDays();
        $limit = $this->limitOption();

        if ($retentionDays === null || $limit === null) {
            return self::FAILURE;
        }

        $target = $this->argumentString('tenant');
        $targetTenant = null;

        if ($target !== null) {
            $targetTenant = $this->targetTenant($target);

            if (! $targetTenant instanceof Tenant) {
                $this->error('Tenant target not found.');

                return self::FAILURE;
            }
        }

        $cutoff = now()->subDays($retentionDays);
        $buckets = $this->bucketQueries($cutoff, $targetTenant);

        if ($this->option('dry-run')) {
            $this->renderDryRun($buckets);

            return self::SUCCESS;
        }

        return $this->prune($buckets, $limit);
    }

    private function retentionDays(): ?int
    {
        $retentionDays = filter_var(
            config('bunshin.security.event_retention_days', 180),
            FILTER_VALIDATE_INT,
        );

        if (! is_int($retentionDays) || $retentionDays < 30 || $retentionDays > 3650) {
            $this->error('BUNSHIN_SECURITY_EVENT_RETENTION_DAYS must be an integer between 30 and 3650.');

            return null;
        }

        return $retentionDays;
    }

    private function limitOption(): ?int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if (! is_int($limit) || $limit < 1 || $limit > 50000) {
            $this->error('The --limit option must be an integer between 1 and 50000.');

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

    private function targetTenant(string $target): ?Tenant
    {
        return Tenant::query()
            ->where('public_id', $target)
            ->orWhere('slug', $target)
            ->first();
    }

    /**
     * @return array<string, array{label: string, query: callable(): Builder<SecurityEvent>}>
     */
    private function bucketQueries(Carbon $cutoff, ?Tenant $targetTenant): array
    {
        $buckets = [];

        if (! $targetTenant instanceof Tenant) {
            $buckets['null_tenant'] = [
                'label' => 'Null-tenant events',
                'query' => fn (): Builder => SecurityEvent::query()
                    ->whereNull('security_events.tenant_id')
                    ->where('security_events.created_at', '<', $cutoff),
            ];
        }

        $buckets['non_purged_tenant'] = [
            'label' => 'Non-purged tenant events',
            'query' => fn (): Builder => SecurityEvent::query()
                ->join('tenants', 'tenants.id', '=', 'security_events.tenant_id')
                ->whereNull('tenants.purged_at')
                ->when(
                    $targetTenant instanceof Tenant,
                    static fn (Builder $query): Builder => $query
                        ->where('security_events.tenant_id', $targetTenant->id),
                )
                ->where('security_events.created_at', '<', $cutoff),
        ];

        $buckets['purged_tenant'] = [
            'label' => 'Purged tenant events',
            'query' => fn (): Builder => SecurityEvent::query()
                ->join('tenants', 'tenants.id', '=', 'security_events.tenant_id')
                ->where('tenants.purged_at', '<', $cutoff)
                ->when(
                    $targetTenant instanceof Tenant,
                    static fn (Builder $query): Builder => $query
                        ->where('security_events.tenant_id', $targetTenant->id),
                ),
        ];

        return $buckets;
    }

    /**
     * @param  array<string, array{label: string, query: callable(): Builder<SecurityEvent>}>  $buckets
     */
    private function renderDryRun(array $buckets): void
    {
        $rows = [];

        foreach ($buckets as $bucket) {
            $query = $bucket['query'];
            $rows[] = [
                $bucket['label'],
                $query()->count(),
            ];
        }

        $this->warn('Dry run: no rows changed.');
        $this->table(['Bucket', 'Candidate rows'], $rows);
    }

    /**
     * @param  array<string, array{label: string, query: callable(): Builder<SecurityEvent>}>  $buckets
     */
    private function prune(array $buckets, int $limit): int
    {
        $remaining = $limit;
        $deletedTotal = 0;
        $rows = [];

        foreach ($buckets as $bucket) {
            if ($remaining <= 0) {
                $rows[] = [$bucket['label'], 0];

                continue;
            }

            try {
                $query = $bucket['query'];
                $deleted = $this->deleteBatch($query(), $remaining);
            } catch (Throwable $exception) {
                $this->error('Failed to prune security events: '.$exception::class);

                return self::FAILURE;
            }

            $deletedTotal += $deleted;
            $remaining -= $deleted;
            $rows[] = [$bucket['label'], $deleted];
        }

        if ($deletedTotal === 0) {
            $this->info('No eligible security events found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Pruned security events. Deleted: %d.', $deletedTotal));
        $this->table(['Bucket', 'Deleted rows'], $rows);

        return self::SUCCESS;
    }

    private function deleteBatch(Builder $query, int $limit): int
    {
        $ids = $query
            ->select('security_events.id')
            ->orderBy('security_events.created_at')
            ->orderBy('security_events.id')
            ->limit($limit)
            ->pluck('security_events.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        return (int) SecurityEvent::query()
            ->whereIn('id', $ids)
            ->delete();
    }
}
