<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillingCheckoutSessionRequest;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingProviderException;
use App\Support\Billing\BillingWebhookProcessor;
use App\Support\Billing\StripeBillingClient;
use App\Support\Billing\StripeWebhookSignatureVerifier;
use App\Support\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    private const BILLING_UNAVAILABLE_MESSAGE = 'Billing provider is not available.';

    private const EMAIL_VERIFICATION_REQUIRED_MESSAGE = 'Email verification is required for billing management.';

    public function __construct(
        private readonly SecurityEventLogger $securityEvents,
        private readonly StripeBillingClient $stripe,
        private readonly StripeWebhookSignatureVerifier $stripeWebhookSignature,
        private readonly BillingWebhookProcessor $billingWebhooks,
    ) {}

    /**
     * @throws ValidationException
     */
    public function checkout(StoreBillingCheckoutSessionRequest $request): JsonResponse
    {
        $context = $this->billingContextOrResponse($request, SecurityEvent::TYPE_BILLING_CHECKOUT_SESSION_CREATE);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        [$user, $tenant] = $context;
        $provider = $this->configuredProviderOrResponse(
            $request,
            SecurityEvent::TYPE_BILLING_CHECKOUT_SESSION_CREATE,
            $user,
            $tenant,
        );

        if ($provider instanceof JsonResponse) {
            return $provider;
        }

        $planKey = (string) $request->validated('plan_key');
        $priceId = $this->priceIdForPlan($provider, $planKey);

        if ($priceId === null) {
            $this->logBillingEvent(
                request: $request,
                eventType: SecurityEvent::TYPE_BILLING_CHECKOUT_SESSION_CREATE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                metadata: [
                    'reason' => 'unknown_plan',
                    'provider' => $provider,
                    'plan_key' => $planKey,
                ],
            );

            throw ValidationException::withMessages([
                'plan_key' => ['The selected billing plan is not available.'],
            ]);
        }

        if ($this->tenantUsesDifferentProvider($tenant, $provider)) {
            throw ValidationException::withMessages([
                'billing_provider' => ['The tenant is linked to a different billing provider.'],
            ]);
        }

        $customerCreated = false;
        $customerId = is_string($tenant->billing_customer_id) ? $tenant->billing_customer_id : null;

        try {
            if ($customerId === null || $customerId === '') {
                $customerId = $this->stripe->createCustomer($tenant, $user);
                $customerCreated = true;

                $tenant->forceFill([
                    'billing_provider' => $provider,
                    'billing_customer_id' => $customerId,
                ])->save();
            } elseif ($tenant->billing_provider !== $provider) {
                $tenant->forceFill([
                    'billing_provider' => $provider,
                ])->save();
            }

            $url = $this->stripe->createCheckoutSession($tenant->refresh(), $user, $customerId, $priceId);
        } catch (BillingProviderException) {
            $this->logBillingEvent(
                request: $request,
                eventType: SecurityEvent::TYPE_BILLING_CHECKOUT_SESSION_CREATE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                metadata: [
                    'reason' => 'provider_request_failed',
                    'provider' => $provider,
                    'plan_key' => $planKey,
                    'customer_created' => $customerCreated,
                ],
            );

            return response()->json([
                'message' => self::BILLING_UNAVAILABLE_MESSAGE,
            ], 502);
        }

        $this->logBillingEvent(
            request: $request,
            eventType: SecurityEvent::TYPE_BILLING_CHECKOUT_SESSION_CREATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $user,
            metadata: [
                'provider' => $provider,
                'plan_key' => $planKey,
                'customer_created' => $customerCreated,
            ],
        );

        return response()->json([
            'data' => [
                'mode' => 'checkout',
                'provider' => $provider,
                'plan_key' => $planKey,
                'url' => $url,
                'tenant' => $this->tenantBillingPayload($tenant->refresh()),
            ],
        ], 201);
    }

    /**
     * @throws ValidationException
     */
    public function portal(Request $request): JsonResponse
    {
        $context = $this->billingContextOrResponse($request, SecurityEvent::TYPE_BILLING_PORTAL_SESSION_CREATE);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        [$user, $tenant] = $context;
        $provider = $this->configuredProviderOrResponse(
            $request,
            SecurityEvent::TYPE_BILLING_PORTAL_SESSION_CREATE,
            $user,
            $tenant,
        );

        if ($provider instanceof JsonResponse) {
            return $provider;
        }

        if ($this->tenantUsesDifferentProvider($tenant, $provider)) {
            throw ValidationException::withMessages([
                'billing_provider' => ['The tenant is linked to a different billing provider.'],
            ]);
        }

        $customerId = is_string($tenant->billing_customer_id) ? $tenant->billing_customer_id : null;

        if ($customerId === null || $customerId === '') {
            $this->logBillingEvent(
                request: $request,
                eventType: SecurityEvent::TYPE_BILLING_PORTAL_SESSION_CREATE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                metadata: [
                    'reason' => 'missing_billing_customer',
                    'provider' => $provider,
                ],
            );

            throw ValidationException::withMessages([
                'billing_customer' => ['The tenant does not have a billing customer yet.'],
            ]);
        }

        if ($tenant->billing_provider !== $provider) {
            $tenant->forceFill([
                'billing_provider' => $provider,
            ])->save();
        }

        try {
            $url = $this->stripe->createPortalSession($customerId);
        } catch (BillingProviderException) {
            $this->logBillingEvent(
                request: $request,
                eventType: SecurityEvent::TYPE_BILLING_PORTAL_SESSION_CREATE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                metadata: [
                    'reason' => 'provider_request_failed',
                    'provider' => $provider,
                ],
            );

            return response()->json([
                'message' => self::BILLING_UNAVAILABLE_MESSAGE,
            ], 502);
        }

        $this->logBillingEvent(
            request: $request,
            eventType: SecurityEvent::TYPE_BILLING_PORTAL_SESSION_CREATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $user,
            metadata: [
                'provider' => $provider,
            ],
        );

        return response()->json([
            'data' => [
                'mode' => 'portal',
                'provider' => $provider,
                'url' => $url,
                'tenant' => $this->tenantBillingPayload($tenant->refresh()),
            ],
        ], 201);
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        $provider = trim($provider);
        $webhookSecret = $this->configuredWebhookSecretOrResponse($provider);

        if ($webhookSecret instanceof JsonResponse) {
            return $webhookSecret;
        }

        $payload = $request->getContent();

        if (! $this->stripeWebhookSignature->hasValidSignature($request, $payload, $webhookSecret)) {
            return response()->json([
                'message' => 'Invalid billing webhook signature.',
            ], 400);
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return response()->json([
                'message' => 'Invalid billing webhook payload.',
            ], 400);
        }

        $eventId = $decoded['id'] ?? null;
        $eventType = $decoded['type'] ?? null;
        $data = $decoded['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;

        if (! is_string($eventId) || trim($eventId) === ''
            || ! is_string($eventType) || trim($eventType) === ''
            || ! is_array($object)) {
            return response()->json([
                'message' => 'Invalid billing webhook payload.',
            ], 400);
        }

        $result = $this->billingWebhooks->accept(
            request: $request,
            provider: $provider,
            eventId: trim($eventId),
            eventType: trim($eventType),
            livemode: (bool) ($decoded['livemode'] ?? false),
            object: $object,
            payloadHash: hash('sha256', $payload),
        );

        return response()->json([
            'data' => [
                'provider' => $provider,
                'event_type' => trim($eventType),
                'processing_status' => $result['duplicate']
                    ? 'duplicate'
                    : $result['event']->processing_status,
            ],
        ]);
    }

    /**
     * @return array{0: User, 1: Tenant}|JsonResponse
     */
    private function billingContextOrResponse(Request $request, string $eventType): array|JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->loadMissing('tenant');
        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant context is required for authenticated API access.',
            ], 403);
        }

        if (! $user->isTenantOwner()) {
            $this->logBillingEvent(
                request: $request,
                eventType: $eventType,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                metadata: [
                    'reason' => 'owner_required',
                    'role' => $user->role,
                ],
            );

            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $this->logBillingEvent(
                request: $request,
                eventType: $eventType,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                metadata: [
                    'reason' => 'email_not_verified',
                ],
            );

            return response()->json([
                'message' => self::EMAIL_VERIFICATION_REQUIRED_MESSAGE,
            ], 403);
        }

        return [$user, $tenant];
    }

    private function configuredProviderOrResponse(
        Request $request,
        string $eventType,
        User $user,
        Tenant $tenant,
    ): string|JsonResponse {
        if (! filter_var(config('bunshin.billing.enabled'), FILTER_VALIDATE_BOOL)) {
            return $this->billingConfigurationFailureResponse(
                request: $request,
                eventType: $eventType,
                user: $user,
                tenant: $tenant,
                reason: 'billing_disabled',
            );
        }

        $provider = config('bunshin.billing.provider');

        if (! is_string($provider) || trim($provider) === '') {
            return $this->billingConfigurationFailureResponse(
                request: $request,
                eventType: $eventType,
                user: $user,
                tenant: $tenant,
                reason: 'provider_missing',
            );
        }

        $provider = trim($provider);

        if ($provider !== 'stripe') {
            return $this->billingConfigurationFailureResponse(
                request: $request,
                eventType: $eventType,
                user: $user,
                tenant: $tenant,
                reason: 'provider_unsupported',
                provider: $provider,
            );
        }

        foreach ([
            'bunshin.billing.providers.stripe.secret_key' => 'secret_key_missing',
            'bunshin.billing.providers.stripe.api_base_url' => 'api_base_url_missing',
            'bunshin.billing.checkout.success_url' => 'checkout_success_url_missing',
            'bunshin.billing.checkout.cancel_url' => 'checkout_cancel_url_missing',
            'bunshin.billing.portal.return_url' => 'portal_return_url_missing',
        ] as $configKey => $reason) {
            $value = config($configKey);

            if (! is_string($value) || trim($value) === '') {
                return $this->billingConfigurationFailureResponse(
                    request: $request,
                    eventType: $eventType,
                    user: $user,
                    tenant: $tenant,
                    reason: $reason,
                    provider: $provider,
                );
            }
        }

        return $provider;
    }

    private function configuredWebhookSecretOrResponse(string $provider): string|JsonResponse
    {
        if (! filter_var(config('bunshin.billing.enabled'), FILTER_VALIDATE_BOOL)) {
            return response()->json([
                'message' => self::BILLING_UNAVAILABLE_MESSAGE,
            ], 503);
        }

        $configuredProvider = config('bunshin.billing.provider');

        if (! is_string($configuredProvider) || trim($configuredProvider) === '') {
            return response()->json([
                'message' => self::BILLING_UNAVAILABLE_MESSAGE,
            ], 503);
        }

        $configuredProvider = trim($configuredProvider);

        if ($provider !== $configuredProvider || $provider !== 'stripe') {
            return response()->json([
                'message' => self::BILLING_UNAVAILABLE_MESSAGE,
            ], 503);
        }

        $webhookSecret = config('bunshin.billing.providers.stripe.webhook_secret');

        if (! is_string($webhookSecret) || trim($webhookSecret) === '') {
            return response()->json([
                'message' => self::BILLING_UNAVAILABLE_MESSAGE,
            ], 503);
        }

        return trim($webhookSecret);
    }

    private function billingConfigurationFailureResponse(
        Request $request,
        string $eventType,
        User $user,
        Tenant $tenant,
        string $reason,
        ?string $provider = null,
    ): JsonResponse {
        $this->logBillingEvent(
            request: $request,
            eventType: $eventType,
            outcome: SecurityEvent::OUTCOME_FAILURE,
            tenant: $tenant,
            user: $user,
            metadata: [
                'reason' => $reason,
                'provider' => $provider,
            ],
        );

        return response()->json([
            'message' => self::BILLING_UNAVAILABLE_MESSAGE,
        ], 503);
    }

    private function priceIdForPlan(string $provider, string $planKey): ?string
    {
        $pricePlanMap = config("bunshin.billing.price_plan_map.{$provider}", []);

        if (! is_array($pricePlanMap)) {
            return null;
        }

        foreach ($pricePlanMap as $priceId => $mappedPlanKey) {
            if (! is_string($priceId) || $priceId === '') {
                continue;
            }

            if ((string) $mappedPlanKey === $planKey) {
                return $priceId;
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

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logBillingEvent(
        Request $request,
        string $eventType,
        string $outcome,
        Tenant $tenant,
        User $user,
        array $metadata = [],
    ): void {
        $this->securityEvents->log(
            request: $request,
            eventType: $eventType,
            outcome: $outcome,
            tenant: $tenant,
            user: $user,
            metadata: $metadata,
        );
    }

    /**
     * @return array{
     *     public_id: string|null,
     *     plan_key: string|null,
     *     subscription_status: string|null,
     *     has_active_plan: bool
     * }
     */
    private function tenantBillingPayload(Tenant $tenant): array
    {
        return [
            'public_id' => $tenant->public_id,
            'plan_key' => $tenant->plan_key,
            'subscription_status' => $tenant->subscription_status,
            'has_active_plan' => $tenant->hasActivePlan(),
        ];
    }
}
