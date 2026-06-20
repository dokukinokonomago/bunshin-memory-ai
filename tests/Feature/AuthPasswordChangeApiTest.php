<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthPasswordChangeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password_and_existing_tokens_are_revoked(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'change@example.test',
        ]);
        $currentToken = $user->createApiToken('current-device');
        $otherToken = $user->createApiToken('other-device');
        RateLimiter::clear('password-change:'.$user->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertNoContent();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', (string) $user->password));
        $this->assertTrue($user->checkSecretUnlockPassword('secret-password'));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event_type' => SecurityEvent::TYPE_PASSWORD_CHANGE,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'change@example.test',
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$otherToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'change@example.test',
                'password' => 'password',
            ])
            ->assertUnauthorized();

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'change@example.test',
                'password' => 'new-password',
            ])
            ->assertCreated();

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_password_change_rejects_wrong_current_password_without_changing_or_revoking_tokens(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'wrong-current@example.test',
        ]);
        $currentToken = $user->createApiToken('current-device');
        RateLimiter::clear('password-change:'.$user->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();

        $this->assertTrue(Hash::check('password', (string) $user->password));
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_PASSWORD_CHANGE)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('wrong-current@example.test', $event->subject_email);
        $this->assertSame('invalid_current_password', $event->metadata['reason']);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_password_change_validates_payload_shape(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $currentToken = $user->createApiToken('current-device');
        RateLimiter::clear('password-change:'.$user->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => '',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
                'password',
            ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_requires_valid_bearer_token_and_tenant_context(): void
    {
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan@example.test',
        ]);
        $orphanToken = $orphanUser->createApiToken('orphan-device');
        RateLimiter::clear('password-change:'.$orphanUser->id);

        $payload = [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $this
            ->putJson('/api/v1/auth/password', $payload)
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->putJson('/api/v1/auth/password', $payload)
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->putJson('/api/v1/auth/password', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');
    }

    public function test_password_change_is_rate_limited_per_authenticated_user(): void
    {
        config(['bunshin.security.rate_limits.password_change.per_minute' => 1]);

        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $currentToken = $user->createApiToken('current-device');
        RateLimiter::clear('password-change:'.$user->id);

        $payload = [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->putJson('/api/v1/auth/password', $payload)
            ->assertUnprocessable();

        $this
            ->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->putJson('/api/v1/auth/password', $payload)
            ->assertStatus(429);
    }
}
