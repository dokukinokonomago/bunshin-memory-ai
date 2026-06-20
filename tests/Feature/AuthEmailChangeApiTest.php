<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VerifyEmailChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthEmailChangeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_and_verify_email_change(): void
    {
        Notification::fake();

        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'old@example.test',
            'email_verified_at' => now()->subDay(),
        ]);

        $this
            ->withApiToken($user, 'email-change-device')
            ->putJson('/api/v1/auth/email', [
                'email' => '  New@Example.TEST  ',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Email change verification link has been sent.')
            ->assertJsonPath('data.user.email', 'old@example.test')
            ->assertJsonPath('data.user.pending_email', 'new@example.test')
            ->assertJsonPath('data.user.is_email_verified', true);

        $user->refresh();

        $this->assertSame('old@example.test', $user->email);
        $this->assertSame('new@example.test', $user->pending_email);
        $this->assertNotNull($user->pending_email_requested_at);

        Notification::assertSentOnDemand(
            VerifyEmailChangeNotification::class,
            function (VerifyEmailChangeNotification $notification, array $channels, object $notifiable) use ($user): bool {
                $this->assertSame(['mail'], $channels);
                $this->assertSame('new@example.test', $notifiable->routes['mail']);
                $this->assertStringContainsString('/api/v1/auth/email/change/verify/'.$user->id.'/', $notification->verificationUrl());

                return $notification->email() === 'new@example.test';
            },
        );

        $this
            ->getJson($this->emailChangeVerificationUrlFor($user))
            ->assertOk()
            ->assertJsonPath('message', 'Email has been changed.')
            ->assertJsonPath('data.user.email', 'new@example.test')
            ->assertJsonPath('data.user.pending_email', null)
            ->assertJsonPath('data.user.pending_email_requested_at', null)
            ->assertJsonPath('data.user.is_email_verified', true);

        $user->refresh();

        $this->assertSame('new@example.test', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_requested_at);
        $this->assertTrue($user->hasVerifiedEmail());

        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event_type' => SecurityEvent::TYPE_EMAIL_CHANGE_REQUEST,
            'outcome' => SecurityEvent::OUTCOME_REQUESTED,
            'subject_email' => 'new@example.test',
        ]);
        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event_type' => SecurityEvent::TYPE_EMAIL_CHANGE_COMPLETE,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'new@example.test',
        ]);
    }

    public function test_email_change_rejects_current_email_and_unavailable_emails(): void
    {
        Notification::fake();

        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'current@example.test',
            'pending_email' => null,
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'taken@example.test',
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'other@example.test',
            'pending_email' => 'pending@example.test',
        ]);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/auth/email', [
                'email' => ' CURRENT@example.test ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/auth/email', [
                'email' => 'taken@example.test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/auth/email', [
                'email' => 'pending@example.test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $user->refresh();

        $this->assertSame('current@example.test', $user->email);
        $this->assertNull($user->pending_email);
        Notification::assertNothingSent();
    }

    public function test_email_change_verification_rejects_invalid_or_unavailable_links(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'old@example.test',
            'pending_email' => 'new@example.test',
            'pending_email_requested_at' => now(),
        ]);

        $this
            ->getJson(URL::temporarySignedRoute(
                'api.v1.auth.email.change.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->id,
                    'hash' => 'invalid-hash',
                ],
            ))
            ->assertForbidden()
            ->assertJsonPath('message', 'The email change verification link is invalid or expired.');

        $user->refresh();

        $this->assertSame('old@example.test', $user->email);
        $this->assertSame('new@example.test', $user->pending_email);

        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event_type' => SecurityEvent::TYPE_EMAIL_CHANGE_COMPLETE,
            'outcome' => SecurityEvent::OUTCOME_FAILURE,
            'subject_email' => 'new@example.test',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'new@example.test',
        ]);

        $this
            ->getJson($this->emailChangeVerificationUrlFor($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $user->refresh();

        $this->assertSame('old@example.test', $user->email);
        $this->assertSame('new@example.test', $user->pending_email);
    }

    public function test_email_change_requires_valid_bearer_token_and_tenant_context(): void
    {
        Notification::fake();

        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan@example.test',
        ]);
        $orphanToken = $orphanUser->createApiToken('orphan-device');

        $payload = [
            'email' => 'new-orphan@example.test',
        ];

        $this
            ->putJson('/api/v1/auth/email', $payload)
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->putJson('/api/v1/auth/email', $payload)
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->putJson('/api/v1/auth/email', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $orphanUser->refresh();

        $this->assertSame('orphan@example.test', $orphanUser->email);
        $this->assertNull($orphanUser->pending_email);
        Notification::assertNothingSent();
    }

    private function emailChangeVerificationUrlFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'api.v1.auth.email.change.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1((string) $user->pending_email),
            ],
        );
    }
}
