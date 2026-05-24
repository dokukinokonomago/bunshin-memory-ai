<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthAccountDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_account_and_erase_owned_data(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'delete@example.test',
            'pending_email' => 'pending-delete@example.test',
            'pending_email_requested_at' => now(),
        ]);
        $retainedOwner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'retained-owner@example.test',
        ]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $parentCategory = $this->createCategory($tenant, $user, '学校', 'school');
        $childCategory = $this->createCategory($tenant, $user, '部活', 'club', $parentCategory);
        $sameTenantOtherCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');

        $ownedOnlyTag = $this->createTag($tenant, '消えるタグ', '消えるタグ');
        $secretOnlyTag = $this->createTag($tenant, '秘密タグ', '秘密タグ');
        $deletedOnlyTag = $this->createTag($tenant, '削除済みタグ', '削除済みタグ');
        $sharedTag = $this->createTag($tenant, '共有タグ', '共有タグ');
        $otherTenantTag = $this->createTag($otherTenant, '別テナントタグ', '別テナントタグ');

        $private = $this->createMemory($tenant, $user, [
            'category_id' => $childCategory->id,
            'title' => '削除される通常記憶',
            'body' => '削除される本文',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $private->tags()->attach([$ownedOnlyTag->id, $sharedTag->id]);

        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $parentCategory->id,
            'title' => 'secret title must not leak',
            'body' => 'secret body must not leak',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);
        $secret->tags()->attach($secretOnlyTag);

        $alreadyDeleted = $this->createMemory($tenant, $user, [
            'title' => '既に削除済み',
            'body' => '既に削除済み本文',
        ]);
        $alreadyDeleted->tags()->attach($deletedOnlyTag);
        $alreadyDeleted->delete();

        $sameTenantOtherMemory = $this->createMemory($tenant, $sameTenantOtherUser, [
            'category_id' => $sameTenantOtherCategory->id,
            'title' => '残る別ユーザー記憶',
            'body' => '残る別ユーザー本文',
        ]);
        $sameTenantOtherMemory->tags()->attach($sharedTag);

        $otherTenantMemory = $this->createMemory($otherTenant, $otherTenantUser, [
            'category_id' => $otherTenantCategory->id,
            'title' => '残る別テナント記憶',
            'body' => '残る別テナント本文',
        ]);
        $otherTenantMemory->tags()->attach($otherTenantTag);

        $accessToken = $user->createApiToken('account-delete');
        $extraToken = $user->createApiToken('account-delete-extra');
        $unlockToken = $user->secretUnlockTokens()->create([
            'token' => hash('sha256', 'secret-unlock-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $pendingInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $user->id,
            'email' => 'pending-invite@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'pending-token'),
            'expires_at' => now()->addDays(7),
        ]);
        $acceptedInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $user->id,
            'accepted_user_id' => $sameTenantOtherUser->id,
            'email' => 'accepted-invite@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'accepted-token'),
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);
        $otherPendingInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $retainedOwner->id,
            'email' => 'other-pending-invite@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'other-pending-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this->clearAccountLifecycleRateLimit($user);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->deleteJson('/api/v1/auth/account', [
                'current_password' => 'password',
                'confirmation' => 'DELETE',
                'reason' => '  No longer using the service  ',
            ])
            ->assertNoContent();

        $this->assertStringNotContainsString('secret title must not leak', $response->getContent());
        $this->assertStringNotContainsString('secret body must not leak', $response->getContent());

        $deletedUser = $user->refresh();
        $this->assertNull($deletedUser->tenant_id);
        $this->assertSame(User::ROLE_MEMBER, $deletedUser->role);
        $this->assertSame(User::ACCOUNT_STATUS_DISABLED, $deletedUser->account_status);
        $this->assertSame('Deleted User', $deletedUser->name);
        $this->assertStringStartsWith('deleted-user-'.$user->id.'-', $deletedUser->email);
        $this->assertStringEndsWith('@deleted.local', $deletedUser->email);
        $this->assertNull($deletedUser->pending_email);
        $this->assertNull($deletedUser->pending_email_requested_at);
        $this->assertNull($deletedUser->email_verified_at);
        $this->assertNull($deletedUser->secret_unlock_password);
        $this->assertNotNull($deletedUser->deleted_at);
        $this->assertNotNull($deletedUser->anonymized_at);
        $this->assertFalse(Hash::check('password', (string) $deletedUser->password));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $extraToken->accessToken->id]);
        $this->assertDatabaseMissing('secret_unlock_tokens', ['id' => $unlockToken->id]);

        $this->assertSoftDeleted('memories', ['id' => $private->id]);
        $this->assertSoftDeleted('memories', ['id' => $secret->id]);
        $this->assertSoftDeleted('memories', ['id' => $alreadyDeleted->id]);
        $this->assertDatabaseHas('memories', ['id' => $sameTenantOtherMemory->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('memories', ['id' => $otherTenantMemory->id, 'deleted_at' => null]);

        $this->assertDatabaseMissing('memory_tag', ['memory_id' => $private->id]);
        $this->assertDatabaseMissing('memory_tag', ['memory_id' => $secret->id]);
        $this->assertDatabaseMissing('memory_tag', ['memory_id' => $alreadyDeleted->id]);
        $this->assertDatabaseHas('memory_tag', [
            'memory_id' => $sameTenantOtherMemory->id,
            'tag_id' => $sharedTag->id,
        ]);

        $this->assertDatabaseMissing('categories', ['id' => $parentCategory->id]);
        $this->assertDatabaseMissing('categories', ['id' => $childCategory->id]);
        $this->assertDatabaseHas('categories', ['id' => $sameTenantOtherCategory->id]);
        $this->assertDatabaseHas('categories', ['id' => $otherTenantCategory->id]);

        $this->assertDatabaseMissing('tags', ['id' => $ownedOnlyTag->id]);
        $this->assertDatabaseMissing('tags', ['id' => $secretOnlyTag->id]);
        $this->assertDatabaseMissing('tags', ['id' => $deletedOnlyTag->id]);
        $this->assertDatabaseHas('tags', ['id' => $sharedTag->id]);
        $this->assertDatabaseHas('tags', ['id' => $otherTenantTag->id]);

        $this->assertNotNull($pendingInvitation->refresh()->revoked_at);
        $this->assertNull($acceptedInvitation->refresh()->revoked_at);
        $this->assertNull($otherPendingInvitation->refresh()->revoked_at);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_DELETE)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('delete@example.test', $event->subject_email);
        $this->assertSame('No longer using the service', $event->metadata['reason']);
        $this->assertSame(2, $event->metadata['memories_deleted']);
        $this->assertSame(2, $event->metadata['categories_deleted']);
        $this->assertSame(3, $event->metadata['tags_pruned']);
        $this->assertSame(1, $event->metadata['pending_invitations_revoked']);
        $this->assertStringNotContainsString('delete@example.test', (string) json_encode($event->metadata));
    }

    public function test_account_deletion_rejects_invalid_password_confirmation_tenant_context_and_last_active_owner(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $retainedOwner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_MEMBER,
            'email' => 'invalid-delete@example.test',
        ]);
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan-delete@example.test',
        ]);
        $lastOwner = User::factory()->create([
            'tenant_id' => $this->createTenant('Last Owner', 'last-owner')->id,
            'role' => User::ROLE_OWNER,
            'email' => 'last-owner@example.test',
        ]);

        $token = $user->createApiToken('account-delete');
        $orphanToken = $orphanUser->createApiToken('orphan-delete');
        $lastOwnerToken = $lastOwner->createApiToken('last-owner-delete');
        $this->clearAccountLifecycleRateLimit($user);
        $this->clearAccountLifecycleRateLimit($orphanUser);
        $this->clearAccountLifecycleRateLimit($lastOwner);

        $this
            ->deleteJson('/api/v1/auth/account', [
                'current_password' => 'password',
                'confirmation' => 'DELETE',
            ])
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->deleteJson('/api/v1/auth/account', [
                'current_password' => 'password',
                'confirmation' => 'DELETE',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson('/api/v1/auth/account', [
                'current_password' => 'password',
                'confirmation' => 'delete',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmation']);

        $wrongPasswordResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson('/api/v1/auth/account', [
                'current_password' => 'wrong-password',
                'confirmation' => 'DELETE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertStringNotContainsString('wrong-password', $wrongPasswordResponse->getContent());
        $this->assertNull($user->refresh()->deleted_at);
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);

        $passwordFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_DELETE)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'invalid_current_password')
            ->sole();

        $this->assertSame($tenant->id, $passwordFailure->tenant_id);
        $this->assertSame($user->id, $passwordFailure->user_id);
        $this->assertSame('invalid-delete@example.test', $passwordFailure->subject_email);

        $this
            ->withHeader('Authorization', 'Bearer '.$lastOwnerToken->plainTextToken)
            ->deleteJson('/api/v1/auth/account', [
                'current_password' => 'password',
                'confirmation' => 'DELETE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account']);

        $this->assertNull($lastOwner->refresh()->deleted_at);
        $this->assertNotNull($retainedOwner->id);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $lastOwnerToken->accessToken->id]);

        $ownerFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_DELETE)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'last_active_owner')
            ->sole();

        $this->assertSame($lastOwner->id, $ownerFailure->user_id);
    }

    public function test_account_deletion_is_rate_limited_per_authenticated_user(): void
    {
        config(['bunshin.security.rate_limits.account_lifecycle.per_minute' => 1]);

        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_MEMBER,
        ]);
        $token = $user->createApiToken('account-delete-rate-limit');
        $this->clearAccountLifecycleRateLimit($user);

        $payload = [
            'current_password' => 'wrong-password',
            'confirmation' => 'DELETE',
        ];

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson('/api/v1/auth/account', $payload)
            ->assertUnprocessable();

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson('/api/v1/auth/account', $payload)
            ->assertStatus(429);
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function clearAccountLifecycleRateLimit(User $user): void
    {
        $key = 'account-lifecycle:'.$user->id;

        RateLimiter::clear($key);
        RateLimiter::clear(md5('bunshin-account-lifecycle'.$key));
    }

    private function createCategory(
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
            'source' => 'manual',
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }
}
