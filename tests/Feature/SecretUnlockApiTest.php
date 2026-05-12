<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\SecretUnlockToken;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecretUnlockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_issue_secret_unlock_token_with_account_password(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'unlock_token',
                    'expires_at',
                ],
            ]);

        $plainTextToken = $response->json('data.unlock_token');
        [$id, $token] = explode('|', $plainTextToken, 2);
        $storedToken = SecretUnlockToken::query()->findOrFail((int) $id);

        $this->assertSame($user->id, $storedToken->user_id);
        $this->assertTrue(hash_equals($storedToken->token, hash('sha256', $token)));
        $this->assertStringNotContainsString($token, $storedToken->token);
        $this->assertTrue($storedToken->expires_at->between(
            now()->addMinutes(14),
            now()->addMinutes(16),
        ));
        $this->assertSame($storedToken->expires_at->toAtomString(), $response->json('data.expires_at'));
    }

    public function test_secret_unlock_rejects_wrong_password_and_requires_tenant_user_authentication(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userWithoutTenant = User::factory()->create(['tenant_id' => null]);

        $this
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->assertUnauthorized();

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('secret_unlock_tokens', 0);

        $this
            ->withApiToken($userWithoutTenant)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->assertForbidden();
    }

    public function test_valid_secret_unlock_token_allows_memory_space_to_include_secret_memories(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '家族', 'family');
        $tag = $this->createTag($tenant, '秘密', '秘密');

        $visible = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '通常記憶',
            'body' => '通常本文',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '秘匿記憶',
            'body' => '秘匿本文',
            'visibility' => Memory::VISIBILITY_SECRET,
            'metadata' => [
                'importance_score' => 1,
            ],
        ]);
        $secret->tags()->attach($tag);

        $unlockToken = $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->json('data.unlock_token');

        $response = $this
            ->withApiToken($user)
            ->withHeader('X-Secret-Unlock', $unlockToken)
            ->getJson('/api/v1/memory-space?include_secret=1')
            ->assertOk()
            ->assertJsonPath('data.categories.0.memory_count', 2)
            ->assertJsonPath('data.categories.0.locked_secret_count', 0)
            ->assertJsonPath('data.secret.locked', false)
            ->assertJsonPath('data.secret.locked_count', 0);

        $this->assertNotNull($response->json('data.secret.unlock_expires_at'));

        $memories = collect($response->json('data.memories'))->keyBy('id');

        $this->assertTrue($memories->has($visible->id));
        $this->assertTrue($memories->has($secret->id));
        $this->assertSame('秘匿記憶', $memories[$secret->id]['title']);
        $this->assertSame('秘匿本文', $memories[$secret->id]['body']);
        $this->assertSame(['秘密'], $memories[$secret->id]['tags']);
        $this->assertSame(Memory::VISIBILITY_SECRET, $memories[$secret->id]['visibility']);

        $storedToken = SecretUnlockToken::findToken($unlockToken);

        $this->assertNotNull($storedToken?->last_used_at);
    }

    public function test_invalid_other_user_and_expired_secret_unlock_tokens_do_not_expose_secret_memories(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '家族', 'family');
        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '漏れてはいけない title',
            'body' => '漏れてはいけない body',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);

        $otherUserUnlockToken = $this
            ->withApiToken($otherUser)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->json('data.unlock_token');

        $this->assertMemorySpaceKeepsSecretLocked($user, $secret, $otherUserUnlockToken);

        $expiredPlainTextToken = Str::random(40);
        $expiredToken = SecretUnlockToken::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', $expiredPlainTextToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertMemorySpaceKeepsSecretLocked(
            $user,
            $secret,
            $expiredToken->getKey().'|'.$expiredPlainTextToken,
        );

        $this->assertMemorySpaceKeepsSecretLocked($user, $secret, 'invalid-token');
    }

    private function assertMemorySpaceKeepsSecretLocked(User $user, Memory $secret, string $unlockToken): void
    {
        $response = $this
            ->withApiToken($user)
            ->withHeader('X-Secret-Unlock', $unlockToken)
            ->getJson('/api/v1/memory-space?include_secret=1')
            ->assertOk()
            ->assertJsonPath('data.secret.locked', true)
            ->assertJsonPath('data.secret.locked_count', 1);

        $this->assertNotContains(
            $secret->id,
            collect($response->json('data.memories'))->pluck('id')->all(),
        );
        $this->assertStringNotContainsString('漏れてはいけない title', $response->getContent());
        $this->assertStringNotContainsString('漏れてはいけない body', $response->getContent());
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
            'body' => 'テスト用の記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
            ...$attributes,
        ]);
    }
}
