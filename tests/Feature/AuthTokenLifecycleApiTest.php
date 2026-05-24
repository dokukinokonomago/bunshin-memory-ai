<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_only_their_own_token_metadata(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $currentToken = $user->createApiToken('current-device');
        $otherToken = $user->createApiToken('other-device');
        $hiddenToken = $otherUser->createApiToken('hidden-device');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonPath('data.0.id', $currentToken->accessToken->id)
            ->assertJsonPath('data.0.name', 'current-device')
            ->assertJsonPath('data.0.abilities', ['*'])
            ->assertJsonPath('data.0.is_current', true);

        $tokens = $response->json('data');
        $tokenIds = array_column($tokens, 'id');

        $this->assertCount(2, $tokens);
        $this->assertContains($currentToken->accessToken->id, $tokenIds);
        $this->assertContains($otherToken->accessToken->id, $tokenIds);
        $this->assertNotContains($hiddenToken->accessToken->id, $tokenIds);

        foreach ($tokens as $token) {
            $this->assertArrayNotHasKey('token', $token);
            $this->assertArrayNotHasKey('access_token', $token);
        }

        $this->assertNotNull($currentToken->accessToken->fresh()->last_used_at);
    }

    public function test_authenticated_user_can_revoke_one_owned_token_only(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $currentToken = $user->createApiToken('current-device');
        $targetToken = $user->createApiToken('stale-device');
        $otherUserToken = $otherUser->createApiToken('other-user-device');

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$targetToken->accessToken->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $targetToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$otherUserToken->accessToken->id)
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherUserToken->accessToken->id,
        ]);
    }

    public function test_authenticated_user_can_revoke_all_of_their_tokens_only(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $currentToken = $user->createApiToken('current-device');
        $otherToken = $user->createApiToken('other-device');
        $otherUserToken = $otherUser->createApiToken('other-user-device');

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->postJson('/api/v1/auth/tokens/revoke-all')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherUserToken->accessToken->id,
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_rotate_the_current_token(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $expiresAt = now()->addDay()->startOfSecond();
        $currentToken = $user->createApiToken(
            name: 'laptop',
            abilities: ['memories:read'],
            expiresAt: $expiresAt,
        );
        $otherToken = $user->createApiToken('mobile');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->postJson('/api/v1/auth/tokens/rotate')
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', $expiresAt->toAtomString())
            ->assertJsonPath('data.token.name', 'laptop')
            ->assertJsonPath('data.token.abilities', ['memories:read'])
            ->assertJsonPath('data.token.is_current', true)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token' => [
                        'id',
                        'created_at',
                    ],
                ],
            ]);

        $plainTextToken = $response->json('data.access_token');
        [$id, $rawToken] = explode('|', $plainTextToken, 2);
        $newStoredToken = PersonalAccessToken::query()->findOrFail((int) $id);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
        $this->assertSame($user->id, $newStoredToken->tokenable_id);
        $this->assertSame('laptop', $newStoredToken->name);
        $this->assertSame(['memories:read'], $newStoredToken->abilities);
        $this->assertTrue($newStoredToken->expires_at->equalTo($expiresAt));
        $this->assertTrue(hash_equals($newStoredToken->token, hash('sha256', $rawToken)));

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newStoredToken->id)
            ->assertJsonPath('data.0.is_current', true);
    }

    public function test_token_lifecycle_requires_valid_token_and_tenant_context(): void
    {
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan@example.test',
        ]);
        $orphanToken = $orphanUser->createApiToken('orphan-device');

        $this
            ->getJson('/api/v1/auth/tokens')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/v1/auth/tokens/revoke-all')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->postJson('/api/v1/auth/tokens/rotate')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$orphanToken->accessToken->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');
    }
}
