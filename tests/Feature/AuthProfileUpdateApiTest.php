<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthProfileUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_profile_name(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'name' => 'Old Name',
            'email' => 'profile@example.test',
        ]);
        $currentToken = $user->createApiToken('profile-device');

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->patchJson('/api/v1/auth/profile', [
                'name' => '  New Profile Name  ',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'New Profile Name')
            ->assertJsonPath('data.user.email', 'profile@example.test')
            ->assertJsonPath('data.user.role', User::ROLE_OWNER);

        $user->refresh();

        $this->assertSame('New Profile Name', $user->name);
        $this->assertSame('profile@example.test', $user->email);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'New Profile Name');
    }

    public function test_profile_update_rejects_email_change_and_invalid_name(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Original Name',
            'email' => 'original@example.test',
        ]);
        $currentToken = $user->createApiToken('profile-device');

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'Renamed User',
                'email' => 'new@example.test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->patchJson('/api/v1/auth/profile', [
                'name' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $user->refresh();

        $this->assertSame('Original Name', $user->name);
        $this->assertSame('original@example.test', $user->email);
    }

    public function test_profile_update_requires_valid_bearer_token_and_tenant_context(): void
    {
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan@example.test',
        ]);
        $orphanToken = $orphanUser->createApiToken('orphan-device');

        $payload = [
            'name' => 'New Name',
        ];

        $this
            ->patchJson('/api/v1/auth/profile', $payload)
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->patchJson('/api/v1/auth/profile', $payload)
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->patchJson('/api/v1/auth/profile', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $orphanUser->refresh();

        $this->assertNotSame('New Name', $orphanUser->name);
    }
}
