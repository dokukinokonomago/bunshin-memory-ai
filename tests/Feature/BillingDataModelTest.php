<?php

namespace Tests\Feature;

use App\Models\BillingWebhookEvent;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_billing_fields_are_fillable_casted_and_related_to_webhook_events(): void
    {
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
            'billing_subscription_id' => 'sub_test_123',
            'billing_price_id' => 'price_pro_monthly',
            'billing_cancel_at_period_end' => true,
            'billing_last_synced_at' => '2026-05-16 10:03:00',
        ]);

        $event = BillingWebhookEvent::query()->create([
            'billing_provider' => 'stripe',
            'provider_event_id' => 'evt_test_123',
            'event_type' => 'customer.subscription.updated',
            'livemode' => true,
            'tenant_id' => $tenant->id,
            'billing_customer_id' => 'cus_test_123',
            'billing_subscription_id' => 'sub_test_123',
            'payload_hash' => hash('sha256', '{"id":"evt_test_123"}'),
            'received_at' => '2026-05-16 10:04:00',
            'processed_at' => '2026-05-16 10:04:05',
            'processing_status' => BillingWebhookEvent::STATUS_PROCESSED,
        ]);

        $tenant->refresh();
        $event->refresh();

        $this->assertTrue($tenant->billing_cancel_at_period_end);
        $this->assertSame('2026-05-16 10:03:00', $tenant->billing_last_synced_at?->format('Y-m-d H:i:s'));
        $this->assertTrue($event->livemode);
        $this->assertSame('2026-05-16 10:04:00', $event->received_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-16 10:04:05', $event->processed_at?->format('Y-m-d H:i:s'));
        $this->assertTrue($tenant->billingWebhookEvents->contains($event));
        $this->assertTrue($event->tenant->is($tenant));
    }

    public function test_provider_customer_id_is_unique_per_provider(): void
    {
        $this->tenant([
            'slug' => 'stripe-tenant',
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_shared',
            'billing_subscription_id' => 'sub_stripe',
        ]);
        $this->tenant([
            'slug' => 'other-provider-tenant',
            'billing_provider' => 'test-provider',
            'billing_customer_id' => 'cus_shared',
            'billing_subscription_id' => 'sub_other',
        ]);

        $this->expectException(QueryException::class);

        $this->tenant([
            'slug' => 'stripe-duplicate-customer',
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_shared',
            'billing_subscription_id' => 'sub_unique',
        ]);
    }

    public function test_provider_subscription_id_is_unique_per_provider(): void
    {
        $this->tenant([
            'slug' => 'stripe-tenant',
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_stripe',
            'billing_subscription_id' => 'sub_shared',
        ]);
        $this->tenant([
            'slug' => 'other-provider-tenant',
            'billing_provider' => 'test-provider',
            'billing_customer_id' => 'cus_other',
            'billing_subscription_id' => 'sub_shared',
        ]);

        $this->expectException(QueryException::class);

        $this->tenant([
            'slug' => 'stripe-duplicate-subscription',
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_unique',
            'billing_subscription_id' => 'sub_shared',
        ]);
    }

    public function test_billing_webhook_events_use_provider_event_id_for_idempotency_without_raw_payload_columns(): void
    {
        $expectedColumns = [
            'billing_provider',
            'provider_event_id',
            'event_type',
            'livemode',
            'tenant_id',
            'billing_customer_id',
            'billing_subscription_id',
            'payload_hash',
            'received_at',
            'processed_at',
            'processing_status',
            'error_code',
            'error_message',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(Schema::hasColumn('billing_webhook_events', $column), $column);
        }

        $this->assertFalse(Schema::hasColumn('billing_webhook_events', 'payload'));
        $this->assertFalse(Schema::hasColumn('billing_webhook_events', 'raw_payload'));
        $this->assertFalse(Schema::hasColumn('billing_webhook_events', 'signature_secret'));

        BillingWebhookEvent::query()->create([
            'billing_provider' => 'stripe',
            'provider_event_id' => 'evt_duplicate',
            'event_type' => 'checkout.session.completed',
            'livemode' => false,
            'payload_hash' => hash('sha256', '{"id":"evt_duplicate"}'),
            'received_at' => now(),
            'processing_status' => BillingWebhookEvent::STATUS_RECEIVED,
        ]);
        BillingWebhookEvent::query()->create([
            'billing_provider' => 'test-provider',
            'provider_event_id' => 'evt_duplicate',
            'event_type' => 'checkout.session.completed',
            'livemode' => false,
            'payload_hash' => hash('sha256', '{"id":"evt_duplicate"}'),
            'received_at' => now(),
            'processing_status' => BillingWebhookEvent::STATUS_RECEIVED,
        ]);

        $this->expectException(QueryException::class);

        BillingWebhookEvent::query()->create([
            'billing_provider' => 'stripe',
            'provider_event_id' => 'evt_duplicate',
            'event_type' => 'checkout.session.completed',
            'livemode' => false,
            'payload_hash' => hash('sha256', '{"id":"evt_duplicate"}'),
            'received_at' => now(),
            'processing_status' => BillingWebhookEvent::STATUS_RECEIVED,
        ]);
    }

    public function test_billing_config_stub_is_provider_neutral_and_disabled_by_default(): void
    {
        $this->assertFalse(config('bunshin.billing.enabled'));
        $this->assertNull(config('bunshin.billing.provider'));
        $this->assertSame(300, config('bunshin.billing.webhook_tolerance_seconds'));
        $this->assertSame([], config('bunshin.billing.price_plan_map.stripe'));

        config(['bunshin.billing.price_plan_map.stripe.price_pro_monthly' => Tenant::PLAN_PRO]);

        $this->assertSame(Tenant::PLAN_PRO, config('bunshin.billing.price_plan_map.stripe.price_pro_monthly'));
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
}
