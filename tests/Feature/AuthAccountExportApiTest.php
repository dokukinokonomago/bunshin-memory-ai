<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthAccountExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_export_account_data_with_secret_memories_locked_by_default(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'export@example.test',
        ]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $school = $this->createCategory($tenant, $user, '学校', 'school', 10);
        $club = $this->createCategory($tenant, $user, '部活', 'club', 20, $school);
        $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');

        $friend = $this->createTag($tenant, '友達', '友達');
        $secretOnlyTag = $this->createTag($tenant, '秘密', '秘密');
        $deletedOnlyTag = $this->createTag($tenant, '削除済み', '削除済み');
        $otherOwnerTag = $this->createTag($tenant, '他ユーザー', '他ユーザー');

        $private = $this->createMemory($tenant, $user, [
            'category_id' => $club->id,
            'period_key' => 'high_school',
            'occurred_on' => '2010-07-15',
            'title' => '放課後の教室',
            'body' => '放課後の教室で友達と話した。',
            'emotion_label' => '普通',
            'emotion_intensity' => 3,
            'visibility' => Memory::VISIBILITY_PRIVATE,
            'metadata' => [
                'importance_score' => 0.8,
            ],
        ]);
        $private->tags()->attach($friend);

        $shared = $this->createMemory($tenant, $user, [
            'title' => '共有できる記憶',
            'body' => '共有できる本文。',
            'visibility' => Memory::VISIBILITY_SHARED,
        ]);

        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $school->id,
            'title' => 'secret title must not leak',
            'body' => 'secret body must not leak',
            'visibility' => Memory::VISIBILITY_SECRET,
            'metadata' => [
                'beliefs' => ['secret belief must not leak'],
            ],
        ]);
        $secret->tags()->attach($secretOnlyTag);

        $deleted = $this->createMemory($tenant, $user, [
            'title' => 'deleted title must not leak',
            'body' => 'deleted body must not leak',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $deleted->tags()->attach($deletedOnlyTag);
        $deleted->delete();

        $otherOwnerMemory = $this->createMemory($tenant, $sameTenantOtherUser, [
            'title' => 'other owner title must not leak',
            'body' => 'other owner body must not leak',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $otherOwnerMemory->tags()->attach($otherOwnerTag);
        $this->createMemory($otherTenant, $otherTenantUser, [
            'title' => 'other tenant title must not leak',
            'body' => 'other tenant body must not leak',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $accessToken = $user->createApiToken('account-export');
        $this->clearAccountLifecycleRateLimit($user);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->postJson('/api/v1/auth/account/export', [
                'current_password' => 'password',
                'include_secret' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'export@example.test')
            ->assertJsonPath('data.user.public_id', $user->public_id)
            ->assertJsonPath('data.tenant.slug', 'bunshin-ai')
            ->assertJsonCount(2, 'data.categories')
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonCount(3, 'data.memories');

        $this->assertSame(['友達'], collect($response->json('data.tags'))->pluck('name')->all());

        $memories = collect($response->json('data.memories'))->keyBy('id');

        $this->assertTrue($memories->has($private->id));
        $this->assertTrue($memories->has($shared->id));
        $this->assertTrue($memories->has($secret->id));
        $this->assertFalse($memories->has($deleted->id));
        $this->assertSame('放課後の教室', $memories[$private->id]['title']);
        $this->assertSame('放課後の教室で友達と話した。', $memories[$private->id]['body']);
        $this->assertSame(['友達'], $memories[$private->id]['tags']);
        $this->assertSame(0.8, $memories[$private->id]['metadata']['importance_score']);
        $this->assertSame($club->id, $memories[$private->id]['category']['id']);
        $this->assertSame($club->public_id, $memories[$private->id]['category']['public_id']);

        $lockedSecret = $memories[$secret->id];
        $this->assertSame(Memory::VISIBILITY_SECRET, $lockedSecret['visibility']);
        $this->assertTrue($lockedSecret['locked']);
        $this->assertArrayNotHasKey('title', $lockedSecret);
        $this->assertArrayNotHasKey('body', $lockedSecret);
        $this->assertArrayNotHasKey('tags', $lockedSecret);
        $this->assertArrayNotHasKey('metadata', $lockedSecret);

        $content = $response->getContent();
        $this->assertStringNotContainsString('secret title must not leak', $content);
        $this->assertStringNotContainsString('secret body must not leak', $content);
        $this->assertStringNotContainsString('secret belief must not leak', $content);
        $this->assertStringNotContainsString('秘密', $content);
        $this->assertStringNotContainsString('deleted title must not leak', $content);
        $this->assertStringNotContainsString('other owner title must not leak', $content);
        $this->assertStringNotContainsString('other tenant title must not leak', $content);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $accessToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event_type' => SecurityEvent::TYPE_ACCOUNT_EXPORT_REQUEST,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'export@example.test',
        ]);
    }

    public function test_valid_secret_unlock_token_allows_account_export_to_include_secret_memories(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'secret-export@example.test',
        ]);
        $category = $this->createCategory($tenant, $user, '家族', 'family');
        $secretTag = $this->createTag($tenant, '秘密', '秘密');
        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '秘匿記憶',
            'body' => '秘匿本文',
            'visibility' => Memory::VISIBILITY_SECRET,
            'metadata' => [
                'importance_score' => 1,
            ],
        ]);
        $secret->tags()->attach($secretTag);

        $accessToken = $user->createApiToken('account-export');
        $unlockToken = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->assertCreated()
            ->json('data.unlock_token');

        $storedUnlockToken = SecretUnlockToken::findToken($unlockToken);
        $this->assertNull($storedUnlockToken?->last_used_at);
        $this->clearAccountLifecycleRateLimit($user);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->withHeader('X-Secret-Unlock', $unlockToken)
            ->postJson('/api/v1/auth/account/export', [
                'current_password' => 'password',
                'include_secret' => true,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonCount(1, 'data.memories');

        $memory = $response->json('data.memories.0');

        $this->assertSame($secret->id, $memory['id']);
        $this->assertFalse($memory['locked']);
        $this->assertSame('秘匿記憶', $memory['title']);
        $this->assertSame('秘匿本文', $memory['body']);
        $this->assertSame(['秘密'], $memory['tags']);
        $this->assertSame(1, $memory['metadata']['importance_score']);
        $this->assertSame(['秘密'], collect($response->json('data.tags'))->pluck('name')->all());
        $this->assertNull($storedUnlockToken->refresh()->last_used_at);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_EXPORT_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertTrue($event->metadata['include_secret']);
        $this->assertTrue($event->metadata['secret_unlocked']);
    }

    public function test_account_export_rejects_invalid_password_secret_unlock_token_and_tenant_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invalid-export@example.test',
        ]);
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan-export@example.test',
        ]);
        $secret = $this->createMemory($tenant, $user, [
            'title' => 'secret title must not leak',
            'body' => 'secret body must not leak',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);
        $accessToken = $user->createApiToken('account-export');
        $orphanToken = $orphanUser->createApiToken('orphan-export');
        $this->clearAccountLifecycleRateLimit($user);
        $this->clearAccountLifecycleRateLimit($orphanUser);

        $this
            ->postJson('/api/v1/auth/account/export', [
                'current_password' => 'password',
            ])
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->postJson('/api/v1/auth/account/export', [
                'current_password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $wrongPasswordResponse = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->postJson('/api/v1/auth/account/export', [
                'current_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertStringNotContainsString('secret title must not leak', $wrongPasswordResponse->getContent());

        $passwordFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_EXPORT_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'invalid_current_password')
            ->sole();

        $this->assertSame($tenant->id, $passwordFailure->tenant_id);
        $this->assertSame($user->id, $passwordFailure->user_id);
        $this->assertSame('invalid-export@example.test', $passwordFailure->subject_email);

        $invalidSecretResponse = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->withHeader('X-Secret-Unlock', 'invalid-token')
            ->postJson('/api/v1/auth/account/export', [
                'current_password' => 'password',
                'include_secret' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['secret_unlock_token']);

        $this->assertStringNotContainsString((string) $secret->title, $invalidSecretResponse->getContent());
        $this->assertStringNotContainsString((string) $secret->body, $invalidSecretResponse->getContent());

        $secretFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_EXPORT_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'invalid_secret_unlock_token')
            ->sole();

        $this->assertSame($tenant->id, $secretFailure->tenant_id);
        $this->assertSame($user->id, $secretFailure->user_id);
        $this->assertTrue($secretFailure->metadata['include_secret']);
    }

    public function test_account_export_validates_payload_shape_and_is_rate_limited_per_authenticated_user(): void
    {
        config(['bunshin.security.rate_limits.account_lifecycle.per_minute' => 1]);

        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $accessToken = $user->createApiToken('account-export');
        $this->clearAccountLifecycleRateLimit($user);

        $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->postJson('/api/v1/auth/account/export', [
                'current_password' => '',
                'include_secret' => 'maybe',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'include_secret']);

        $this->clearAccountLifecycleRateLimit($user);

        $payload = [
            'current_password' => 'wrong-password',
        ];

        $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->postJson('/api/v1/auth/account/export', $payload)
            ->assertUnprocessable();

        $this
            ->withHeader('Authorization', 'Bearer '.$accessToken->plainTextToken)
            ->postJson('/api/v1/auth/account/export', $payload)
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
        int $sortOrder = 10,
        ?Category $parent = null,
    ): Category {
        return Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
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
}
