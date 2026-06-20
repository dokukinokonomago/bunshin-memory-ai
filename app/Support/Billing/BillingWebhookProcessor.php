<?php

namespace App\Support\Billing;

use App\Models\BillingWebhookEvent;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Support\SecurityEventLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BillingWebhookProcessor
{
    public function __construct(private readonly SecurityEventLogger $securityEvents) {}

    /**
     * @param  array<string, mixed>  $object
     * @return array{event: BillingWebhookEvent, duplicate: bool}
     */
    public function accept(
        Request $request,
        string $provider,
        string $eventId,
        string $eventType,
        bool $livemode,
        array $object,
        string $payloadHash,
    ): array {
        $existing = BillingWebhookEvent::query()
            ->where('billing_provider', $provider)
            ->where('provider_event_id', $eventId)
            ->first();

        if ($existing instanceof BillingWebhookEvent) {
            return [
                'event' => $existing,
                'duplicate' => true,
            ];
        }

        try {
            $event = BillingWebhookEvent::query()->create([
                'billing_provider' => $provider,
                'provider_event_id' => $eventId,
                'event_type' => $eventType,
                'livemode' => $livemode,
                'billing_customer_id' => $this->extractCustomerId($object),
                'billing_subscription_id' => $this->extractSubscriptionId($eventType, $object),
                'payload_hash' => $payloadHash,
                'received_at' => now(),
                'processing_status' => BillingWebhookEvent::STATUS_RECEIVED,
            ]);
        } catch (QueryException) {
            $event = BillingWebhookEvent::query()
                ->where('billing_provider', $provider)
                ->where('provider_event_id', $eventId)
                ->firstOrFail();

            return [
                'event' => $event,
                'duplicate' => true,
            ];
        }

        $this->processEvent($request, $event, $provider, $eventType, $object);

        return [
            'event' => $event->refresh(),
            'duplicate' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function processEvent(
        Request $request,
        BillingWebhookEvent $event,
        string $provider,
        string $eventType,
        array $object,
    ): void {
        if ($eventType === 'checkout.session.completed') {
            $this->processCheckoutSessionCompleted($request, $event, $provider, $eventType, $object);

            return;
        }

        if (str_starts_with($eventType, 'customer.subscription.')) {
            $this->processSubscriptionEvent($request, $event, $provider, $eventType, $object);

            return;
        }

        $this->ignoreEvent(
            request: $request,
            event: $event,
            tenant: null,
            errorCode: 'unsupported_event_type',
            errorMessage: 'Billing webhook event type is not handled.',
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function processCheckoutSessionCompleted(
        Request $request,
        BillingWebhookEvent $event,
        string $provider,
        string $eventType,
        array $object,
    ): void {
        $tenantPublicId = $this->extractTenantPublicId($object);
        $tenant = is_string($tenantPublicId)
            ? Tenant::query()->where('public_id', $tenantPublicId)->first()
            : null;

        if (! $tenant instanceof Tenant) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: null,
                errorCode: 'tenant_reference_missing',
                errorMessage: 'Verified checkout session did not match a tenant.',
            );

            return;
        }

        $customerId = $this->extractCustomerId($object);
        $subscriptionId = $this->extractSubscriptionId($eventType, $object);

        if ($this->tenantUsesDifferentProvider($tenant, $provider)) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'provider_mismatch',
                errorMessage: 'Tenant is linked to a different billing provider.',
            );

            return;
        }

        if ($this->tenantHasDifferentCustomer($tenant, $customerId)) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'customer_mismatch',
                errorMessage: 'Billing customer did not match the tenant.',
            );

            return;
        }

        if ($customerId === null || $subscriptionId === null) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'billing_reference_missing',
                errorMessage: 'Verified checkout session did not include billing linkage.',
            );

            return;
        }

        $priceId = $this->extractPriceId($object);
        $status = $this->extractProviderStatus($eventType, $object) ?? Tenant::SUBSCRIPTION_STATUS_ACTIVE;

        $this->syncTenantFromProviderObject(
            request: $request,
            event: $event,
            tenant: $tenant,
            provider: $provider,
            providerEventType: $eventType,
            object: $object,
            customerId: $customerId,
            subscriptionId: $subscriptionId,
            priceId: $priceId,
            providerStatus: $status,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function processSubscriptionEvent(
        Request $request,
        BillingWebhookEvent $event,
        string $provider,
        string $eventType,
        array $object,
    ): void {
        $customerId = $this->extractCustomerId($object);
        $subscriptionId = $this->extractSubscriptionId($eventType, $object);

        if ($subscriptionId === null) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: null,
                errorCode: 'subscription_reference_missing',
                errorMessage: 'Verified subscription webhook did not include a subscription id.',
            );

            return;
        }

        $tenant = $this->tenantForBillingReference(
            provider: $provider,
            customerId: $customerId,
            subscriptionId: $subscriptionId,
            tenantPublicId: $this->extractTenantPublicId($object),
        );

        if (! $tenant instanceof Tenant) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: null,
                errorCode: 'billing_reference_unknown',
                errorMessage: 'Billing reference did not match a tenant.',
            );

            return;
        }

        if ($this->tenantHasDifferentSubscription($tenant, $subscriptionId)) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'subscription_mismatch',
                errorMessage: 'Billing subscription did not match the tenant.',
            );

            return;
        }

        $priceId = $this->extractPriceId($object) ?? $tenant->billing_price_id;
        $status = $eventType === 'customer.subscription.deleted'
            ? Tenant::SUBSCRIPTION_STATUS_CANCELED
            : $this->extractProviderStatus($eventType, $object);

        $this->syncTenantFromProviderObject(
            request: $request,
            event: $event,
            tenant: $tenant,
            provider: $provider,
            providerEventType: $eventType,
            object: $object,
            customerId: $customerId,
            subscriptionId: $subscriptionId,
            priceId: is_string($priceId) && $priceId !== '' ? $priceId : null,
            providerStatus: $status,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function syncTenantFromProviderObject(
        Request $request,
        BillingWebhookEvent $event,
        Tenant $tenant,
        string $provider,
        string $providerEventType,
        array $object,
        ?string $customerId,
        string $subscriptionId,
        ?string $priceId,
        ?string $providerStatus,
    ): void {
        $event->forceFill([
            'tenant_id' => $tenant->id,
            'billing_customer_id' => $customerId,
            'billing_subscription_id' => $subscriptionId,
        ])->save();

        if ($tenant->isArchived()) {
            $this->ignoreEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'tenant_archived',
                errorMessage: 'Tenant is archived; billing webhook did not reactivate it.',
            );

            return;
        }

        if ($priceId === null) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'price_reference_missing',
                errorMessage: 'Verified billing webhook did not include a price reference.',
            );

            return;
        }

        $planKey = $this->planKeyForPrice($provider, $priceId);

        if ($planKey === null) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'price_mapping_unknown',
                errorMessage: 'Billing price is not mapped to a local plan.',
            );

            return;
        }

        $localStatus = $this->localStatusForProviderStatus($providerStatus);

        if ($localStatus === null) {
            $this->failEvent(
                request: $request,
                event: $event,
                tenant: $tenant,
                errorCode: 'subscription_status_unknown',
                errorMessage: 'Billing subscription status is not mapped.',
            );

            return;
        }

        $cancelAtPeriodEnd = (bool) ($object['cancel_at_period_end'] ?? false);

        $tenant->forceFill([
            'billing_provider' => $provider,
            'billing_customer_id' => $customerId,
            'billing_subscription_id' => $subscriptionId,
            'billing_price_id' => $priceId,
            'billing_cancel_at_period_end' => $cancelAtPeriodEnd,
            'billing_last_synced_at' => now(),
            'plan_key' => $planKey,
            'subscription_status' => $localStatus,
            'trial_ends_at' => $localStatus === Tenant::SUBSCRIPTION_STATUS_TRIALING
                ? $this->timestampToCarbon($object['trial_end'] ?? null)
                : null,
            'subscription_ends_at' => $this->subscriptionEndsAt($localStatus, $cancelAtPeriodEnd, $object),
        ])->save();

        $this->finishEvent(
            request: $request,
            event: $event,
            tenant: $tenant->refresh(),
            status: BillingWebhookEvent::STATUS_PROCESSED,
            errorCode: null,
            errorMessage: null,
            metadata: [
                'plan_key' => $planKey,
                'subscription_status' => $localStatus,
                'provider_event_type' => $providerEventType,
            ],
        );
    }

    private function tenantForBillingReference(
        string $provider,
        ?string $customerId,
        ?string $subscriptionId,
        ?string $tenantPublicId,
    ): ?Tenant {
        if ($subscriptionId !== null) {
            $tenant = Tenant::query()
                ->where('billing_provider', $provider)
                ->where('billing_subscription_id', $subscriptionId)
                ->first();

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        if ($customerId !== null) {
            $tenant = Tenant::query()
                ->where('billing_provider', $provider)
                ->where('billing_customer_id', $customerId)
                ->first();

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        if ($tenantPublicId !== null) {
            $tenant = Tenant::query()
                ->where('public_id', $tenantPublicId)
                ->first();

            if ($tenant instanceof Tenant && ! $this->tenantUsesDifferentProvider($tenant, $provider)) {
                return $tenant;
            }
        }

        return null;
    }

    private function tenantUsesDifferentProvider(Tenant $tenant, string $provider): bool
    {
        return is_string($tenant->billing_provider)
            && $tenant->billing_provider !== ''
            && $tenant->billing_provider !== $provider;
    }

    private function tenantHasDifferentCustomer(Tenant $tenant, ?string $customerId): bool
    {
        return $customerId !== null
            && is_string($tenant->billing_customer_id)
            && $tenant->billing_customer_id !== ''
            && $tenant->billing_customer_id !== $customerId;
    }

    private function tenantHasDifferentSubscription(Tenant $tenant, string $subscriptionId): bool
    {
        return is_string($tenant->billing_subscription_id)
            && $tenant->billing_subscription_id !== ''
            && $tenant->billing_subscription_id !== $subscriptionId;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function extractCustomerId(array $object): ?string
    {
        return $this->stringValue($object['customer'] ?? null)
            ?? $this->stringValue($this->valueAtPath($object, ['customer', 'id']));
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function extractSubscriptionId(string $eventType, array $object): ?string
    {
        if (str_starts_with($eventType, 'customer.subscription.')) {
            return $this->stringValue($object['id'] ?? null);
        }

        return $this->stringValue($object['subscription'] ?? null)
            ?? $this->stringValue($this->valueAtPath($object, ['subscription', 'id']));
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function extractTenantPublicId(array $object): ?string
    {
        return $this->stringValue($object['client_reference_id'] ?? null)
            ?? $this->stringValue($this->valueAtPath($object, ['metadata', 'tenant_public_id']))
            ?? $this->stringValue($this->valueAtPath($object, ['subscription', 'metadata', 'tenant_public_id']));
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function extractPriceId(array $object): ?string
    {
        foreach ([
            ['items', 'data', 0, 'price', 'id'],
            ['subscription', 'items', 'data', 0, 'price', 'id'],
            ['line_items', 'data', 0, 'price', 'id'],
            ['lines', 'data', 0, 'price', 'id'],
        ] as $path) {
            $value = $this->valueAtPath($object, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function extractProviderStatus(string $eventType, array $object): ?string
    {
        if (str_starts_with($eventType, 'customer.subscription.')) {
            return $this->stringValue($object['status'] ?? null);
        }

        return $this->stringValue($this->valueAtPath($object, ['subscription', 'status']))
            ?? ($this->stringValue($object['status'] ?? null) === 'complete'
                ? Tenant::SUBSCRIPTION_STATUS_ACTIVE
                : null);
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
     * @param  array<string, mixed>  $object
     */
    private function subscriptionEndsAt(string $status, bool $cancelAtPeriodEnd, array $object): ?Carbon
    {
        if ($status === Tenant::SUBSCRIPTION_STATUS_CANCELED) {
            return $this->timestampToCarbon($object['canceled_at'] ?? null)
                ?? $this->timestampToCarbon($object['ended_at'] ?? null)
                ?? $this->timestampToCarbon($object['current_period_end'] ?? null)
                ?? now();
        }

        if ($cancelAtPeriodEnd) {
            return $this->timestampToCarbon($object['current_period_end'] ?? null);
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

    private function failEvent(
        Request $request,
        BillingWebhookEvent $event,
        ?Tenant $tenant,
        string $errorCode,
        string $errorMessage,
    ): void {
        $this->finishEvent(
            request: $request,
            event: $event,
            tenant: $tenant,
            status: BillingWebhookEvent::STATUS_FAILED,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }

    private function ignoreEvent(
        Request $request,
        BillingWebhookEvent $event,
        ?Tenant $tenant,
        string $errorCode,
        string $errorMessage,
    ): void {
        $this->finishEvent(
            request: $request,
            event: $event,
            tenant: $tenant,
            status: BillingWebhookEvent::STATUS_IGNORED,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function finishEvent(
        Request $request,
        BillingWebhookEvent $event,
        ?Tenant $tenant,
        string $status,
        ?string $errorCode,
        ?string $errorMessage,
        array $metadata = [],
    ): void {
        $event->forceFill([
            'tenant_id' => $tenant?->id ?? $event->tenant_id,
            'processed_at' => now(),
            'processing_status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage === null ? null : Str::limit($errorMessage, 512, ''),
        ])->save();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_BILLING_WEBHOOK_SYNC,
            outcome: $status === BillingWebhookEvent::STATUS_PROCESSED
                ? SecurityEvent::OUTCOME_SUCCESS
                : SecurityEvent::OUTCOME_FAILURE,
            tenant: $tenant,
            metadata: [
                'provider' => $event->billing_provider,
                'provider_event_type' => $event->event_type,
                'processing_status' => $status,
                'reason' => $errorCode,
                ...$metadata,
            ],
        );
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
