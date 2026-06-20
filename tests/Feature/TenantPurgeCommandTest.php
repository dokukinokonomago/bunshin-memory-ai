<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantMemberInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantPurgeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_purges_eligible_archived_tenant_and_keeps_tombstone_and_safe_audit(): void
    {
        $tenant = $this->eligibleTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->eligibleTenant('別テナント', 'other-tenant');
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'name' => 'Archive Owner',
            'email' => 'purge-owner@example.test',
            'pending_email' => 'pending-owner@example.test',
            'pending_email_requested_at' => now(),
            'secret_unlock_password' => Hash::make('secret-password'),
            'remember_token' => 'remember-owner',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
            'email' => 'purge-admin@example.test',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'other-purge@example.test',
        ]);

        $ownerToken = $owner->createApiToken('purge-owner-token');
        $adminToken = $admin->createApiToken('purge-admin-token');
        $otherToken = $otherUser->createApiToken('other-token');
        $unlockToken = $owner->secretUnlockTokens()->create([
            'token' => hash('sha256', 'unlock-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        DB::table('password_reset_tokens')->insert([
            ['email' => 'purge-owner@example.test', 'token' => hash('sha256', 'owner-reset'), 'created_at' => now()],
            ['email' => 'purge-admin@example.test', 'token' => hash('sha256', 'admin-reset'), 'created_at' => now()],
            ['email' => 'other-purge@example.test', 'token' => hash('sha256', 'other-reset'), 'created_at' => now()],
        ]);

        DB::table('sessions')->insert([
            [
                'id' => 'purge-owner-session',
                'user_id' => $owner->id,
                'ip_address' => '192.0.2.10',
                'user_agent' => 'owner agent',
                'payload' => 'owner payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'purge-admin-session',
                'user_id' => $admin->id,
                'ip_address' => '192.0.2.11',
                'user_agent' => 'admin agent',
                'payload' => 'admin payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'other-session',
                'user_id' => $otherUser->id,
                'ip_address' => '192.0.2.12',
                'user_agent' => 'other agent',
                'payload' => 'other payload',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $rootCategory = $this->category($tenant, $owner, '学校', 'school');
        $childCategory = $this->category($tenant, $owner, '部活', 'club', $rootCategory);
        $privateTag = $this->tag($tenant, 'private', 'private');
        $secretTag = $this->tag($tenant, 'secret', 'secret');
        $otherTag = $this->tag($otherTenant, 'other', 'other');

        $privateMemory = $this->memory($tenant, $owner, $childCategory, [
            'title' => 'private title',
            'body' => 'private body',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $privateMemory->tags()->attach($privateTag);

        $secretMemory = $this->memory($tenant, $owner, $rootCategory, [
            'title' => 'secret title must be deleted',
            'body' => 'secret body must be deleted',
            'visibility' => Memory::VISIBILITY_SECRET,
            'metadata' => ['belief' => 'secret metadata'],
        ]);
        $secretMemory->tags()->attach($secretTag);
        $secretMemory->delete();

        $otherMemory = $this->memory($otherTenant, $otherUser, null, [
            'title' => 'other title',
            'body' => 'other body',
        ]);
        $otherMemory->tags()->attach($otherTag);

        $pendingInvitation = $this->invitation($tenant, $owner, 'pending-purge@example.test');
        $acceptedInvitation = $this->invitation($tenant, $owner, 'accepted-purge@example.test', [
            'accepted_user_id' => $admin->id,
            'accepted_at' => now(),
        ]);
        $otherInvitation = $this->invitation($otherTenant, $otherUser, 'other-invite@example.test');

        $preExistingEvent = SecurityEvent::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'event_type' => SecurityEvent::TYPE_LOGIN,
            'outcome' => SecurityEvent::OUTCOME_FAILURE,
            'subject_email' => 'purge-owner@example.test',
            'ip_address' => '192.0.2.10',
            'user_agent' => 'test agent',
            'metadata' => ['raw_email' => 'purge-owner@example.test', 'secret' => 'raw value'],
        ]);

        $tenantPublicId = $tenant->public_id;
        $archivedAt = $tenant->archived_at?->toAtomString();
        $scheduledDeletionAt = $tenant->scheduled_deletion_at?->toAtomString();

        $this
            ->artisan('bunshin:purge-archived-tenants', ['tenant' => $tenantPublicId])
            ->assertExitCode(0);

        $tenant->refresh();
        $this->assertSame($tenantPublicId, $tenant->public_id);
        $this->assertSame('Purged Tenant', $tenant->name);
        $this->assertStringStartsWith('purged-tenant-'.$tenant->id.'-', $tenant->slug);
        $this->assertNull($tenant->archive_reason);
        $this->assertNotNull($tenant->purged_at);
        $this->assertSame($archivedAt, $tenant->archived_at?->toAtomString());
        $this->assertSame($scheduledDeletionAt, $tenant->scheduled_deletion_at?->toAtomString());

        foreach ([$owner, $admin] as $purgedUser) {
            $purgedUser->refresh();
            $this->assertNull($purgedUser->tenant_id);
            $this->assertSame(User::ROLE_MEMBER, $purgedUser->role);
            $this->assertSame(User::ACCOUNT_STATUS_DISABLED, $purgedUser->account_status);
            $this->assertSame('Purged User', $purgedUser->name);
            $this->assertStringStartsWith('purged-user-'.$purgedUser->id.'-', $purgedUser->email);
            $this->assertStringEndsWith('@purged.local', $purgedUser->email);
            $this->assertNull($purgedUser->pending_email);
            $this->assertNull($purgedUser->pending_email_requested_at);
            $this->assertNull($purgedUser->email_verified_at);
            $this->assertNull($purgedUser->remember_token);
            $this->assertNull($purgedUser->secret_unlock_password);
            $this->assertNotNull($purgedUser->deleted_at);
            $this->assertNotNull($purgedUser->anonymized_at);
            $this->assertFalse(Hash::check('password', (string) $purgedUser->password));
        }

        $this->assertDatabaseMissing('memories', ['id' => $privateMemory->id]);
        $this->assertDatabaseMissing('memories', ['id' => $secretMemory->id]);
        $this->assertDatabaseMissing('memory_tag', ['memory_id' => $privateMemory->id]);
        $this->assertDatabaseMissing('memory_tag', ['memory_id' => $secretMemory->id]);
        $this->assertDatabaseMissing('categories', ['id' => $rootCategory->id]);
        $this->assertDatabaseMissing('categories', ['id' => $childCategory->id]);
        $this->assertDatabaseMissing('tags', ['id' => $privateTag->id]);
        $this->assertDatabaseMissing('tags', ['id' => $secretTag->id]);
        $this->assertDatabaseMissing('tenant_member_invitations', ['id' => $pendingInvitation->id]);
        $this->assertDatabaseMissing('tenant_member_invitations', ['id' => $acceptedInvitation->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $ownerToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->assertDatabaseMissing('secret_unlock_tokens', ['id' => $unlockToken->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'purge-owner@example.test']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'purge-admin@example.test']);
        $this->assertDatabaseMissing('sessions', ['id' => 'purge-owner-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'purge-admin-session']);

        $this->assertDatabaseHas('memories', ['id' => $otherMemory->id]);
        $this->assertDatabaseHas('tags', ['id' => $otherTag->id]);
        $this->assertDatabaseHas('tenant_member_invitations', ['id' => $otherInvitation->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'other-purge@example.test']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);

        $preExistingEvent->refresh();
        $this->assertNull($preExistingEvent->subject_email);
        $this->assertNull($preExistingEvent->ip_address);
        $this->assertNull($preExistingEvent->user_agent);
        $this->assertNull($preExistingEvent->metadata);

        $purgeEvent = SecurityEvent::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_type', SecurityEvent::TYPE_TENANT_PURGE)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertNull($purgeEvent->subject_email);
        $this->assertNull($purgeEvent->ip_address);
        $this->assertNull($purgeEvent->user_agent);
        $this->assertSame(2, $purgeEvent->metadata['memories_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['memory_tag_rows_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['categories_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['tags_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['personal_access_tokens_deleted']);
        $this->assertSame(1, $purgeEvent->metadata['secret_unlock_tokens_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['password_reset_tokens_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['sessions_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['invitations_deleted']);
        $this->assertSame(2, $purgeEvent->metadata['users_anonymized']);
        $this->assertSame(1, $purgeEvent->metadata['security_events_scrubbed']);
        $this->assertStringNotContainsString('purge-owner@example.test', (string) json_encode($purgeEvent->metadata));
        $this->assertStringNotContainsString('secret body must be deleted', (string) json_encode($purgeEvent->metadata));
    }

    public function test_dry_run_and_non_eligible_target_do_not_mutate_data(): void
    {
        $eligible = $this->eligibleTenant('Dry Run Tenant', 'dry-run-tenant');
        $owner = User::factory()->create(['tenant_id' => $eligible->id]);
        $category = $this->category($eligible, $owner, 'Dry', 'dry');
        $memory = $this->memory($eligible, $owner, $category, [
            'body' => 'dry run body',
        ]);

        $this
            ->artisan('bunshin:purge-archived-tenants', [
                'tenant' => $eligible->slug,
                '--dry-run' => true,
            ])
            ->assertExitCode(0);

        $eligible->refresh();
        $this->assertNull($eligible->purged_at);
        $this->assertSame('dry-run-tenant', $eligible->slug);
        $this->assertDatabaseHas('memories', ['id' => $memory->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'tenant_id' => $eligible->id]);
        $this->assertDatabaseMissing('security_events', [
            'event_type' => SecurityEvent::TYPE_TENANT_PURGE,
        ]);

        $future = $this->eligibleTenant('Future Tenant', 'future-tenant', [
            'scheduled_deletion_at' => now()->addDay(),
        ]);

        $this
            ->artisan('bunshin:purge-archived-tenants', ['tenant' => $future->slug])
            ->assertExitCode(0);

        $this->assertNull($future->refresh()->purged_at);
    }

    public function test_limit_processes_small_batches_and_slug_target_can_finish_remaining_tenant(): void
    {
        $first = $this->eligibleTenant('First Tenant', 'first-tenant', [
            'scheduled_deletion_at' => now()->subDays(2),
        ]);
        $second = $this->eligibleTenant('Second Tenant', 'second-tenant', [
            'scheduled_deletion_at' => now()->subDay(),
        ]);

        $this
            ->artisan('bunshin:purge-archived-tenants', ['--limit' => 1])
            ->assertExitCode(0);

        $this->assertNotNull($first->refresh()->purged_at);
        $this->assertNull($second->refresh()->purged_at);

        $this
            ->artisan('bunshin:purge-archived-tenants', ['tenant' => $second->slug])
            ->assertExitCode(0);

        $this->assertNotNull($second->refresh()->purged_at);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function eligibleTenant(string $name, string $slug, array $attributes = []): Tenant
    {
        $archivedAt = now()->subDays(31);

        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'archived_at' => $archivedAt,
            'deletion_requested_at' => $archivedAt,
            'scheduled_deletion_at' => now()->subDay(),
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_CANCELED,
            'subscription_ends_at' => $archivedAt,
            ...$attributes,
        ]);
    }

    private function category(
        Tenant $tenant,
        User $owner,
        string $name,
        string $slug,
        ?Category $parent = null,
    ): Category {
        return Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function tag(Tenant $tenant, string $name, string $normalizedName): Tag
    {
        return Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'normalized_name' => $normalizedName,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function memory(Tenant $tenant, User $owner, ?Category $category, array $attributes = []): Memory
    {
        return Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'category_id' => $category?->id,
            'body' => 'memory body',
            'visibility' => Memory::VISIBILITY_PRIVATE,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function invitation(Tenant $tenant, User $invitedBy, string $email, array $attributes = []): TenantMemberInvitation
    {
        return TenantMemberInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'invited_by_user_id' => $invitedBy->id,
            'email' => $email,
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', $email),
            'expires_at' => now()->addDays(7),
            ...$attributes,
        ]);
    }
}
