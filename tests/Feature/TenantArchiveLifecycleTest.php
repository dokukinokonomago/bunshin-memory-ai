<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\PersonalAccessToken;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantArchiveLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_archive_lifecycle_fields_are_cast_and_mark_plan_inactive(): void
    {
        $tenant = $this->tenant();
        $archiver = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);

        $archivedAt = Carbon::parse('2026-05-15 09:00:00');
        $scheduledDeletionAt = Carbon::parse('2026-06-14 09:00:00');

        $tenant->forceFill([
            'archived_at' => $archivedAt,
            'archived_by_user_id' => $archiver->id,
            'archive_reason' => 'No longer using this tenant.',
            'deletion_requested_at' => $archivedAt,
            'scheduled_deletion_at' => $scheduledDeletionAt,
        ])->save();

        $tenant->refresh();

        $this->assertTrue($tenant->isArchived());
        $this->assertFalse($tenant->hasActivePlan());
        $this->assertTrue($tenant->archived_at?->equalTo($archivedAt));
        $this->assertTrue($tenant->deletion_requested_at?->equalTo($archivedAt));
        $this->assertTrue($tenant->scheduled_deletion_at?->equalTo($scheduledDeletionAt));
        $this->assertNull($tenant->purged_at);
        $this->assertSame('No longer using this tenant.', $tenant->archive_reason);
        $this->assertTrue($tenant->archivedBy?->is($archiver));
    }

    public function test_login_rejects_archived_tenant_without_issuing_token_and_logs_failure(): void
    {
        $tenant = $this->tenant([
            'archived_at' => now(),
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'owner@example.test',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'owner@example.test',
                'password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant is archived.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $event = SecurityEvent::query()->firstOrFail();
        $this->assertSame(SecurityEvent::TYPE_LOGIN, $event->event_type);
        $this->assertSame(SecurityEvent::OUTCOME_FAILURE, $event->outcome);
        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('tenant_archived', $event->metadata['reason']);
    }

    public function test_existing_bearer_token_for_archived_tenant_is_rejected_without_touching_last_used_at(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createApiToken('before-archive');

        $tenant->forceFill(['archived_at' => now()])->save();

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/categories')
            ->assertUnauthorized();

        $this->assertNull($token->accessToken->fresh()->last_used_at);
    }

    public function test_archived_tenant_cannot_reach_write_or_tenant_lifecycle_endpoints(): void
    {
        $tenant = $this->tenant([
            'archived_at' => now(),
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $token = $user->createApiToken('archived-tenant');

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/memories', [
                'body' => 'Archived tenant cannot write.',
                'visibility' => Memory::VISIBILITY_PRIVATE,
            ])
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/tenant/export', [
                'current_password' => 'password',
            ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('memories', 0);
        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->assertDatabaseMissing('security_events', [
            'event_type' => SecurityEvent::TYPE_TENANT_EXPORT_REQUEST,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Bunshin AI',
            'slug' => 'bunshin-ai',
            ...$attributes,
        ])->refresh();
    }
}
