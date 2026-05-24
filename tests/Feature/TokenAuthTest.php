<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_bearer_token_authenticates_protected_api_routes(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'feature-test',
        ]);
        $this->assertNotNull($user->personalAccessTokens()->firstOrFail()->last_used_at);
    }

    public function test_session_authentication_does_not_authenticate_token_first_api_routes(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->actingAs($user)
            ->getJson('/api/v1/categories')
            ->assertUnauthorized();
    }

    public function test_invalid_and_expired_bearer_tokens_are_rejected(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $expiredToken = $user->createApiToken('expired-test', expiresAt: now()->subMinute());

        $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/categories')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$expiredToken->plainTextToken)
            ->getJson('/api/v1/categories')
            ->assertUnauthorized();
    }

    public function test_existing_bearer_tokens_for_disabled_and_suspended_users_are_rejected(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);

        foreach ([User::ACCOUNT_STATUS_DISABLED, User::ACCOUNT_STATUS_SUSPENDED] as $status) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'account_status' => $status,
            ]);
            $token = $user->createApiToken("{$status}-test");

            $this
                ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
                ->getJson('/api/v1/categories')
                ->assertUnauthorized();

            $this->assertNull($token->accessToken->fresh()->last_used_at);
        }
    }

    public function test_plain_bearer_token_without_id_prefix_authenticates_api_routes(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $user->personalAccessTokens()->create([
            'name' => 'local-dev',
            'token' => hash('sha256', 'local-dev-token'),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $this
            ->withHeader('Authorization', 'Bearer local-dev-token')
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
