<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BillingSmokeReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $envKeys = [
        'BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL',
        'BUNSHIN_BILLING_CHECKOUT_CANCEL_URL',
        'BUNSHIN_BILLING_PORTAL_RETURN_URL',
        'BUNSHIN_BILLING_SMOKE_API_ORIGIN',
        'API_ORIGIN',
        'BUNSHIN_BILLING_SMOKE_FRONTEND_ORIGIN',
        'FRONTEND_ORIGIN',
        'BUNSHIN_BILLING_SMOKE_OWNER_TOKEN',
        'OWNER_TOKEN',
        'BUNSHIN_BILLING_SMOKE_PROVIDER_CONFIRMED',
        'PROVIDER_ACCOUNT_CONFIRMED',
        'BUNSHIN_BILLING_SMOKE_TENANT',
        'SMOKE_TENANT_PUBLIC_ID',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearSmokeEnvironment();
    }

    protected function tearDown(): void
    {
        $this->clearSmokeEnvironment();

        parent::tearDown();
    }

    public function test_missing_prerequisites_fail_without_provider_calls_or_sensitive_output(): void
    {
        config([
            'bunshin.billing.enabled' => false,
            'bunshin.billing.provider' => null,
            'bunshin.billing.providers.stripe.secret_key' => null,
            'bunshin.billing.providers.stripe.webhook_secret' => null,
            'bunshin.billing.price_plan_map.stripe' => [],
        ]);

        $exitCode = Artisan::call('bunshin:billing-smoke-readiness');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Billing smoke readiness failed.', $output);
        $this->assertStringContainsString('Billing enabled', $output);
        $this->assertStringContainsString('Smoke tenant ready', $output);
        $this->assertStringNotContainsString('sk_live_secret', $output);
        $this->assertStringNotContainsString('whsec_live_secret', $output);
        $this->assertStringNotContainsString('owner_token_secret', $output);
    }

    public function test_ready_config_and_smoke_tenant_pass_without_printing_secrets_urls_or_provider_ids(): void
    {
        $tenant = $this->tenant('ready-smoke-tenant');
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'email' => 'ready-owner@example.test',
            'email_verified_at' => now(),
        ]);
        $this->configureReadyBilling();
        $this->configureReadyEnvironment();

        $exitCode = Artisan::call('bunshin:billing-smoke-readiness', [
            '--tenant' => $tenant->public_id,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Billing smoke readiness passed.', $output);
        $this->assertStringContainsString('Provider account confirmed', $output);
        $this->assertStringContainsString('Smoke tenant ready', $output);

        foreach ([
            'sk_live_secret',
            'whsec_live_secret',
            'price_live_secret',
            'owner_token_secret',
            'https://api.example.test',
            'https://app.example.test',
            'cus_live_secret',
            'sub_live_secret',
            $tenant->public_id,
            $tenant->slug,
            'ready-owner@example.test',
        ] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, $output);
        }
    }

    public function test_smoke_tenant_requires_active_verified_owner(): void
    {
        $tenant = $this->tenant('unverified-smoke-tenant');
        User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'email' => 'unverified-owner@example.test',
        ]);
        $this->configureReadyBilling();
        $this->configureReadyEnvironment();

        $exitCode = Artisan::call('bunshin:billing-smoke-readiness', [
            '--tenant' => $tenant->public_id,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Approved smoke tenant needs an active verified owner.', $output);
        $this->assertStringNotContainsString($tenant->public_id, $output);
        $this->assertStringNotContainsString($tenant->slug, $output);
        $this->assertStringNotContainsString('unverified-owner@example.test', $output);
    }

    public function test_smoke_tenant_can_come_from_environment_without_being_printed(): void
    {
        $tenant = $this->tenant('env-smoke-tenant');
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'email' => 'env-owner@example.test',
            'email_verified_at' => now(),
        ]);
        $this->configureReadyBilling();
        $this->configureReadyEnvironment();
        $this->setRuntimeEnv('BUNSHIN_BILLING_SMOKE_TENANT', $tenant->public_id);

        $exitCode = Artisan::call('bunshin:billing-smoke-readiness');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Billing smoke readiness passed.', $output);
        $this->assertStringNotContainsString($tenant->public_id, $output);
        $this->assertStringNotContainsString($tenant->slug, $output);
        $this->assertStringNotContainsString('env-owner@example.test', $output);
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Bunshin AI',
            'slug' => $slug,
        ])->refresh();
    }

    private function configureReadyBilling(): void
    {
        config([
            'bunshin.billing.enabled' => true,
            'bunshin.billing.provider' => 'stripe',
            'bunshin.billing.providers.stripe.secret_key' => 'sk_live_secret',
            'bunshin.billing.providers.stripe.webhook_secret' => 'whsec_live_secret',
            'bunshin.billing.providers.stripe.api_base_url' => 'https://api.stripe.com',
            'bunshin.billing.checkout.success_url' => 'https://app.example.test/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'bunshin.billing.checkout.cancel_url' => 'https://app.example.test/billing/cancel',
            'bunshin.billing.portal.return_url' => 'https://app.example.test/billing',
            'bunshin.billing.price_plan_map.stripe' => [
                'price_live_secret' => Tenant::PLAN_PRO,
            ],
        ]);
    }

    private function configureReadyEnvironment(): void
    {
        $this->setRuntimeEnv(
            'BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL',
            'https://app.example.test/billing/success?session_id={CHECKOUT_SESSION_ID}',
        );
        $this->setRuntimeEnv('BUNSHIN_BILLING_CHECKOUT_CANCEL_URL', 'https://app.example.test/billing/cancel');
        $this->setRuntimeEnv('BUNSHIN_BILLING_PORTAL_RETURN_URL', 'https://app.example.test/billing');
        $this->setRuntimeEnv('BUNSHIN_BILLING_SMOKE_API_ORIGIN', 'https://api.example.test');
        $this->setRuntimeEnv('BUNSHIN_BILLING_SMOKE_FRONTEND_ORIGIN', 'https://app.example.test');
        $this->setRuntimeEnv('BUNSHIN_BILLING_SMOKE_OWNER_TOKEN', 'owner_token_secret');
        $this->setRuntimeEnv('BUNSHIN_BILLING_SMOKE_PROVIDER_CONFIRMED', 'true');
    }

    private function setRuntimeEnv(string $key, string $value): void
    {
        putenv($key.'='.$value);

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function clearSmokeEnvironment(): void
    {
        foreach ($this->envKeys as $key) {
            putenv($key);

            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}
