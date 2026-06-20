<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_dry_run_reports_provider_local_drift_without_mutating_local_state(): void
    {
        $this->enableBilling();
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
            'billing_subscription_id' => 'sub_test_123',
            'plan_key' => Tenant::PLAN_FREE,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://api.stripe.test/v1/subscriptions/sub_test_123' => Http::response($this->subscription([
                'id' => 'sub_test_123',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_pro_monthly']],
                    ],
                ],
            ]), 200),
        ]);

        $this
            ->artisan('bunshin:reconcile-billing-provider')
            ->assertExitCode(0);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);
        $this->assertNull($tenant->billing_price_id);
        $this->assertNull($tenant->billing_last_synced_at);
        $this->assertDatabaseMissing('security_events', [
            'event_type' => SecurityEvent::TYPE_BILLING_RECONCILIATION,
        ]);
    }

    public function test_apply_links_single_provider_subscription_for_known_customer_and_logs_scrub_safe_event(): void
    {
        $this->enableBilling();
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_checkout_123',
            'billing_subscription_id' => null,
            'plan_key' => Tenant::PLAN_FREE,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://api.stripe.test/v1/subscriptions*' => Http::response([
                'data' => [
                    $this->subscription([
                        'id' => 'sub_checkout_123',
                        'customer' => 'cus_checkout_123',
                        'status' => 'trialing',
                        'trial_end' => 1_790_000_000,
                        'items' => [
                            'data' => [
                                ['price' => ['id' => 'price_pro_monthly']],
                            ],
                        ],
                    ]),
                ],
            ], 200),
        ]);

        $this
            ->artisan('bunshin:reconcile-billing-provider', [
                'tenant' => $tenant->public_id,
                '--apply' => true,
            ])
            ->assertExitCode(0);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_PRO, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_TRIALING, $tenant->subscription_status);
        $this->assertSame('sub_checkout_123', $tenant->billing_subscription_id);
        $this->assertSame('price_pro_monthly', $tenant->billing_price_id);
        $this->assertSame('2026-09-21 14:13:20', $tenant->trial_ends_at?->format('Y-m-d H:i:s'));
        $this->assertNotNull($tenant->billing_last_synced_at);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_BILLING_RECONCILIATION)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertNull($event->subject_email);
        $this->assertNull($event->ip_address);
        $this->assertNull($event->user_agent);
        $this->assertSame('stripe', $event->metadata['provider']);
        $this->assertSame('apply', $event->metadata['mode']);
        $this->assertSame('applied', $event->metadata['result']);
        $this->assertSame(Tenant::PLAN_PRO, $event->metadata['plan_key']);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_TRIALING, $event->metadata['subscription_status']);

        $metadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('cus_checkout_123', $metadata);
        $this->assertStringNotContainsString('sub_checkout_123', $metadata);
        $this->assertStringNotContainsString('price_pro_monthly', $metadata);
    }

    public function test_apply_skips_unknown_price_without_granting_paid_entitlement(): void
    {
        $this->enableBilling();
        $tenant = $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
            'billing_subscription_id' => 'sub_test_123',
            'plan_key' => Tenant::PLAN_FREE,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://api.stripe.test/v1/subscriptions/sub_test_123' => Http::response($this->subscription([
                'id' => 'sub_test_123',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_unknown']],
                    ],
                ],
            ]), 200),
        ]);

        $this
            ->artisan('bunshin:reconcile-billing-provider', ['--apply' => true])
            ->assertExitCode(0);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan_key);
        $this->assertNull($tenant->billing_price_id);
        $this->assertNull($tenant->billing_last_synced_at);
        $this->assertDatabaseMissing('security_events', [
            'event_type' => SecurityEvent::TYPE_BILLING_RECONCILIATION,
        ]);
    }

    public function test_apply_skips_archived_tenant_without_reactivating_subscription(): void
    {
        $this->enableBilling();
        $tenant = $this->tenant([
            'archived_at' => now()->subDay(),
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_archived_123',
            'billing_subscription_id' => 'sub_archived_123',
            'plan_key' => Tenant::PLAN_FREE,
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_CANCELED,
            'subscription_ends_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://api.stripe.test/v1/subscriptions/sub_archived_123' => Http::response($this->subscription([
                'id' => 'sub_archived_123',
                'customer' => 'cus_archived_123',
                'status' => 'active',
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_pro_monthly']],
                    ],
                ],
            ]), 200),
        ]);

        $this
            ->artisan('bunshin:reconcile-billing-provider', ['--apply' => true])
            ->assertExitCode(0);

        $tenant->refresh();

        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_CANCELED, $tenant->subscription_status);
        $this->assertTrue($tenant->subscription_ends_at?->isPast());
        $this->assertNull($tenant->billing_price_id);
        $this->assertNull($tenant->billing_last_synced_at);
        $this->assertDatabaseMissing('security_events', [
            'event_type' => SecurityEvent::TYPE_BILLING_RECONCILIATION,
        ]);
    }

    public function test_command_requires_enabled_configured_provider_without_provider_calls(): void
    {
        Http::fake();

        $this->tenant([
            'billing_provider' => 'stripe',
            'billing_customer_id' => 'cus_test_123',
            'billing_subscription_id' => 'sub_test_123',
        ]);

        $this
            ->artisan('bunshin:reconcile-billing-provider')
            ->assertExitCode(1);

        Http::assertNothingSent();
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

    private function enableBilling(): void
    {
        config([
            'bunshin.billing.enabled' => true,
            'bunshin.billing.provider' => 'stripe',
            'bunshin.billing.providers.stripe.secret_key' => 'sk_test_123',
            'bunshin.billing.providers.stripe.api_base_url' => 'https://api.stripe.test',
            'bunshin.billing.price_plan_map.stripe' => [
                'price_pro_monthly' => Tenant::PLAN_PRO,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function subscription(array $attributes = []): array
    {
        return [
            'id' => 'sub_test_123',
            'object' => 'subscription',
            'customer' => 'cus_test_123',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'items' => [
                'data' => [
                    ['price' => ['id' => 'price_pro_monthly']],
                ],
            ],
            ...$attributes,
        ];
    }
}
