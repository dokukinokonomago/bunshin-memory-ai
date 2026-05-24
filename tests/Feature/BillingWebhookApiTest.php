<?php

namespace Tests\Feature;

use App\Models\BillingWebhookEvent;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BillingWebhookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_subscription_webhook_syncs_known_price_and_duplicate_is_idempotent(): void
    {
        $this->enableWebhookBilling();
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
        ]);
        $event = $this->event('evt_sub_updated_123', 'customer.subscription.updated', [
            'id' => 'sub_test_123',
            'customer' => 'cus_test_123',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'current_period_end' => 1_790_000_000,
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_pro_monthly'],
                    ],
                ],
            ],
        ]);

        $payload = $this->payload($event);

        $this
            ->postStripeWebhookPayload($payload)
            ->assertOk()
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.event_type', 'customer.subscription.updated')
            ->assertJsonPath('data.processing_status', BillingWebhookEvent::STATUS_PROCESSED);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_PRO, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);
        $this->assertSame('sub_test_123', $tenant->billing_subscription_id);
        $this->assertSame('price_pro_monthly', $tenant->billing_price_id);
        $this->assertFalse($tenant->billing_cancel_at_period_end);
        $this->assertNotNull($tenant->billing_last_synced_at);
        $this->assertNull($tenant->subscription_ends_at);

        $webhook = BillingWebhookEvent::query()->sole();

        $this->assertSame('stripe', $webhook->billing_provider);
        $this->assertSame('evt_sub_updated_123', $webhook->provider_event_id);
        $this->assertSame($tenant->id, $webhook->tenant_id);
        $this->assertSame('cus_test_123', $webhook->billing_customer_id);
        $this->assertSame('sub_test_123', $webhook->billing_subscription_id);
        $this->assertSame(hash('sha256', $payload), $webhook->payload_hash);
        $this->assertSame(BillingWebhookEvent::STATUS_PROCESSED, $webhook->processing_status);
        $this->assertNull($webhook->error_code);

        $securityEvent = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_BILLING_WEBHOOK_SYNC)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $securityEvent->tenant_id);
        $this->assertSame('stripe', $securityEvent->metadata['provider']);
        $this->assertSame('customer.subscription.updated', $securityEvent->metadata['provider_event_type']);
        $this->assertSame(Tenant::PLAN_PRO, $securityEvent->metadata['plan_key']);
        $this->assertStringNotContainsString('cus_test_123', json_encode($securityEvent->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('sub_test_123', json_encode($securityEvent->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('price_pro_monthly', json_encode($securityEvent->metadata, JSON_THROW_ON_ERROR));

        $this
            ->postStripeWebhookPayload($payload)
            ->assertOk()
            ->assertJsonPath('data.processing_status', 'duplicate');

        $this->assertSame(1, BillingWebhookEvent::query()->count());
        $this->assertSame(1, SecurityEvent::query()->where('event_type', SecurityEvent::TYPE_BILLING_WEBHOOK_SYNC)->count());
        $this->assertSame(Tenant::PLAN_PRO, $tenant->refresh()->plan_key);
    }

    public function test_checkout_completed_webhook_links_subscription_when_tenant_reference_and_price_are_known(): void
    {
        $this->enableWebhookBilling();
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_checkout_123',
        ]);
        $event = $this->event('evt_checkout_completed_123', 'checkout.session.completed', [
            'id' => 'cs_test_123',
            'customer' => 'cus_checkout_123',
            'client_reference_id' => $tenant->public_id,
            'status' => 'complete',
            'subscription' => [
                'id' => 'sub_checkout_123',
                'status' => 'active',
                'metadata' => [
                    'tenant_public_id' => $tenant->public_id,
                ],
                'items' => [
                    'data' => [
                        [
                            'price' => ['id' => 'price_pro_monthly'],
                        ],
                    ],
                ],
            ],
        ]);

        $this
            ->postStripeWebhook($event)
            ->assertOk()
            ->assertJsonPath('data.processing_status', BillingWebhookEvent::STATUS_PROCESSED);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_PRO, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);
        $this->assertSame('sub_checkout_123', $tenant->billing_subscription_id);
        $this->assertSame('price_pro_monthly', $tenant->billing_price_id);
    }

    public function test_invalid_signature_is_rejected_without_storing_or_mutating_billing_state(): void
    {
        $this->enableWebhookBilling();
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
        ]);
        $payload = $this->payload($this->event('evt_bad_signature', 'customer.subscription.updated', [
            'id' => 'sub_test_123',
            'customer' => 'cus_test_123',
            'status' => 'active',
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_pro_monthly'],
                    ],
                ],
            ],
        ]));

        $this
            ->postStripeWebhookPayload($payload, 't='.now()->getTimestamp().',v1=bad-signature')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Invalid billing webhook signature.');

        $this->assertSame(0, BillingWebhookEvent::query()->count());
        $this->assertSame(0, SecurityEvent::query()->where('event_type', SecurityEvent::TYPE_BILLING_WEBHOOK_SYNC)->count());
        $this->assertSame(Tenant::PLAN_FREE, $tenant->refresh()->plan_key);
        $this->assertNull($tenant->billing_subscription_id);
    }

    public function test_unknown_billing_references_or_price_do_not_grant_paid_entitlement(): void
    {
        $this->enableWebhookBilling();
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
        ]);
        $event = $this->event('evt_unknown_price', 'customer.subscription.updated', [
            'id' => 'sub_test_123',
            'customer' => 'cus_test_123',
            'status' => 'active',
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_unknown'],
                    ],
                ],
            ],
        ]);

        $this
            ->postStripeWebhook($event)
            ->assertOk()
            ->assertJsonPath('data.processing_status', BillingWebhookEvent::STATUS_FAILED);

        $tenant->refresh();
        $webhook = BillingWebhookEvent::query()->sole();

        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan_key);
        $this->assertNull($tenant->billing_subscription_id);
        $this->assertSame(BillingWebhookEvent::STATUS_FAILED, $webhook->processing_status);
        $this->assertSame('price_mapping_unknown', $webhook->error_code);
        $this->assertSame($tenant->id, $webhook->tenant_id);
    }

    public function test_subscription_deleted_webhook_cancels_local_subscription_state(): void
    {
        $this->enableWebhookBilling();
        $tenant = $this->tenant([
            'plan_key' => Tenant::PLAN_PRO,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
            'billing_subscription_id' => 'sub_test_123',
            'billing_price_id' => 'price_pro_monthly',
        ]);
        $event = $this->event('evt_subscription_deleted_123', 'customer.subscription.deleted', [
            'id' => 'sub_test_123',
            'customer' => 'cus_test_123',
            'status' => 'canceled',
            'canceled_at' => 1_790_100_000,
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_pro_monthly'],
                    ],
                ],
            ],
        ]);

        $this
            ->postStripeWebhook($event)
            ->assertOk()
            ->assertJsonPath('data.processing_status', BillingWebhookEvent::STATUS_PROCESSED);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_PRO, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_CANCELED, $tenant->subscription_status);
        $this->assertSame('2026-09-22 18:00:00', $tenant->subscription_ends_at?->format('Y-m-d H:i:s'));
    }

    public function test_webhook_requires_enabled_configured_provider_and_webhook_secret(): void
    {
        $payload = $this->payload($this->event('evt_disabled', 'customer.subscription.updated', [
            'id' => 'sub_test_123',
            'customer' => 'cus_test_123',
            'status' => 'active',
        ]));

        $this
            ->postStripeWebhookPayload($payload)
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Billing provider is not available.');

        config([
            'bunshin.billing.enabled' => true,
            'bunshin.billing.provider' => 'stripe',
            'bunshin.billing.providers.stripe.webhook_secret' => null,
        ]);

        $this
            ->postStripeWebhookPayload($payload)
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Billing provider is not available.');

        $this->assertSame(0, BillingWebhookEvent::query()->count());
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

    private function enableWebhookBilling(): void
    {
        config([
            'bunshin.billing.enabled' => true,
            'bunshin.billing.provider' => 'stripe',
            'bunshin.billing.providers.stripe.webhook_secret' => 'whsec_test_123',
            'bunshin.billing.price_plan_map.stripe' => [
                'price_pro_monthly' => Tenant::PLAN_PRO,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function event(string $id, string $type, array $object): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'livemode' => false,
            'data' => [
                'object' => $object,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function postStripeWebhook(array $event): TestResponse
    {
        return $this->postStripeWebhookPayload($this->payload($event));
    }

    private function postStripeWebhookPayload(string $payload, ?string $signature = null): TestResponse
    {
        return $this->call(
            'POST',
            '/api/v1/billing/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature ?? $this->stripeSignature($payload),
            ],
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function payload(array $event): string
    {
        return json_encode($event, JSON_THROW_ON_ERROR);
    }

    private function stripeSignature(string $payload): string
    {
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

        return "t={$timestamp},v1={$signature}";
    }
}
