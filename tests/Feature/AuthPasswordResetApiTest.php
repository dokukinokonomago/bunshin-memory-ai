<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthPasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();

        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'reset@example.test',
        ]);

        $this
            ->postJson('/api/v1/auth/password/forgot', [
                'email' => ' RESET@example.test ',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'If an account exists for this email, a password reset link has been sent.');

        $plainTextResetToken = null;

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$plainTextResetToken): bool {
                $plainTextResetToken = $notification->token;

                return $notification->token !== '';
            },
        );

        $this->assertNotNull($plainTextResetToken);
        $this->assertTrue(Password::broker()->tokenExists($user, $plainTextResetToken));
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@example.test',
        ]);
    }

    public function test_password_reset_request_does_not_reveal_missing_email(): void
    {
        Notification::fake();

        $this
            ->postJson('/api/v1/auth/password/forgot', [
                'email' => 'missing@example.test',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'If an account exists for this email, a password reset link has been sent.');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'missing@example.test',
        ]);
    }

    public function test_user_can_reset_password_and_existing_tokens_are_revoked(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'reset@example.test',
        ]);
        $oldToken = $user->createApiToken('old-device');
        $resetToken = Password::broker()->createToken($user);

        $this
            ->postJson('/api/v1/auth/password/reset', [
                'email' => ' RESET@example.test ',
                'token' => $resetToken,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertNoContent();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', (string) $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset@example.test',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldToken->accessToken->id,
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$oldToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'reset@example.test',
                'password' => 'new-password',
            ])
            ->assertCreated();

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_reset_password_rejects_invalid_token_without_changing_password(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'reset@example.test',
        ]);
        $accessToken = $user->createApiToken('current-device');
        Password::broker()->createToken($user);

        $this
            ->postJson('/api/v1/auth/password/reset', [
                'email' => 'reset@example.test',
                'token' => 'invalid-token',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $user->refresh();

        $this->assertTrue(Hash::check('password', (string) $user->password));
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@example.test',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $accessToken->accessToken->id,
        ]);
    }

    public function test_password_reset_validates_payload_shape(): void
    {
        $this
            ->postJson('/api/v1/auth/password/forgot', [
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this
            ->postJson('/api/v1/auth/password/reset', [
                'email' => 'not-an-email',
                'token' => '',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'token',
                'password',
            ]);
    }
}
