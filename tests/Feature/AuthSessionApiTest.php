<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_current_session_context(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'admin@example.test',
            'name' => 'Admin User',
        ]);
        $newAccessToken = $user->createApiToken('session-test', expiresAt: now()->addDay());

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$newAccessToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Admin User')
            ->assertJsonPath('data.user.email', 'admin@example.test')
            ->assertJsonPath('data.user.role', User::ROLE_OWNER)
            ->assertJsonPath('data.tenant.id', $tenant->id)
            ->assertJsonPath('data.tenant.name', '分身AI')
            ->assertJsonPath('data.tenant.slug', 'bunshin-ai')
            ->assertJsonPath('data.token.id', $newAccessToken->accessToken->id)
            ->assertJsonPath('data.token.name', 'session-test')
            ->assertJsonPath('data.token.abilities', ['*']);

        $this->assertNotNull($response->json('data.token.last_used_at'));
        $this->assertNotNull($response->json('data.token.expires_at'));
        $this->assertNotNull($response->json('data.token.created_at'));
    }

    public function test_me_rejects_authenticated_user_without_tenant_context(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan@example.test',
        ]);
        $newAccessToken = $user->createApiToken('orphan-test');

        $this
            ->withHeader('Authorization', 'Bearer '.$newAccessToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');
    }

    public function test_me_requires_a_valid_bearer_token(): void
    {
        $this
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $currentToken = $user->createApiToken('current-session');
        $otherToken = $user->createApiToken('other-session');

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$otherToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.token.id', $otherToken->accessToken->id);

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }
}
