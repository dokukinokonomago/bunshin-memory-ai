<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_email_and_password_and_use_issued_bearer_token(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
            'email' => 'admin@example.test',
            'name' => 'Admin User',
        ]);

        $response = $this
            ->postJson('/api/v1/auth/login', [
                'email' => ' ADMIN@example.test ',
                'password' => 'password',
            ])
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', null)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Admin User')
            ->assertJsonPath('data.user.email', 'admin@example.test')
            ->assertJsonPath('data.user.role', User::ROLE_ADMIN)
            ->assertJsonPath('data.tenant.id', $tenant->id)
            ->assertJsonPath('data.tenant.name', '分身AI')
            ->assertJsonPath('data.tenant.slug', 'bunshin-ai')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                ],
            ]);

        $plainTextToken = $response->json('data.access_token');
        [$id, $token] = explode('|', $plainTextToken, 2);
        $storedToken = PersonalAccessToken::query()->findOrFail((int) $id);

        $this->assertSame($user->id, $storedToken->tokenable_id);
        $this->assertSame(User::class, $storedToken->tokenable_type);
        $this->assertSame('login', $storedToken->name);
        $this->assertTrue(hash_equals($storedToken->token, hash('sha256', $token)));
        $this->assertStringNotContainsString($token, $storedToken->token);

        $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->assertNotNull($storedToken->fresh()->last_used_at);
    }

    public function test_login_rejects_invalid_credentials_without_issuing_token(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin@example.test',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'admin@example.test',
                'password' => 'wrong-password',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'メールアドレスまたはパスワードが正しくありません。');

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'missing@example.test',
                'password' => 'password',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'メールアドレスまたはパスワードが正しくありません。');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_user_without_tenant_context(): void
    {
        User::factory()->create([
            'tenant_id' => null,
            'email' => 'admin@example.test',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'admin@example.test',
                'password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for API login.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_disabled_and_suspended_users_without_issuing_token(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);

        foreach ([User::ACCOUNT_STATUS_DISABLED, User::ACCOUNT_STATUS_SUSPENDED] as $status) {
            User::factory()->create([
                'tenant_id' => $tenant->id,
                'account_status' => $status,
                'email' => "{$status}@example.test",
            ]);

            $this
                ->postJson('/api/v1/auth/login', [
                    'email' => "{$status}@example.test",
                    'password' => 'password',
                ])
                ->assertForbidden()
                ->assertJsonPath('message', 'Account is not active.');
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $events = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_LOGIN)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame('account_not_active', $events[0]->metadata['reason']);
        $this->assertSame(User::ACCOUNT_STATUS_DISABLED, $events[0]->metadata['account_status']);
        $this->assertSame('account_not_active', $events[1]->metadata['reason']);
        $this->assertSame(User::ACCOUNT_STATUS_SUSPENDED, $events[1]->metadata['account_status']);
    }

    public function test_login_validates_payload_shape(): void
    {
        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'not-an-email',
                'password' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);
    }
}
