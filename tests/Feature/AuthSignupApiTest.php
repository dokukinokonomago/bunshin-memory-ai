<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthSignupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_owner_can_create_tenant_and_login_with_issued_bearer_token(): void
    {
        Notification::fake();
        config(['bunshin.onboarding.invite_token' => 'invite-secret']);

        $response = $this
            ->postJson('/api/v1/auth/signup', [
                'invite_token' => ' invite-secret ',
                'tenant_name' => ' 分身AI ',
                'tenant_slug' => ' BUNSHIN-AI ',
                'name' => ' Owner User ',
                'email' => ' OWNER@example.test ',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ])
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', null)
            ->assertJsonPath('data.user.name', 'Owner User')
            ->assertJsonPath('data.user.email', 'owner@example.test')
            ->assertJsonPath('data.user.role', User::ROLE_OWNER)
            ->assertJsonPath('data.user.account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->assertJsonPath('data.user.is_email_verified', false)
            ->assertJsonPath('data.user.email_verified_at', null)
            ->assertJsonPath('data.tenant.name', '分身AI')
            ->assertJsonPath('data.tenant.slug', 'bunshin-ai')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                ],
            ]);

        $tenant = Tenant::query()->sole();
        $user = User::query()->sole();
        $plainTextToken = $response->json('data.access_token');
        [$id, $token] = explode('|', $plainTextToken, 2);
        $storedToken = PersonalAccessToken::query()->findOrFail((int) $id);

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame(User::ROLE_OWNER, $user->role);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $user->account_status);
        $this->assertSame($user->id, $response->json('data.user.id'));
        $this->assertSame($tenant->id, $response->json('data.tenant.id'));
        $this->assertSame($user->id, $storedToken->tokenable_id);
        $this->assertSame(User::class, $storedToken->tokenable_type);
        $this->assertSame('signup', $storedToken->name);
        $this->assertTrue(hash_equals($storedToken->token, hash('sha256', $token)));
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'owner@example.test')
            ->assertJsonPath('data.user.role', User::ROLE_OWNER)
            ->assertJsonPath('data.user.account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->assertJsonPath('data.tenant.slug', 'bunshin-ai')
            ->assertJsonPath('data.token.name', 'signup');

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'owner@example.test',
                'password' => 'strong-password',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', User::ROLE_OWNER)
            ->assertJsonPath('data.user.account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->assertJsonPath('data.tenant.id', $tenant->id);
    }

    public function test_signup_rejects_invalid_invite_token_without_creating_records(): void
    {
        config(['bunshin.onboarding.invite_token' => 'invite-secret']);

        $this
            ->postJson('/api/v1/auth/signup', [
                'invite_token' => 'wrong-secret',
                'tenant_name' => '分身AI',
                'tenant_slug' => 'bunshin-ai',
                'name' => 'Owner User',
                'email' => 'owner@example.test',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid onboarding invite token.');

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_signup_is_closed_when_invite_token_is_not_configured(): void
    {
        config(['bunshin.onboarding.invite_token' => null]);

        $this
            ->postJson('/api/v1/auth/signup', [
                'invite_token' => 'invite-secret',
                'tenant_name' => '分身AI',
                'tenant_slug' => 'bunshin-ai',
                'name' => 'Owner User',
                'email' => 'owner@example.test',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid onboarding invite token.');

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_validates_payload_shape(): void
    {
        config(['bunshin.onboarding.invite_token' => 'invite-secret']);

        $this
            ->postJson('/api/v1/auth/signup', [
                'invite_token' => '',
                'tenant_name' => '',
                'tenant_slug' => 'Bad Slug',
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'invite_token',
                'tenant_name',
                'tenant_slug',
                'name',
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_rejects_duplicate_tenant_slug_and_email(): void
    {
        config(['bunshin.onboarding.invite_token' => 'invite-secret']);

        $tenant = Tenant::query()->create([
            'name' => 'Existing Tenant',
            'slug' => 'existing',
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);

        $this
            ->postJson('/api/v1/auth/signup', [
                'invite_token' => 'invite-secret',
                'tenant_name' => '分身AI',
                'tenant_slug' => 'EXISTING',
                'name' => 'Owner User',
                'email' => ' OWNER@example.test ',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_slug',
                'email',
            ]);

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
