<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BillingSessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_verified_owner_can_create_checkout_session_without_mutating_local_plan_state(): void
    {
        $this->enableBilling();

        Http::fake([
            'https://api.stripe.test/v1/customers' => Http::response(['id' => 'cus_test_123'], 200),
            'https://api.stripe.test/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.test/session/cs_test_123',
            ], 200),
        ]);

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => ' Pro ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'checkout')
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.plan_key', Tenant::PLAN_PRO)
            ->assertJsonPath('data.url', 'https://checkout.stripe.test/session/cs_test_123')
            ->assertJsonPath('data.tenant.public_id', $tenant->public_id)
            ->assertJsonPath('data.tenant.plan_key', Tenant::PLAN_FREE)
            ->assertJsonPath('data.tenant.subscription_status', Tenant::SUBSCRIPTION_STATUS_ACTIVE);

        $tenant->refresh();

        $this->assertSame('stripe', $tenant->billing_provider);
        $this->assertSame('cus_test_123', $tenant->billing_customer_id);
        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);

        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.test/v1/customers'
            && $request['email'] === 'owner@example.test'
            && $request['metadata[tenant_public_id]'] === $tenant->public_id
            && $request['metadata[owner_user_public_id]'] === $owner->public_id);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.test/v1/checkout/sessions'
            && $request['mode'] === 'subscription'
            && $request['customer'] === 'cus_test_123'
            && $request['line_items[0][price]'] === 'price_pro_monthly'
            && $request['client_reference_id'] === $tenant->public_id);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_BILLING_CHECKOUT_SESSION_CREATE)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($owner->id, $event->user_id);
        $this->assertSame('stripe', $event->metadata['provider']);
        $this->assertSame(Tenant::PLAN_PRO, $event->metadata['plan_key']);
        $this->assertTrue($event->metadata['customer_created']);
        $this->assertStringNotContainsString('checkout.stripe.test', json_encode($event->metadata, JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('url', $event->metadata);
    }

    public function test_verified_owner_can_create_portal_session_without_mutating_local_plan_state(): void
    {
        $this->enableBilling();

        Http::fake([
            'https://api.stripe.test/v1/billing_portal/sessions' => Http::response([
                'id' => 'bps_test_123',
                'url' => 'https://billing.stripe.test/session/bps_test_123',
            ], 200),
        ]);

        $tenant = $this->tenant([
            'plan_key' => Tenant::PLAN_PRO,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_456',
            'billing_subscription_id' => 'sub_test_456',
            'billing_price_id' => 'price_pro_monthly',
        ]);
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/portal-sessions')
            ->assertCreated()
            ->assertJsonPath('data.mode', 'portal')
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.url', 'https://billing.stripe.test/session/bps_test_123')
            ->assertJsonPath('data.tenant.public_id', $tenant->public_id)
            ->assertJsonPath('data.tenant.plan_key', Tenant::PLAN_PRO)
            ->assertJsonPath('data.tenant.subscription_status', Tenant::SUBSCRIPTION_STATUS_ACTIVE);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_PRO, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);
        $this->assertSame('cus_test_456', $tenant->billing_customer_id);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.test/v1/billing_portal/sessions'
            && $request['customer'] === 'cus_test_456'
            && $request['return_url'] === 'https://app.example.test/billing');

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_BILLING_PORTAL_SESSION_CREATE)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($owner->id, $event->user_id);
        $this->assertSame('stripe', $event->metadata['provider']);
        $this->assertStringNotContainsString('billing.stripe.test', json_encode($event->metadata, JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('url', $event->metadata);
    }

    public function test_billing_session_endpoints_require_auth_owner_and_verified_email(): void
    {
        $this->enableBilling();
        Http::fake();

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $unverifiedOwner = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'email' => 'unverified-owner@example.test',
        ]);

        $this
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => Tenant::PLAN_PRO,
            ])
            ->assertUnauthorized();

        $this
            ->withApiToken($member)
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => Tenant::PLAN_PRO,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->withApiToken($unverifiedOwner)
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => Tenant::PLAN_PRO,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Email verification is required for billing management.');

        $this
            ->withApiToken($unverifiedOwner)
            ->postJson('/api/v1/billing/portal-sessions')
            ->assertForbidden()
            ->assertJsonPath('message', 'Email verification is required for billing management.');

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => Tenant::PLAN_PRO,
            ])
            ->assertStatus(Response::HTTP_BAD_GATEWAY);
    }

    public function test_checkout_rejects_disabled_or_misconfigured_provider_and_unknown_plan_without_provider_calls(): void
    {
        Http::fake();

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => Tenant::PLAN_PRO,
            ])
            ->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE)
            ->assertJsonPath('message', 'Billing provider is not available.');

        config([
            'bunshin.billing.enabled' => true,
            'bunshin.billing.provider' => 'stripe',
            'bunshin.billing.providers.stripe.secret_key' => null,
        ]);

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => Tenant::PLAN_PRO,
            ])
            ->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE)
            ->assertJsonPath('message', 'Billing provider is not available.');

        $this->enableBilling();

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/checkout-sessions', [
                'plan_key' => Tenant::PLAN_FREE,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_key']);

        Http::assertNothingSent();
        $this->assertNull($tenant->refresh()->billing_customer_id);
        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);
    }

    public function test_portal_requires_existing_billing_customer_and_matching_provider_without_provider_calls(): void
    {
        $this->enableBilling();
        Http::fake();

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/portal-sessions')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_customer']);

        $tenant->forceFill([
            'billing_provider' => 'test-provider',
            'billing_customer_id' => 'cus_other',
        ])->save();

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/billing/portal-sessions')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_provider']);

        Http::assertNothingSent();
        $this->assertSame(Tenant::PLAN_FREE, $tenant->refresh()->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Bunshin AI',
            'slug' => 'bunshin-ai',
            ...$attributes,
        ])->refresh();
    }

    private function user(Tenant $tenant, string $role, string $email): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
            'email' => $email,
        ]);
    }

    private function enableBilling(): void
    {
        config([
            'bunshin.billing.enabled' => true,
            'bunshin.billing.provider' => 'stripe',
            'bunshin.billing.providers.stripe.secret_key' => 'sk_test_123',
            'bunshin.billing.providers.stripe.api_base_url' => 'https://api.stripe.test',
            'bunshin.billing.checkout.success_url' => 'https://app.example.test/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'bunshin.billing.checkout.cancel_url' => 'https://app.example.test/billing/cancel',
            'bunshin.billing.portal.return_url' => 'https://app.example.test/billing',
            'bunshin.billing.price_plan_map.stripe' => [
                'price_pro_monthly' => Tenant::PLAN_PRO,
            ],
        ]);
    }
}
