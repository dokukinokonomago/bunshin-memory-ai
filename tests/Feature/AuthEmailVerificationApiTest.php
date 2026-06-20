<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthEmailVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_email_verification_link_marks_user_verified_and_records_event(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);

        $this
            ->getJson($this->verificationUrlFor($user))
            ->assertOk()
            ->assertJsonPath('message', 'Email has been verified.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'owner@example.test')
            ->assertJsonPath('data.user.is_email_verified', true);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event_type' => SecurityEvent::TYPE_EMAIL_VERIFICATION_COMPLETE,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'owner@example.test',
        ]);
    }

    public function test_email_verification_rejects_invalid_hash_without_marking_user_verified(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);

        $this
            ->getJson(URL::temporarySignedRoute(
                'api.v1.auth.email.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->id,
                    'hash' => 'invalid-hash',
                ],
            ))
            ->assertForbidden()
            ->assertJsonPath('message', 'The email verification link is invalid or expired.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_EMAIL_VERIFICATION_COMPLETE)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('owner@example.test', $event->subject_email);
        $this->assertSame('invalid_hash', $event->metadata['reason']);
    }

    public function test_authenticated_user_can_request_verification_email_resend(): void
    {
        Notification::fake();

        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertAccepted()
            ->assertJsonPath('message', 'Email verification link has been sent.');

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function (VerifyEmailNotification $notification) use ($user): bool {
                $actionUrl = $notification->toMail($user)->actionUrl;

                $this->assertStringContainsString('/api/v1/auth/email/verify/'.$user->id.'/', $actionUrl);

                return true;
            },
        );

        $this->assertDatabaseHas('security_events', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event_type' => SecurityEvent::TYPE_EMAIL_VERIFICATION_REQUEST,
            'outcome' => SecurityEvent::OUTCOME_REQUESTED,
            'subject_email' => 'owner@example.test',
        ]);
    }

    public function test_verification_resend_requires_authentication_and_tenant_context(): void
    {
        Notification::fake();

        $this
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertUnauthorized();

        $orphan = User::factory()->unverified()->create([
            'tenant_id' => null,
            'email' => 'orphan@example.test',
        ]);

        $this
            ->withApiToken($orphan)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        Notification::assertNothingSent();
    }

    private function verificationUrlFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'api.v1.auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }
}
