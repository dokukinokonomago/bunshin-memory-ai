<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TenantExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_tenant_operational_data_without_memory_content_or_raw_audit_data(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'owner-export@example.test',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
            'email' => 'admin-export@example.test',
        ]);
        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_MEMBER,
            'email' => 'member-export@example.test',
        ]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $privateCategory = $this->createCategory($tenant, $owner, 'category name must not leak', 'private-category');
        $memberCategory = $this->createCategory($tenant, $member, 'member category name must not leak', 'member-category');
        $this->createCategory($otherTenant, $otherTenantUser, 'other tenant category must not leak', 'other-category');

        $tag = $this->createTag($tenant, 'tag name must not leak', 'tag name must not leak');

        $privateMemory = $this->createMemory($tenant, $owner, [
            'category_id' => $privateCategory->id,
            'period_key' => 'high_school',
            'title' => 'private title must not leak',
            'body' => 'private body must not leak',
            'visibility' => Memory::VISIBILITY_PRIVATE,
            'metadata' => [
                'beliefs' => ['memory metadata must not leak'],
            ],
        ]);
        $privateMemory->tags()->attach($tag);

        $this->createMemory($tenant, $owner, [
            'category_id' => $privateCategory->id,
            'period_key' => 'high_school',
            'title' => 'secret title must not leak',
            'body' => 'secret body must not leak',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);

        $this->createMemory($tenant, $member, [
            'category_id' => $memberCategory->id,
            'period_key' => 'childhood',
            'title' => 'member shared title must not leak',
            'body' => 'member shared body must not leak',
            'visibility' => Memory::VISIBILITY_SHARED,
        ]);

        $deleted = $this->createMemory($tenant, $owner, [
            'title' => 'deleted title must not leak',
            'body' => 'deleted body must not leak',
        ]);
        $deleted->delete();

        $this->createMemory($otherTenant, $otherTenantUser, [
            'title' => 'other tenant title must not leak',
            'body' => 'other tenant body must not leak',
        ]);

        $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'pending-invite@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'pending-token-must-not-leak'),
            'expires_at' => now()->addDays(7),
        ]);
        $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'accepted_user_id' => $member->id,
            'email' => 'accepted-invite@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'accepted-token-must-not-leak'),
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        SecurityEvent::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'event_type' => SecurityEvent::TYPE_LOGIN,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => $owner->email,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/Leak',
            'metadata' => ['raw_metadata_secret' => 'must not leak'],
            'created_at' => now()->subMinutes(20),
        ]);
        SecurityEvent::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'event_type' => SecurityEvent::TYPE_ACCOUNT_STATUS_CHANGE,
            'outcome' => SecurityEvent::OUTCOME_FAILURE,
            'subject_email' => $member->email,
            'ip_address' => '203.0.113.11',
            'user_agent' => 'Mozilla/Leak2',
            'metadata' => ['reason' => 'owner_boundary'],
            'created_at' => now()->subMinutes(10),
        ]);

        $accessToken = $owner->createApiToken('tenant-export');
        $this->clearTenantLifecycleRateLimit($tenant, $owner);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->postJson('/api/v1/tenant/export', [
                'current_password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', 'bunshin-ai')
            ->assertJsonPath('data.tenant.public_id', $tenant->public_id)
            ->assertJsonPath('data.quota.members_count', 3)
            ->assertJsonPath('data.quota.active_memories_count', 3)
            ->assertJsonPath('data.quota.categories_count', 2)
            ->assertJsonCount(3, 'data.members')
            ->assertJsonCount(2, 'data.invitations')
            ->assertJsonCount(3, 'data.memory_inventory')
            ->assertJsonCount(2, 'data.security_event_summary');

        $members = collect($response->json('data.members'))->keyBy('public_id');
        $this->assertSame('owner-export@example.test', $members[$owner->public_id]['email']);
        $this->assertSame(User::ROLE_ADMIN, $members[$admin->public_id]['role']);
        $this->assertSame(User::ROLE_MEMBER, $members[$member->public_id]['role']);

        $inventory = collect($response->json('data.memory_inventory'))
            ->keyBy(fn (array $row): string => implode('|', [
                $row['owner_user_public_id'],
                $row['visibility'],
                $row['category_public_id'] ?? 'none',
                $row['period_key'] ?? 'none',
            ]));

        $this->assertSame(1, $inventory[$owner->public_id.'|'.Memory::VISIBILITY_PRIVATE.'|'.$privateCategory->public_id.'|high_school']['count']);
        $this->assertSame(1, $inventory[$owner->public_id.'|'.Memory::VISIBILITY_SECRET.'|'.$privateCategory->public_id.'|high_school']['count']);
        $this->assertSame(1, $inventory[$member->public_id.'|'.Memory::VISIBILITY_SHARED.'|'.$memberCategory->public_id.'|childhood']['count']);

        $summary = collect($response->json('data.security_event_summary'))
            ->keyBy(fn (array $row): string => $row['event_type'].'|'.$row['outcome']);

        $this->assertSame(1, $summary[SecurityEvent::TYPE_LOGIN.'|'.SecurityEvent::OUTCOME_SUCCESS]['count']);
        $this->assertNotNull($summary[SecurityEvent::TYPE_LOGIN.'|'.SecurityEvent::OUTCOME_SUCCESS]['last_seen_at']);
        $this->assertSame(1, $summary[SecurityEvent::TYPE_ACCOUNT_STATUS_CHANGE.'|'.SecurityEvent::OUTCOME_FAILURE]['count']);

        $content = $response->getContent();
        $this->assertStringNotContainsString('private title must not leak', $content);
        $this->assertStringNotContainsString('private body must not leak', $content);
        $this->assertStringNotContainsString('secret title must not leak', $content);
        $this->assertStringNotContainsString('secret body must not leak', $content);
        $this->assertStringNotContainsString('member shared title must not leak', $content);
        $this->assertStringNotContainsString('member shared body must not leak', $content);
        $this->assertStringNotContainsString('deleted title must not leak', $content);
        $this->assertStringNotContainsString('other tenant title must not leak', $content);
        $this->assertStringNotContainsString('memory metadata must not leak', $content);
        $this->assertStringNotContainsString('tag name must not leak', $content);
        $this->assertStringNotContainsString('category name must not leak', $content);
        $this->assertStringNotContainsString('member category name must not leak', $content);
        $this->assertStringNotContainsString('raw_metadata_secret', $content);
        $this->assertStringNotContainsString('203.0.113.10', $content);
        $this->assertStringNotContainsString('Mozilla/Leak', $content);
        $this->assertStringNotContainsString('pending-token-must-not-leak', $content);
        $this->assertStringNotContainsString('accepted-token-must-not-leak', $content);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $accessToken->accessToken->id,
        ]);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_TENANT_EXPORT_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($owner->id, $event->user_id);
        $this->assertSame('owner-export@example.test', $event->subject_email);
        $this->assertSame(3, $event->metadata['members_count']);
        $this->assertSame(3, $event->metadata['active_memories_count']);
        $this->assertSame(2, $event->metadata['categories_count']);
        $this->assertSame(2, $event->metadata['invitations_count']);
        $this->assertStringNotContainsString('private title must not leak', (string) json_encode($event->metadata));
    }

    public function test_tenant_export_rejects_invalid_password_non_owner_tenant_context_and_validation_errors(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'invalid-owner-export@example.test',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
            'email' => 'non-owner-export@example.test',
        ]);
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan-tenant-export@example.test',
        ]);

        $ownerToken = $owner->createApiToken('tenant-export-owner');
        $adminToken = $admin->createApiToken('tenant-export-admin');
        $orphanToken = $orphanUser->createApiToken('tenant-export-orphan');
        $this->clearTenantLifecycleRateLimit($tenant, $owner);
        $this->clearTenantLifecycleRateLimit($tenant, $admin);
        $this->clearTenantLifecycleRateLimit(null, $orphanUser);

        $this
            ->postJson('/api/v1/tenant/export', [
                'current_password' => 'password',
            ])
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->postJson('/api/v1/tenant/export', [
                'current_password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/export', [
                'current_password' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this
            ->withHeader('Authorization', 'Bearer '.$adminToken->plainTextToken)
            ->postJson('/api/v1/tenant/export', [
                'current_password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $ownerRequiredFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_TENANT_EXPORT_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'owner_required')
            ->sole();

        $this->assertSame($tenant->id, $ownerRequiredFailure->tenant_id);
        $this->assertSame($admin->id, $ownerRequiredFailure->user_id);
        $this->assertSame(User::ROLE_ADMIN, $ownerRequiredFailure->metadata['role']);

        $wrongPasswordResponse = $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/export', [
                'current_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertStringNotContainsString('wrong-password', $wrongPasswordResponse->getContent());

        $passwordFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_TENANT_EXPORT_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'invalid_current_password')
            ->sole();

        $this->assertSame($tenant->id, $passwordFailure->tenant_id);
        $this->assertSame($owner->id, $passwordFailure->user_id);
        $this->assertSame('invalid-owner-export@example.test', $passwordFailure->subject_email);
    }

    public function test_tenant_export_is_rate_limited_per_authenticated_tenant_user(): void
    {
        config(['bunshin.security.rate_limits.tenant_lifecycle.per_minute' => 1]);

        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $token = $owner->createApiToken('tenant-export-rate-limit');
        $this->clearTenantLifecycleRateLimit($tenant, $owner);

        $payload = [
            'current_password' => 'wrong-password',
        ];

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/tenant/export', $payload)
            ->assertUnprocessable();

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/tenant/export', $payload)
            ->assertStatus(429);
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createCategory(Tenant $tenant, User $owner, string $name, string $slug): Category
    {
        return Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => 10,
        ]);
    }

    private function createTag(Tenant $tenant, string $name, string $normalizedName): Tag
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
    private function createMemory(Tenant $tenant, User $owner, array $attributes): Memory
    {
        return Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'category_id' => $attributes['category_id'] ?? null,
            'period_key' => $attributes['period_key'] ?? 'high_school',
            'occurred_on' => $attributes['occurred_on'] ?? '2010-01-01',
            'title' => $attributes['title'] ?? '記憶タイトル',
            'body' => $attributes['body'] ?? '記憶本文',
            'emotion_label' => $attributes['emotion_label'] ?? '普通',
            'emotion_intensity' => $attributes['emotion_intensity'] ?? 3,
            'visibility' => $attributes['visibility'] ?? Memory::VISIBILITY_PRIVATE,
            'source' => $attributes['source'] ?? 'manual',
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    private function clearTenantLifecycleRateLimit(?Tenant $tenant, User $user): void
    {
        $key = 'tenant-lifecycle:'.(string) ($tenant?->id ?? 'no-tenant').':'.$user->id;

        RateLimiter::clear($key);
        RateLimiter::clear(md5('bunshin-tenant-lifecycle'.$key));
    }
}
