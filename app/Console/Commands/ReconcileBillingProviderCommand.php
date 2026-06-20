<?php

namespace App\Console\Commands;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Support\Billing\BillingProviderException;
use App\Support\Billing\StripeBillingClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReconcileBillingProviderCommand extends Command
{
    protected $signature = 'bunshin:reconcile-billing-provider
        {tenant? : Tenant public id or slug to inspect}
        {--apply : Apply safe local billing state updates}
        {--limit=100 : Maximum tenant billing records to inspect}';

    protected $description = 'Compare provider subscription state with local tenant billing state.';

    public function __construct(private readonly StripeBillingClient $stripe)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider = $this->configuredProvider();
        $limit = $this->limitOption();

        if ($provider === null || $limit === null) {
            return self::FAILURE;
        }

        $target = $this->argumentString('tenant');
        $targetTenant = null;

        if ($target !== null) {
            $targetTenant = $this->targetTenant($target);

            if (! $targetTenant instanceof Tenant) {
                $this->error('Tenant target not found.');

                return self::FAILURE;
            }
        }

        $tenants = $this->tenantQuery($provider, $targetTenant)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenant billing records found.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $rows = [];
        $summary = [
            'inspected' => 0,
            'in_sync' => 0,
            'drift' => 0,
            'applied' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($tenants as $tenant) {
            $summary['inspected']++;
            $inspection = $this->inspectTenant($tenant, $provider);

            if ($inspection['result'] === 'provider_request_failed') {
                $summary['failed']++;
            } elseif ($inspection['result'] === 'in_sync') {
                $summary['in_sync']++;
            } elseif ($inspection['can_apply']) {
                $summary['drift']++;
            } else {
                $summary['skipped']++;
            }

            $result = $inspection['result'];

            if ($apply && $inspection['can_apply']) {
                $this->applyExpectedState($tenant, $provider, $inspection['expected'], $inspection['drift_fields']);
                $summary['applied']++;
                $result = 'applied';
            }

            $rows[] = [
                $tenant->public_id,
                $tenant->slug,
                $tenant->plan_key.'/'.$tenant->subscription_status,
                $inspection['provider_plan_key'] === null || $inspection['provider_status'] === null
                    ? '-'
                    : $inspection['provider_plan_key'].'/'.$inspection['provider_status'],
                $result,
                $inspection['drift_fields'] === [] ? '-' : implode(',', $inspection['drift_fields']),
            ];
        }

        if (! $apply) {
            $this->warn('Dry run: no local billing rows changed.');
        }

        $this->table([
            'Tenant',
            'Slug',
            'Local',
            'Provider mapped',
            'Result',
            'Drift fields',
        ], $rows);

        $this->line(sprintf(
            'Billing reconciliation complete. Inspected: %d. In sync: %d. Drift: %d. Applied: %d. Skipped: %d. Failed: %d.',
            $summary['inspected'],
            $summary['in_sync'],
            $summary['drift'],
            $summary['applied'],
            $summary['skipped'],
            $summary['failed'],
        ));

        return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function configuredProvider(): ?string
    {
        if (! filter_var(config('bunshin.billing.enabled'), FILTER_VALIDATE_BOOL)) {
            $this->error('Billing provider is not enabled.');

            return null;
        }

        $provider = config('bunshin.billing.provider');

        if (! is_string($provider) || trim($provider) === '') {
            $this->error('Billing provider is not configured.');

            return null;
        }

        $provider = trim($provider);

        if ($provider !== 'stripe') {
            $this->error('Configured billing provider is not supported by reconciliation.');

            return null;
        }

        foreach ([
            'bunshin.billing.providers.stripe.secret_key',
            'bunshin.billing.providers.stripe.api_base_url',
        ] as $configKey) {
            $value = config($configKey);

            if (! is_string($value) || trim($value) === '') {
                $this->error('Billing provider read configuration is incomplete.');

                return null;
            }
        }

        return $provider;
    }

    private function limitOption(): ?int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if (! is_int($limit) || $limit < 1 || $limit > 500) {
            $this->error('The --limit option must be an integer between 1 and 500.');

            return null;
        }

        return $limit;
    }

    private function argumentString(string $name): ?string
    {
        $value = $this->argument($name);
        $value = is_array($value) ? reset($value) : $value;
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function targetTenant(string $target): ?Tenant
    {
        return Tenant::query()
            ->where('public_id', $target)
            ->orWhere('slug', $target)
            ->first();
    }

    /**
     * @return Builder<Tenant>
     */
    private function tenantQuery(string $provider, ?Tenant $targetTenant): Builder
    {
        return Tenant::query()
            ->where('billing_provider', $provider)
            ->whereNotNull('billing_customer_id')
            ->when(
                $targetTenant instanceof Tenant,
                static fn (Builder $query): Builder => $query->whereKey($targetTenant->id),
            );
    }

    /**
     * @return array{
     *     result: string,
     *     provider_plan_key: string|null,
     *     provider_status: string|null,
     *     drift_fields: list<string>,
     *     can_apply: bool,
     *     expected: array<string, mixed>
     * }
     */
    private function inspectTenant(Tenant $tenant, string $provider): array
    {
        try {
            $providerLookup = $this->providerSubscriptionForTenant($tenant);
        } catch (BillingProviderException) {
            return $this->inspectionResult('provider_request_failed');
        }

        $subscription = $providerLookup['subscription'];

        if (! is_array($subscription)) {
            return $this->inspectionResult($providerLookup['result']);
        }

        $subscriptionId = $this->stringValue($subscription['id'] ?? null);

        if ($subscriptionId === null) {
            return $this->inspectionResult('subscription_reference_missing');
        }

        $customerId = $this->extractCustomerId($subscription);

        if ($customerId !== null
            && is_string($tenant->billing_customer_id)
            && $tenant->billing_customer_id !== ''
            && $tenant->billing_customer_id !== $customerId) {
            return $this->inspectionResult('customer_mismatch');
        }

        if ($tenant->isArchived()) {
            return $this->inspectionResult('tenant_archived');
        }

        $priceId = $this->extractPriceId($subscription);

        if ($priceId === null) {
            return $this->inspectionResult('price_reference_missing');
        }

        $planKey = $this->planKeyForPrice($provider, $priceId);

        if ($planKey === null) {
            return $this->inspectionResult('price_mapping_unknown');
        }

        $providerStatus = $this->stringValue($subscription['status'] ?? null);
        $localStatus = $this->localStatusForProviderStatus($providerStatus);

        if ($localStatus === null) {
            return $this->inspectionResult(
                result: 'subscription_status_unknown',
                providerPlanKey: $planKey,
                providerStatus: $providerStatus,
            );
        }

        $cancelAtPeriodEnd = (bool) ($subscription['cancel_at_period_end'] ?? false);
        $expected = [
            'billing_provider' => $provider,
            'billing_customer_id' => $customerId ?? $tenant->billing_customer_id,
            'billing_subscription_id' => $subscriptionId,
            'billing_price_id' => $priceId,
            'billing_cancel_at_period_end' => $cancelAtPeriodEnd,
            'plan_key' => $planKey,
            'subscription_status' => $localStatus,
            'trial_ends_at' => $localStatus === Tenant::SUBSCRIPTION_STATUS_TRIALING
                ? $this->timestampToCarbon($subscription['trial_end'] ?? null)
                : null,
            'subscription_ends_at' => $this->subscriptionEndsAt($localStatus, $cancelAtPeriodEnd, $subscription),
        ];
        $driftFields = $this->driftFields($tenant, $expected);

        return [
            'result' => $driftFields === [] ? 'in_sync' : 'drift',
            'provider_plan_key' => $planKey,
            'provider_status' => $providerStatus,
            'drift_fields' => $driftFields,
            'can_apply' => $driftFields !== [],
            'expected' => $expected,
        ];
    }

    /**
     * @return array{subscription: array<string, mixed>|null, result: string}
     */
    private function providerSubscriptionForTenant(Tenant $tenant): array
    {
        if (is_string($tenant->billing_subscription_id) && $tenant->billing_subscription_id !== '') {
            return [
                'subscription' => $this->stripe->retrieveSubscription($tenant->billing_subscription_id),
                'result' => 'found',
            ];
        }

        if (! is_string($tenant->billing_customer_id) || $tenant->billing_customer_id === '') {
            return [
                'subscription' => null,
                'result' => 'provider_subscription_missing',
            ];
        }

        $subscriptions = $this->stripe->listCustomerSubscriptions($tenant->billing_customer_id, 2);

        if (count($subscriptions) === 0) {
            return [
                'subscription' => null,
                'result' => 'provider_subscription_missing',
            ];
        }

        if (count($subscriptions) > 1) {
            return [
                'subscription' => null,
                'result' => 'provider_subscription_ambiguous',
            ];
        }

        return [
            'subscription' => $subscriptions[0],
            'result' => 'found',
        ];
    }

    /**
     * @return array{
     *     result: string,
     *     provider_plan_key: string|null,
     *     provider_status: string|null,
     *     drift_fields: list<string>,
     *     can_apply: bool,
     *     expected: array<string, mixed>
     * }
     */
    private function inspectionResult(
        string $result,
        ?string $providerPlanKey = null,
        ?string $providerStatus = null,
    ): array {
        return [
            'result' => $result,
            'provider_plan_key' => $providerPlanKey,
            'provider_status' => $providerStatus,
            'drift_fields' => [],
            'can_apply' => false,
            'expected' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $expected
     * @return list<string>
     */
    private function driftFields(Tenant $tenant, array $expected): array
    {
        $fields = [];

        foreach ([
            'billing_provider',
            'billing_customer_id',
            'billing_subscription_id',
            'billing_price_id',
            'plan_key',
            'subscription_status',
        ] as $field) {
            if ($tenant->{$field} !== $expected[$field]) {
                $fields[] = $field;
            }
        }

        if ((bool) $tenant->billing_cancel_at_period_end !== (bool) $expected['billing_cancel_at_period_end']) {
            $fields[] = 'billing_cancel_at_period_end';
        }

        foreach (['trial_ends_at', 'subscription_ends_at'] as $field) {
            if (! $this->sameTimestamp($tenant->{$field}, $expected[$field])) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  list<string>  $driftFields
     */
    private function applyExpectedState(Tenant $tenant, string $provider, array $expected, array $driftFields): void
    {
        $tenant->forceFill([
            ...$expected,
            'billing_last_synced_at' => now(),
        ])->save();

        SecurityEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_type' => SecurityEvent::TYPE_BILLING_RECONCILIATION,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'metadata' => [
                'provider' => $provider,
                'mode' => 'apply',
                'result' => 'applied',
                'plan_key' => $expected['plan_key'],
                'subscription_status' => $expected['subscription_status'],
                'changed_fields' => $driftFields,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function extractCustomerId(array $subscription): ?string
    {
        return $this->stringValue($subscription['customer'] ?? null)
            ?? $this->stringValue($this->valueAtPath($subscription, ['customer', 'id']));
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function extractPriceId(array $subscription): ?string
    {
        foreach ([
            ['items', 'data', 0, 'price', 'id'],
            ['plan', 'id'],
        ] as $path) {
            $value = $this->valueAtPath($subscription, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function planKeyForPrice(string $provider, string $priceId): ?string
    {
        $pricePlanMap = config("bunshin.billing.price_plan_map.{$provider}", []);

        if (! is_array($pricePlanMap)) {
            return null;
        }

        $planKey = $pricePlanMap[$priceId] ?? null;

        if (! is_string($planKey) || trim($planKey) === '') {
            return null;
        }

        $plans = config('bunshin.plans', []);

        return is_array($plans) && array_key_exists($planKey, $plans) ? $planKey : null;
    }

    private function localStatusForProviderStatus(?string $providerStatus): ?string
    {
        return match ($providerStatus) {
            'active' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            'trialing' => Tenant::SUBSCRIPTION_STATUS_TRIALING,
            'past_due', 'unpaid' => Tenant::SUBSCRIPTION_STATUS_PAST_DUE,
            'canceled' => Tenant::SUBSCRIPTION_STATUS_CANCELED,
            'incomplete', 'incomplete_expired' => Tenant::SUBSCRIPTION_STATUS_INCOMPLETE,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function subscriptionEndsAt(string $status, bool $cancelAtPeriodEnd, array $subscription): ?Carbon
    {
        if ($status === Tenant::SUBSCRIPTION_STATUS_CANCELED) {
            return $this->timestampToCarbon($subscription['canceled_at'] ?? null)
                ?? $this->timestampToCarbon($subscription['ended_at'] ?? null)
                ?? $this->timestampToCarbon($subscription['current_period_end'] ?? null)
                ?? now();
        }

        if ($cancelAtPeriodEnd) {
            return $this->timestampToCarbon($subscription['current_period_end'] ?? null);
        }

        return null;
    }

    private function timestampToCarbon(mixed $timestamp): ?Carbon
    {
        if (! is_int($timestamp) && ! (is_string($timestamp) && ctype_digit($timestamp))) {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $timestamp);
    }

    private function sameTimestamp(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return $actual === null && $expected === null;
        }

        if (! $actual instanceof Carbon || ! $expected instanceof Carbon) {
            return false;
        }

        return $actual->equalTo($expected);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<int|string>  $path
     */
    private function valueAtPath(array $source, array $path): mixed
    {
        $value = $source;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
