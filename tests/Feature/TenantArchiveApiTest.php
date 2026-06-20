<?php

namespace Tests\Feature;

use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TenantArchiveApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_owner_can_archive_tenant_and_revoke_tenant_credentials_without_hard_deleting_data(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'archive-owner@example.test',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
            'email' => 'archive-admin@example.test',
        ]);
        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_MEMBER,
            'email' => 'archive-member@example.test',
        ]);
        $otherTenantUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'other-archive@example.test',
        ]);

        $ownerToken = $owner->createApiToken('tenant-archive-owner');
        $adminToken = $admin->createApiToken('tenant-archive-admin');
        $memberToken = $member->createApiToken('tenant-archive-member');
        $otherTenantToken = $otherTenantUser->createApiToken('tenant-archive-other');

        $ownerUnlockToken = $this->createSecretUnlockToken($owner, 'owner-unlock-token');
        $adminUnlockToken = $this->createSecretUnlockToken($admin, 'admin-unlock-token');
        $otherTenantUnlockToken = $this->createSecretUnlockToken($otherTenantUser, 'other-unlock-token');

        $pendingInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'pending-archive@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'pending-token'),
            'expires_at' => now()->addDays(7),
        ]);
        $acceptedInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'accepted_user_id' => $member->id,
            'email' => 'accepted-archive@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'accepted-token'),
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);
        $expiredInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'expired-archive@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'expired-token'),
            'expires_at' => now()->subDay(),
        ]);
        $otherTenantInvitation = $otherTenant->memberInvitations()->create([
            'invited_by_user_id' => $otherTenantUser->id,
            'email' => 'other-pending-archive@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'other-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this->clearTenantLifecycleRateLimit($tenant, $owner);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE bunshin-ai',
                'reason' => '  Service no longer needed  ',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Tenant archive has been scheduled.')
            ->assertJsonPath('data.tenant_public_id', $tenant->public_id);

        $tenant->refresh();
        $this->assertTrue($tenant->isArchived());
        $this->assertSame($owner->id, $tenant->archived_by_user_id);
        $this->assertSame('Service no longer needed', $tenant->archive_reason);
        $this->assertTrue($tenant->archived_at?->equalTo($tenant->deletion_requested_at));
        $this->assertTrue($tenant->scheduled_deletion_at?->equalTo($tenant->archived_at?->copy()->addDays(30)));
        $this->assertNull($tenant->purged_at);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_CANCELED, $tenant->subscription_status);
        $this->assertTrue($tenant->subscription_ends_at?->equalTo($tenant->archived_at));

        $this->assertSame($tenant->archived_at?->toAtomString(), $response->json('data.archived_at'));
        $this->assertSame($tenant->scheduled_deletion_at?->toAtomString(), $response->json('data.scheduled_deletion_at'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $ownerToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $memberToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherTenantToken->accessToken->id]);

        $this->assertDatabaseMissing('secret_unlock_tokens', ['id' => $ownerUnlockToken->id]);
        $this->assertDatabaseMissing('secret_unlock_tokens', ['id' => $adminUnlockToken->id]);
        $this->assertDatabaseHas('secret_unlock_tokens', ['id' => $otherTenantUnlockToken->id]);

        $this->assertNotNull($pendingInvitation->refresh()->revoked_at);
        $this->assertNull($acceptedInvitation->refresh()->revoked_at);
        $this->assertNull($expiredInvitation->refresh()->revoked_at);
        $this->assertNull($otherTenantInvitation->refresh()->revoked_at);

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('tenant_member_invitations', ['id' => $acceptedInvitation->id]);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_TENANT_ARCHIVE)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($owner->id, $event->user_id);
        $this->assertSame('archive-owner@example.test', $event->subject_email);
        $this->assertSame('Service no longer needed', $event->metadata['reason']);
        $this->assertSame(30, $event->metadata['scheduled_deletion_days']);
        $this->assertSame(3, $event->metadata['tokens_revoked']);
        $this->assertSame(2, $event->metadata['secret_unlock_tokens_revoked']);
        $this->assertSame(1, $event->metadata['pending_invitations_revoked']);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $event->metadata['previous_subscription_status']);
        $this->assertStringNotContainsString('password', (string) json_encode($event->metadata));

        $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_tenant_archive_cancels_linked_provider_subscription_after_local_archive(): void
    {
        $this->enableBilling();

        Http::fake([
            'https://api.stripe.test/v1/subscriptions/sub_archive_123' => Http::response([
                'id' => 'sub_archive_123',
                'object' => 'subscription',
                'customer' => 'cus_archive_123',
                'status' => 'canceled',
            ], 200),
        ]);

        $tenant = $this->createTenant('分身AI', 'bunshin-ai', [
            'plan_key' => Tenant::PLAN_PRO,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_archive_123',
            'billing_subscription_id' => 'sub_archive_123',
            'billing_price_id' => 'price_pro_monthly',
            'billing_cancel_at_period_end' => true,
        ]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'billing-archive-owner@example.test',
        ]);
        $ownerToken = $owner->createApiToken('tenant-archive-billing-owner');
        $this->clearTenantLifecycleRateLimit($tenant, $owner);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE bunshin-ai',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.billing_provider_cancellation.status', 'succeeded')
            ->assertJsonPath('data.billing_provider_cancellation.provider', 'stripe');

        $tenant->refresh();

        $this->assertTrue($tenant->isArchived());
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_CANCELED, $tenant->subscription_status);
        $this->assertTrue($tenant->subscription_ends_at?->equalTo($tenant->archived_at));
        $this->assertFalse($tenant->billing_cancel_at_period_end);
        $this->assertNotNull($tenant->billing_last_synced_at);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.stripe.test/v1/subscriptions/sub_archive_123'
            && in_array($request['invoice_now'] ?? null, [false, 0, '0'], true)
            && in_array($request['prorate'] ?? null, [false, 0, '0'], true));

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_BILLING_SUBSCRIPTION_CANCEL_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($owner->id, $event->user_id);
        $this->assertNull($event->subject_email);
        $this->assertSame('stripe', $event->metadata['provider']);
        $this->assertSame('immediate_no_proration_no_refund', $event->metadata['archive_cancellation_policy']);
        $this->assertSame('succeeded', $event->metadata['result']);
        $this->assertSame('provider_cancelled', $event->metadata['reason']);
        $this->assertSame(Tenant::PLAN_PRO, $event->metadata['previous_plan_key']);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $event->metadata['previous_subscription_status']);
        $this->assertContains('billing_cancel_at_period_end', $event->metadata['changed_fields']);
        $this->assertContains('billing_last_synced_at', $event->metadata['changed_fields']);

        $metadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('cus_archive_123', $metadata);
        $this->assertStringNotContainsString('sub_archive_123', $metadata);
        $this->assertStringNotContainsString('price_pro_monthly', $metadata);
        $this->assertStringNotContainsString('sk_test_123', $metadata);
        $this->assertStringNotContainsString('cus_archive_123', $response->getContent());
        $this->assertStringNotContainsString('sub_archive_123', $response->getContent());
        $this->assertStringNotContainsString('price_pro_monthly', $response->getContent());
    }

    public function test_tenant_archive_skips_provider_cancellation_when_not_safely_actionable(): void
    {
        Http::fake();

        $disabledTenant = $this->createTenant('Billing Disabled', 'billing-disabled', [
            'billing_provider' => 'stripe',
            'billing_subscription_id' => 'sub_disabled_123',
        ]);
        $disabledOwner = User::factory()->create([
            'tenant_id' => $disabledTenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $disabledToken = $disabledOwner->createApiToken('tenant-archive-disabled-billing');
        $this->clearTenantLifecycleRateLimit($disabledTenant, $disabledOwner);

        $this
            ->withHeader('Authorization', 'Bearer '.$disabledToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE billing-disabled',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.billing_provider_cancellation.status', 'skipped')
            ->assertJsonPath('data.billing_provider_cancellation.reason', 'billing_disabled');

        $this->enableBilling();

        $missingSubscriptionTenant = $this->createTenant('Missing Subscription', 'missing-subscription', [
            'billing_provider' => 'stripe',
        ]);
        $missingSubscriptionOwner = User::factory()->create([
            'tenant_id' => $missingSubscriptionTenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $missingSubscriptionToken = $missingSubscriptionOwner->createApiToken('tenant-archive-missing-subscription');
        $this->clearTenantLifecycleRateLimit($missingSubscriptionTenant, $missingSubscriptionOwner);

        $this
            ->withHeader('Authorization', 'Bearer '.$missingSubscriptionToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE missing-subscription',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.billing_provider_cancellation.status', 'skipped')
            ->assertJsonPath('data.billing_provider_cancellation.provider', 'stripe')
            ->assertJsonPath('data.billing_provider_cancellation.reason', 'missing_billing_subscription');

        $providerMismatchTenant = $this->createTenant('Provider Mismatch', 'provider-mismatch', [
            'billing_provider' => 'other-provider',
            'billing_subscription_id' => 'sub_other_123',
        ]);
        $providerMismatchOwner = User::factory()->create([
            'tenant_id' => $providerMismatchTenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $providerMismatchToken = $providerMismatchOwner->createApiToken('tenant-archive-provider-mismatch');
        $this->clearTenantLifecycleRateLimit($providerMismatchTenant, $providerMismatchOwner);

        $this
            ->withHeader('Authorization', 'Bearer '.$providerMismatchToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE provider-mismatch',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.billing_provider_cancellation.status', 'skipped')
            ->assertJsonPath('data.billing_provider_cancellation.provider', 'stripe')
            ->assertJsonPath('data.billing_provider_cancellation.reason', 'provider_mismatch');

        Http::assertNothingSent();

        $reasons = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_BILLING_SUBSCRIPTION_CANCEL_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_SKIPPED)
            ->pluck('metadata')
            ->map(static fn (array $metadata): string => $metadata['reason'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'billing_disabled',
            'missing_billing_subscription',
            'provider_mismatch',
        ], $reasons);
    }

    public function test_tenant_archive_keeps_local_archive_when_provider_cancellation_fails(): void
    {
        $this->enableBilling();

        Http::fake([
            'https://api.stripe.test/v1/subscriptions/sub_failure_123' => Http::response([
                'error' => [
                    'message' => 'Provider raw failure for sub_failure_123 and cus_failure_123.',
                ],
            ], 500),
        ]);

        $tenant = $this->createTenant('分身AI', 'bunshin-ai', [
            'plan_key' => Tenant::PLAN_PRO,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_failure_123',
            'billing_subscription_id' => 'sub_failure_123',
            'billing_price_id' => 'price_pro_monthly',
            'billing_cancel_at_period_end' => true,
        ]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'billing-failure-owner@example.test',
        ]);
        $ownerToken = $owner->createApiToken('tenant-archive-billing-failure-owner');
        $this->clearTenantLifecycleRateLimit($tenant, $owner);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE bunshin-ai',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.billing_provider_cancellation.status', 'requires_operator_review')
            ->assertJsonPath('data.billing_provider_cancellation.provider', 'stripe')
            ->assertJsonPath('data.billing_provider_cancellation.reason', 'provider_request_failed');

        $tenant->refresh();

        $this->assertTrue($tenant->isArchived());
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_CANCELED, $tenant->subscription_status);
        $this->assertTrue($tenant->subscription_ends_at?->equalTo($tenant->archived_at));
        $this->assertTrue($tenant->billing_cancel_at_period_end);
        $this->assertNull($tenant->billing_last_synced_at);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_BILLING_SUBSCRIPTION_CANCEL_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame('stripe', $event->metadata['provider']);
        $this->assertSame('requires_operator_review', $event->metadata['result']);
        $this->assertSame('provider_request_failed', $event->metadata['reason']);
        $this->assertSame(Tenant::PLAN_PRO, $event->metadata['previous_plan_key']);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $event->metadata['previous_subscription_status']);
        $this->assertArrayNotHasKey('changed_fields', $event->metadata);

        $metadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('cus_failure_123', $metadata);
        $this->assertStringNotContainsString('sub_failure_123', $metadata);
        $this->assertStringNotContainsString('price_pro_monthly', $metadata);
        $this->assertStringNotContainsString('Provider raw failure', $metadata);
        $this->assertStringNotContainsString('cus_failure_123', $response->getContent());
        $this->assertStringNotContainsString('sub_failure_123', $response->getContent());
        $this->assertStringNotContainsString('Provider raw failure', $response->getContent());
    }

    public function test_tenant_archive_rejects_invalid_password_confirmation_non_owner_and_tenant_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'invalid-archive-owner@example.test',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
            'email' => 'invalid-archive-admin@example.test',
        ]);
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan-archive@example.test',
        ]);

        $ownerToken = $owner->createApiToken('tenant-archive-owner');
        $adminToken = $admin->createApiToken('tenant-archive-admin');
        $orphanToken = $orphanUser->createApiToken('tenant-archive-orphan');
        $this->clearTenantLifecycleRateLimit($tenant, $owner);
        $this->clearTenantLifecycleRateLimit($tenant, $admin);
        $this->clearTenantLifecycleRateLimit(null, $orphanUser);

        $this
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE bunshin-ai',
            ])
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$orphanToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE bunshin-ai',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => '',
                'reason' => str_repeat('a', 501),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmation', 'reason']);

        $this
            ->withHeader('Authorization', 'Bearer '.$adminToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'ARCHIVE bunshin-ai',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $ownerRequiredFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_TENANT_ARCHIVE)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'owner_required')
            ->sole();

        $this->assertSame($tenant->id, $ownerRequiredFailure->tenant_id);
        $this->assertSame($admin->id, $ownerRequiredFailure->user_id);
        $this->assertSame(User::ROLE_ADMIN, $ownerRequiredFailure->metadata['role']);

        $wrongPasswordResponse = $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'wrong-password',
                'confirmation' => 'ARCHIVE bunshin-ai',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertStringNotContainsString('wrong-password', $wrongPasswordResponse->getContent());

        $passwordFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_TENANT_ARCHIVE)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'invalid_current_password')
            ->sole();

        $this->assertSame($tenant->id, $passwordFailure->tenant_id);
        $this->assertSame($owner->id, $passwordFailure->user_id);
        $this->assertSame('invalid-archive-owner@example.test', $passwordFailure->subject_email);

        $this
            ->withHeader('Authorization', 'Bearer '.$ownerToken->plainTextToken)
            ->postJson('/api/v1/tenant/archive', [
                'current_password' => 'password',
                'confirmation' => 'archive bunshin-ai',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmation']);

        $confirmationFailure = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_TENANT_ARCHIVE)
            ->where('outcome', SecurityEvent::OUTCOME_FAILURE)
            ->where('metadata->reason', 'invalid_confirmation')
            ->sole();

        $this->assertSame($tenant->id, $confirmationFailure->tenant_id);
        $this->assertSame($owner->id, $confirmationFailure->user_id);
        $this->assertSame('ARCHIVE bunshin-ai', $confirmationFailure->metadata['expected_confirmation']);

        $this->assertFalse($tenant->refresh()->isArchived());
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $ownerToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
    }

    public function test_tenant_archive_is_rate_limited_per_authenticated_tenant_user(): void
    {
        config(['bunshin.security.rate_limits.tenant_lifecycle.per_minute' => 1]);

        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $token = $owner->createApiToken('tenant-archive-rate-limit');
        $this->clearTenantLifecycleRateLimit($tenant, $owner);

        $payload = [
            'current_password' => 'wrong-password',
            'confirmation' => 'ARCHIVE bunshin-ai',
        ];

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/tenant/archive', $payload)
            ->assertUnprocessable();

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/tenant/archive', $payload)
            ->assertStatus(429);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTenant(string $name, string $slug, array $attributes = []): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            ...$attributes,
        ]);
    }

    private function createSecretUnlockToken(User $user, string $token): SecretUnlockToken
    {
        return $user->secretUnlockTokens()->create([
            'token' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    private function clearTenantLifecycleRateLimit(?Tenant $tenant, User $user): void
    {
        $key = 'tenant-lifecycle:'.(string) ($tenant?->id ?? 'no-tenant').':'.$user->id;

        RateLimiter::clear($key);
        RateLimiter::clear(md5('bunshin-tenant-lifecycle'.$key));
    }

    private function enableBilling(): void
    {
        config([
            'bunshin.billing.enabled' => true,
            'bunshin.billing.provider' => 'stripe',
            'bunshin.billing.providers.stripe.secret_key' => 'sk_test_123',
            'bunshin.billing.providers.stripe.api_base_url' => 'https://api.stripe.test',
        ]);
    }
}
