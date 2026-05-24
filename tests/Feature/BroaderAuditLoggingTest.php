<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BroaderAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_lifecycle_profile_and_secret_unlock_password_writes_are_audited(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'secret_unlock_password' => null,
        ]);

        $profileToken = $user->createApiToken('profile-device');

        $this
            ->withHeader('Authorization', 'Bearer '.$profileToken->plainTextToken)
            ->patchJson('/api/v1/auth/profile', [
                'name' => ' Audit User ',
            ])
            ->assertOk();

        $profileEvent = $this->event(SecurityEvent::TYPE_PROFILE_UPDATE);

        $this->assertSame($tenant->id, $profileEvent->tenant_id);
        $this->assertSame($user->id, $profileEvent->user_id);
        $this->assertNull($profileEvent->subject_email);
        $this->assertSame(['name'], $profileEvent->metadata['changed_fields']);

        $this
            ->withHeader('Authorization', 'Bearer '.$profileToken->plainTextToken)
            ->putJson('/api/v1/secret-unlock-password', [
                'account_password' => 'password',
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertOk();

        $setupEvent = $this->event(SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_CHANGE);
        $this->assertSame('set', $setupEvent->metadata['mode']);
        $this->assertAuditMetadataExcludes($setupEvent, ['password', 'new-secret-password']);

        $this
            ->withHeader('Authorization', 'Bearer '.$profileToken->plainTextToken)
            ->putJson('/api/v1/secret-unlock-password', [
                'account_password' => 'password',
                'current_password' => 'new-secret-password',
                'password' => 'next-secret-password',
                'password_confirmation' => 'next-secret-password',
            ])
            ->assertOk();

        $changeEvent = $this->event(SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_CHANGE);
        $this->assertSame('changed', $changeEvent->metadata['mode']);
        $this->assertAuditMetadataExcludes($changeEvent, ['new-secret-password', 'next-secret-password']);

        $currentToken = $user->createApiToken('current-device');
        $targetToken = $user->createApiToken('stale-device');

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$targetToken->accessToken->id)
            ->assertNoContent();

        $revokeEvent = $this->event(SecurityEvent::TYPE_TOKEN_REVOKE);
        $this->assertSame($targetToken->accessToken->id, $revokeEvent->metadata['token_id']);
        $this->assertFalse($revokeEvent->metadata['revoked_current_token']);
        $this->assertAuditMetadataExcludes($revokeEvent, [$targetToken->plainTextToken]);

        $rotateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->postJson('/api/v1/auth/tokens/rotate')
            ->assertCreated();

        $rotatedPlainTextToken = $rotateResponse->json('data.access_token');
        $rotateEvent = $this->event(SecurityEvent::TYPE_TOKEN_ROTATE);
        $this->assertSame($currentToken->accessToken->id, $rotateEvent->metadata['rotated_from_token_id']);
        $this->assertSame($rotateResponse->json('data.token.id'), $rotateEvent->metadata['rotated_to_token_id']);
        $this->assertAuditMetadataExcludes($rotateEvent, [$currentToken->plainTextToken, $rotatedPlainTextToken]);

        $this
            ->withHeader('Authorization', 'Bearer '.$rotatedPlainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $logoutEvent = $this->event(SecurityEvent::TYPE_TOKEN_LOGOUT);
        $this->assertSame($rotateResponse->json('data.token.id'), $logoutEvent->metadata['token_id']);
        $this->assertAuditMetadataExcludes($logoutEvent, [$rotatedPlainTextToken]);

        $revokeAllUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $revokeAllCurrentToken = $revokeAllUser->createApiToken('current-device');
        $revokeAllUser->createApiToken('other-device');

        $this
            ->withHeader('Authorization', 'Bearer '.$revokeAllCurrentToken->plainTextToken)
            ->postJson('/api/v1/auth/tokens/revoke-all')
            ->assertNoContent();

        $revokeAllEvent = $this->event(SecurityEvent::TYPE_TOKEN_REVOKE_ALL);
        $this->assertSame($revokeAllUser->id, $revokeAllEvent->user_id);
        $this->assertSame(2, $revokeAllEvent->metadata['tokens_revoked']);
        $this->assertAuditMetadataExcludes($revokeAllEvent, [$revokeAllCurrentToken->plainTextToken]);
    }

    public function test_tenant_member_management_writes_are_audited_with_public_ids(): void
    {
        Notification::fake();

        $tenant = $this->tenant();
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_MEMBER,
            'email' => 'member@example.test',
        ]);

        $inviteResponse = $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'invitee@example.test',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertCreated();

        $createInvitationEvent = $this->event(SecurityEvent::TYPE_TENANT_INVITATION_CREATE);
        $this->assertSame($owner->id, $createInvitationEvent->user_id);
        $this->assertNull($createInvitationEvent->subject_email);
        $this->assertSame($inviteResponse->json('data.public_id'), $createInvitationEvent->metadata['resource_public_id']);
        $this->assertSame(User::ROLE_ADMIN, $createInvitationEvent->metadata['target_role']);
        $this->assertAuditMetadataExcludes($createInvitationEvent, ['invitee@example.test', $inviteResponse->json('data.invite_token')]);

        $this
            ->withApiToken($owner)
            ->deleteJson('/api/v1/tenant/invitations/'.$inviteResponse->json('data.public_id'))
            ->assertNoContent();

        $revokeInvitationEvent = $this->event(SecurityEvent::TYPE_TENANT_INVITATION_REVOKE);
        $this->assertSame($inviteResponse->json('data.public_id'), $revokeInvitationEvent->metadata['resource_public_id']);
        $this->assertFalse($revokeInvitationEvent->metadata['was_already_revoked']);

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$member->public_id.'/role', [
                'role' => User::ROLE_ADMIN,
            ])
            ->assertOk();

        $roleEvent = $this->event(SecurityEvent::TYPE_TENANT_MEMBER_ROLE_CHANGE);
        $this->assertSame($member->public_id, $roleEvent->metadata['subject_user_public_id']);
        $this->assertSame(User::ROLE_OWNER, $roleEvent->metadata['manager_role']);
        $this->assertSame(User::ROLE_MEMBER, $roleEvent->metadata['previous_role']);
        $this->assertSame(User::ROLE_ADMIN, $roleEvent->metadata['new_role']);
        $this->assertAuditMetadataExcludes($roleEvent, ['member@example.test']);

        $memberToken = $member->createApiToken('member-device');

        $this
            ->withApiToken($owner)
            ->deleteJson('/api/v1/tenant/members/'.$member->public_id)
            ->assertNoContent();

        $memberRevokeEvent = $this->event(SecurityEvent::TYPE_TENANT_MEMBER_REVOKE);
        $this->assertSame($member->public_id, $memberRevokeEvent->metadata['subject_user_public_id']);
        $this->assertSame(User::ROLE_ADMIN, $memberRevokeEvent->metadata['previous_role']);
        $this->assertSame(1, $memberRevokeEvent->metadata['tokens_revoked']);
        $this->assertAuditMetadataExcludes($memberRevokeEvent, ['member@example.test', $memberToken->plainTextToken]);
    }

    public function test_memory_and_category_writes_are_audited_without_content_names_or_tag_values(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $categoryResponse = $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => ' 秘密カテゴリ ',
                'slug' => 'secret-category',
            ])
            ->assertCreated();

        $category = Category::query()->firstOrFail();
        $categoryCreateEvent = $this->event(SecurityEvent::TYPE_CATEGORY_CREATE);
        $this->assertSame($category->public_id, $categoryCreateEvent->metadata['resource_public_id']);
        $this->assertAuditMetadataExcludes($categoryCreateEvent, ['秘密カテゴリ', 'secret-category']);

        $memoryResponse = $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'title' => '監査に残さないタイトル',
                'body' => '監査に残さない本文',
                'visibility' => Memory::VISIBILITY_SECRET,
                'category_id' => $categoryResponse->json('data.public_id'),
                'tags' => ['秘密タグ', '友達'],
            ])
            ->assertCreated();

        $memory = Memory::query()->firstOrFail();
        $memoryCreateEvent = $this->event(SecurityEvent::TYPE_MEMORY_CREATE);
        $this->assertSame($memory->public_id, $memoryCreateEvent->metadata['resource_public_id']);
        $this->assertSame(Memory::VISIBILITY_SECRET, $memoryCreateEvent->metadata['visibility']);
        $this->assertSame($category->public_id, $memoryCreateEvent->metadata['category_public_id']);
        $this->assertSame(2, $memoryCreateEvent->metadata['tag_count']);
        $this->assertAuditMetadataExcludes($memoryCreateEvent, ['監査に残さないタイトル', '監査に残さない本文', '秘密カテゴリ', '秘密タグ', '友達']);

        $this
            ->withApiToken($user)
            ->patchJson('/api/v1/memories/'.$memoryResponse->json('data.public_id'), [
                'title' => '更新後タイトル',
                'body' => '更新後本文',
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'tags' => ['更新タグ'],
            ])
            ->assertOk();

        $memoryUpdateEvent = $this->event(SecurityEvent::TYPE_MEMORY_UPDATE);
        $this->assertSame($memory->public_id, $memoryUpdateEvent->metadata['resource_public_id']);
        $this->assertEqualsCanonicalizing(['title', 'body', 'visibility', 'tags'], $memoryUpdateEvent->metadata['changed_fields']);
        $this->assertSame(1, $memoryUpdateEvent->metadata['tag_count']);
        $this->assertAuditMetadataExcludes($memoryUpdateEvent, ['更新後タイトル', '更新後本文', '更新タグ']);

        $this
            ->withApiToken($user)
            ->deleteJson('/api/v1/memories/'.$memoryResponse->json('data.public_id'))
            ->assertNoContent();

        $memoryDeleteEvent = $this->event(SecurityEvent::TYPE_MEMORY_DELETE);
        $this->assertSame($memory->public_id, $memoryDeleteEvent->metadata['resource_public_id']);
        $this->assertSame(1, $memoryDeleteEvent->metadata['tag_count']);
        $this->assertAuditMetadataExcludes($memoryDeleteEvent, ['更新後タイトル', '更新後本文', '更新タグ']);

        $this
            ->withApiToken($user)
            ->patchJson('/api/v1/categories/'.$categoryResponse->json('data.public_id'), [
                'name' => '更新後カテゴリ',
                'slug' => 'updated-category',
            ])
            ->assertOk();

        $categoryUpdateEvent = $this->event(SecurityEvent::TYPE_CATEGORY_UPDATE);
        $this->assertSame($category->public_id, $categoryUpdateEvent->metadata['resource_public_id']);
        $this->assertEqualsCanonicalizing(['name', 'slug'], $categoryUpdateEvent->metadata['changed_fields']);
        $this->assertAuditMetadataExcludes($categoryUpdateEvent, ['更新後カテゴリ', 'updated-category']);

        $this
            ->withApiToken($user)
            ->deleteJson('/api/v1/categories/'.$categoryResponse->json('data.public_id'))
            ->assertNoContent();

        $categoryDeleteEvent = $this->event(SecurityEvent::TYPE_CATEGORY_DELETE);
        $this->assertSame($category->public_id, $categoryDeleteEvent->metadata['resource_public_id']);
        $this->assertSame(0, $categoryDeleteEvent->metadata['affected_memory_count']);
        $this->assertAuditMetadataExcludes($categoryDeleteEvent, ['更新後カテゴリ', 'updated-category']);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
    }

    private function event(string $eventType): SecurityEvent
    {
        return SecurityEvent::query()
            ->where('event_type', $eventType)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @param  array<int, string|null>  $needles
     */
    private function assertAuditMetadataExcludes(SecurityEvent $event, array $needles): void
    {
        $encodedMetadata = json_encode($event->metadata, JSON_UNESCAPED_UNICODE);

        $this->assertIsString($encodedMetadata);

        foreach ($needles as $needle) {
            if ($needle === null || $needle === '') {
                continue;
            }

            $this->assertStringNotContainsString($needle, $encodedMetadata);
        }
    }
}
