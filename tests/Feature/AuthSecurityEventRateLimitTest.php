<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthSecurityEventRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_security_events_are_recorded_for_success_and_failure(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin@example.test',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => ' ADMIN@example.test ',
                'password' => 'wrong-password',
            ])
            ->assertUnauthorized();

        $failure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_LOGIN)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->sole();

        $this->assertSame($tenant->id, $failure->tenant_id);
        $this->assertSame($user->id, $failure->user_id);
        $this->assertSame('admin@example.test', $failure->subject_email);
        $this->assertSame('invalid_credentials', $failure->metadata['reason']);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'admin@example.test',
                'password' => 'password',
            ])
            ->assertCreated();

        $success = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_LOGIN)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $success->tenant_id);
        $this->assertSame($user->id, $success->user_id);
        $this->assertSame('admin@example.test', $success->subject_email);
        $this->assertNull($success->metadata);
    }

    public function test_signup_password_reset_and_invitation_accept_security_events_are_recorded(): void
    {
        Notification::fake();
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
            ->assertForbidden();

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
            ->assertCreated();

        $tenant = Tenant::query()->where('slug', 'bunshin-ai')->sole();
        $owner = User::query()->where('email', 'owner@example.test')->sole();

        $this->assertDatabaseHas('security_events', [
            'event_type' => SecurityEvent::TYPE_SIGNUP,
            'outcome' => SecurityEvent::OUTCOME_FAILURE,
            'subject_email' => 'owner@example.test',
        ]);
        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'event_type' => SecurityEvent::TYPE_SIGNUP,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'owner@example.test',
        ]);

        $this
            ->postJson('/api/v1/auth/password/forgot', [
                'email' => ' OWNER@example.test ',
            ])
            ->assertAccepted();

        Notification::assertSentTo($owner, ResetPasswordNotification::class);

        $resetToken = Password::broker()->createToken($owner);

        $this
            ->postJson('/api/v1/auth/password/reset', [
                'email' => 'owner@example.test',
                'token' => $resetToken,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'event_type' => SecurityEvent::TYPE_PASSWORD_RESET_REQUEST,
            'outcome' => SecurityEvent::OUTCOME_REQUESTED,
            'subject_email' => 'owner@example.test',
        ]);
        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'event_type' => SecurityEvent::TYPE_PASSWORD_RESET_COMPLETE,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'owner@example.test',
        ]);

        $plainInvitationToken = 'accept-secret';
        $invitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'member@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', $plainInvitationToken),
            'expires_at' => now()->addDays(7),
        ]);

        $this
            ->postJson('/api/v1/tenant/invitations/accept', [
                'token' => $invitation->id.'|'.$plainInvitationToken,
                'name' => 'Member User',
                'password' => 'member-password',
                'password_confirmation' => 'member-password',
            ])
            ->assertCreated();

        $member = User::query()->where('email', 'member@example.test')->sole();

        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'event_type' => SecurityEvent::TYPE_TENANT_INVITATION_ACCEPT,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'member@example.test',
        ]);
    }

    public function test_auth_write_endpoints_are_rate_limited(): void
    {
        Notification::fake();

        config([
            'bunshin.onboarding.invite_token' => 'invite-secret',
            'bunshin.security.rate_limits.login.per_minute' => 1,
            'bunshin.security.rate_limits.signup.per_minute' => 1,
            'bunshin.security.rate_limits.password_forgot.per_minute' => 1,
            'bunshin.security.rate_limits.password_reset.per_minute' => 1,
            'bunshin.security.rate_limits.invitation_accept.per_minute' => 1,
            'bunshin.security.rate_limits.email_verification.per_minute' => 1,
            'bunshin.security.rate_limits.email_change.per_minute' => 1,
            'bunshin.security.rate_limits.secret_unlock_password_recovery_request.per_minute' => 1,
            'bunshin.security.rate_limits.secret_unlock_password_recovery_complete.per_minute' => 1,
            'bunshin.security.rate_limits.tenant_security_action.per_minute' => 1,
        ]);

        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'limited-login@example.test',
        ]);

        $loginPayload = [
            'email' => 'limited-login@example.test',
            'password' => 'wrong-password',
        ];

        $this->postJson('/api/v1/auth/login', $loginPayload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $loginPayload)->assertStatus(429);

        $signupPayload = [
            'invite_token' => 'wrong-secret',
            'tenant_name' => 'Rate Limited',
            'tenant_slug' => 'rate-limited',
            'name' => 'Owner User',
            'email' => 'limited-signup@example.test',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ];

        $this->postJson('/api/v1/auth/signup', $signupPayload)->assertForbidden();
        $this->postJson('/api/v1/auth/signup', $signupPayload)->assertStatus(429);

        $forgotPayload = [
            'email' => 'limited-forgot@example.test',
        ];

        $this->postJson('/api/v1/auth/password/forgot', $forgotPayload)->assertAccepted();
        $this->postJson('/api/v1/auth/password/forgot', $forgotPayload)->assertStatus(429);

        $resetPayload = [
            'email' => 'limited-reset@example.test',
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $this->postJson('/api/v1/auth/password/reset', $resetPayload)->assertUnprocessable();
        $this->postJson('/api/v1/auth/password/reset', $resetPayload)->assertStatus(429);

        $acceptPayload = [
            'token' => '1|invalid-token',
            'name' => 'Member User',
            'password' => 'member-password',
            'password_confirmation' => 'member-password',
        ];

        $this->postJson('/api/v1/tenant/invitations/accept', $acceptPayload)->assertUnprocessable();
        $this->postJson('/api/v1/tenant/invitations/accept', $acceptPayload)->assertStatus(429);

        $verificationUser = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email' => 'limited-verification@example.test',
        ]);
        $verificationToken = $verificationUser->createApiToken('verification-rate-limit')->plainTextToken;
        RateLimiter::clear('email-verification:'.$verificationUser->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$verificationToken)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertAccepted();

        $this
            ->withHeader('Authorization', 'Bearer '.$verificationToken)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(429);

        $emailChangeUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'limited-email-change@example.test',
        ]);
        $emailChangeToken = $emailChangeUser->createApiToken('email-change-rate-limit')->plainTextToken;
        RateLimiter::clear('email-change:'.$emailChangeUser->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$emailChangeToken)
            ->putJson('/api/v1/auth/email', [
                'email' => 'limited-email-change-new@example.test',
            ])
            ->assertAccepted();

        $this
            ->withHeader('Authorization', 'Bearer '.$emailChangeToken)
            ->putJson('/api/v1/auth/email', [
                'email' => 'limited-email-change-new@example.test',
            ])
            ->assertStatus(429);

        $recoveryUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'limited-recovery@example.test',
        ]);
        $recoveryToken = $recoveryUser->createApiToken('secret-unlock-recovery-rate-limit')->plainTextToken;
        RateLimiter::clear('secret-unlock-password-recovery-request:'.$recoveryUser->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$recoveryToken)
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'password',
            ])
            ->assertAccepted();

        $this
            ->withHeader('Authorization', 'Bearer '.$recoveryToken)
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'password',
            ])
            ->assertStatus(429);

        $recoveryCompleteUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'limited-recovery-complete@example.test',
        ]);
        $recoveryCompleteToken = $recoveryCompleteUser->createApiToken('secret-unlock-recovery-complete-rate-limit')->plainTextToken;
        $recoveryCompleteUrl = URL::temporarySignedRoute(
            'api.v1.secret-unlock-password.recovery.complete',
            now()->addMinutes(30),
            [
                'id' => $recoveryCompleteUser->id,
                'hash' => sha1((string) $recoveryCompleteUser->email),
            ],
        );
        RateLimiter::clear('secret-unlock-password-recovery-complete:'.$recoveryCompleteUser->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$recoveryCompleteToken)
            ->putJson($recoveryCompleteUrl, [
                'account_password' => 'wrong-password',
                'password' => 'recovered-secret-password',
                'password_confirmation' => 'recovered-secret-password',
            ])
            ->assertUnprocessable();

        $this
            ->withHeader('Authorization', 'Bearer '.$recoveryCompleteToken)
            ->putJson($recoveryCompleteUrl, [
                'account_password' => 'wrong-password',
                'password' => 'recovered-secret-password',
                'password_confirmation' => 'recovered-secret-password',
            ])
            ->assertStatus(429);

        $manager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'limited-manager@example.test',
        ]);
        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_MEMBER,
            'email' => 'limited-member@example.test',
        ]);
        $managerToken = $manager->createApiToken('tenant-security-action-rate-limit')->plainTextToken;
        RateLimiter::clear('tenant-security-action:'.$tenant->id.':'.$manager->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson('/api/v1/tenant/members/'.$member->id.'/secret-unlock-password/force-rotation')
            ->assertOk();

        $this
            ->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson('/api/v1/tenant/members/'.$member->id.'/secret-unlock-password/force-rotation')
            ->assertStatus(429);

        RateLimiter::clear('tenant-security-action:'.$tenant->id.':'.$manager->id);

        $statusManager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'limited-status-manager@example.test',
        ]);
        $statusMember = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_MEMBER,
            'email' => 'limited-status-member@example.test',
        ]);
        $statusManagerToken = $statusManager->createApiToken('tenant-security-action-status-rate-limit')->plainTextToken;
        RateLimiter::clear('tenant-security-action:'.$tenant->id.':'.$statusManager->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$statusManagerToken)
            ->patchJson('/api/v1/tenant/members/'.$statusMember->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_DISABLED,
            ])
            ->assertOk();

        $this
            ->withHeader('Authorization', 'Bearer '.$statusManagerToken)
            ->patchJson('/api/v1/tenant/members/'.$statusMember->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            ])
            ->assertStatus(429);

        RateLimiter::clear('tenant-security-action:'.$tenant->id.':'.$statusManager->id);
    }
}
