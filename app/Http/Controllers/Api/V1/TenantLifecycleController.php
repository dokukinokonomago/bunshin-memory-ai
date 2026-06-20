<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveTenantRequest;
use App\Http\Requests\ExportTenantRequest;
use App\Models\Category;
use App\Models\Memory;
use App\Models\PersonalAccessToken;
use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\TenantMemberInvitation;
use App\Models\User;
use App\Support\Billing\BillingProviderException;
use App\Support\Billing\StripeBillingClient;
use App\Support\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantLifecycleController extends Controller
{
    private const ACCOUNT_NOT_ACTIVE_MESSAGE = 'Account is not active.';

    private const ARCHIVE_SCHEDULED_MESSAGE = 'Tenant archive has been scheduled.';

    private const SCHEDULED_DELETION_DAYS = 30;

    private const BILLING_CANCELLATION_POLICY = 'immediate_no_proration_no_refund';

    public function __construct(
        private readonly SecurityEventLogger $securityEvents,
        private readonly StripeBillingClient $stripe,
    ) {}

    /**
     * @throws ValidationException
     */
    public function export(ExportTenantRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('tenant');

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            return $this->tenantContextRequiredResponse();
        }

        if (! $user->hasActiveAccount()) {
            return response()->json([
                'message' => self::ACCOUNT_NOT_ACTIVE_MESSAGE,
            ], 403);
        }

        if (! $user->isTenantOwner()) {
            $this->logTenantExport(
                request: $request,
                tenant: $tenant,
                user: $user,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: [
                    'reason' => 'owner_required',
                    'role' => $user->role,
                ],
            );

            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $data = $request->validated();

        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            $this->logTenantExport(
                request: $request,
                tenant: $tenant,
                user: $user,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: [
                    'reason' => 'invalid_current_password',
                ],
            );

            throw ValidationException::withMessages([
                'current_password' => ['The current password is invalid.'],
            ]);
        }

        $payload = $this->tenantExportPayload($tenant);

        $this->logTenantExport(
            request: $request,
            tenant: $tenant,
            user: $user,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            metadata: [
                'members_count' => $payload['quota']['members_count'],
                'active_memories_count' => $payload['quota']['active_memories_count'],
                'categories_count' => $payload['quota']['categories_count'],
                'invitations_count' => count($payload['invitations']),
            ],
        );

        return response()->json([
            'data' => $payload,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function archive(ArchiveTenantRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('tenant');

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            return $this->tenantContextRequiredResponse();
        }

        if (! $user->hasActiveAccount()) {
            return response()->json([
                'message' => self::ACCOUNT_NOT_ACTIVE_MESSAGE,
            ], 403);
        }

        if (! $user->isTenantOwner()) {
            $this->logTenantArchive(
                request: $request,
                tenant: $tenant,
                user: $user,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: [
                    'reason' => 'owner_required',
                    'role' => $user->role,
                ],
            );

            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $data = $request->validated();

        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            $this->logTenantArchive(
                request: $request,
                tenant: $tenant,
                user: $user,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: [
                    'reason' => 'invalid_current_password',
                ],
            );

            throw ValidationException::withMessages([
                'current_password' => ['The current password is invalid.'],
            ]);
        }

        $expectedConfirmation = 'ARCHIVE '.$tenant->slug;

        if ((string) $data['confirmation'] !== $expectedConfirmation) {
            $this->logTenantArchive(
                request: $request,
                tenant: $tenant,
                user: $user,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: [
                    'reason' => 'invalid_confirmation',
                    'expected_confirmation' => $expectedConfirmation,
                ],
            );

            throw ValidationException::withMessages([
                'confirmation' => ['The confirmation value is invalid.'],
            ]);
        }

        $archiveReason = isset($data['reason']) && is_string($data['reason']) && $data['reason'] !== ''
            ? $data['reason']
            : null;

        /** @var array{archived_at: string, scheduled_deletion_at: string, tokens_revoked: int, secret_unlock_tokens_revoked: int, pending_invitations_revoked: int, previous_plan_key: string|null, previous_subscription_status: string|null} $result */
        $result = DB::transaction(function () use ($tenant, $user, $archiveReason): array {
            /** @var Tenant $lockedTenant */
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTenant->isArchived()) {
                throw ValidationException::withMessages([
                    'tenant' => ['The tenant has already been archived.'],
                ]);
            }

            $now = now();
            $scheduledDeletionAt = $now->copy()->addDays(self::SCHEDULED_DELETION_DAYS);
            $previousPlanKey = $lockedTenant->plan_key;
            $previousSubscriptionStatus = $lockedTenant->subscription_status;

            $userIds = User::query()
                ->where('tenant_id', $lockedTenant->id)
                ->pluck('id');

            $tokensRevoked = PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();

            $secretUnlockTokensRevoked = SecretUnlockToken::query()
                ->whereIn('user_id', $userIds)
                ->delete();

            $pendingInvitationsRevoked = TenantMemberInvitation::query()
                ->where('tenant_id', $lockedTenant->id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->update([
                    'revoked_at' => $now,
                    'updated_at' => $now,
                ]);

            $lockedTenant->forceFill([
                'archived_at' => $now,
                'archived_by_user_id' => $user->id,
                'archive_reason' => $archiveReason,
                'deletion_requested_at' => $now,
                'scheduled_deletion_at' => $scheduledDeletionAt,
                'purged_at' => null,
                'subscription_status' => Tenant::SUBSCRIPTION_STATUS_CANCELED,
                'subscription_ends_at' => $now,
            ])->save();

            return [
                'archived_at' => $now->toAtomString(),
                'scheduled_deletion_at' => $scheduledDeletionAt->toAtomString(),
                'tokens_revoked' => $tokensRevoked,
                'secret_unlock_tokens_revoked' => $secretUnlockTokensRevoked,
                'pending_invitations_revoked' => $pendingInvitationsRevoked,
                'previous_plan_key' => $previousPlanKey,
                'previous_subscription_status' => $previousSubscriptionStatus,
            ];
        });

        $this->logTenantArchive(
            request: $request,
            tenant: $tenant,
            user: $user,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            metadata: [
                'reason' => $archiveReason,
                'scheduled_deletion_days' => self::SCHEDULED_DELETION_DAYS,
                ...$result,
            ],
        );

        $billingProviderCancellation = $this->cancelArchivedTenantBillingSubscription(
            request: $request,
            tenant: $tenant->refresh(),
            user: $user,
            previousPlanKey: $result['previous_plan_key'],
            previousSubscriptionStatus: $result['previous_subscription_status'],
        );

        return response()->json([
            'message' => self::ARCHIVE_SCHEDULED_MESSAGE,
            'data' => [
                'tenant_public_id' => $tenant->public_id,
                'archived_at' => $result['archived_at'],
                'scheduled_deletion_at' => $result['scheduled_deletion_at'],
                'billing_provider_cancellation' => $billingProviderCancellation,
            ],
        ], 202);
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantExportPayload(Tenant $tenant): array
    {
        $members = $tenant->users()
            ->orderByRaw("case role when 'owner' then 0 when 'admin' then 1 else 2 end")
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->userPayload($user))
            ->values()
            ->all();

        $invitations = $tenant->memberInvitations()
            ->with(['invitedBy', 'acceptedUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TenantMemberInvitation $invitation): array => $this->invitationPayload($invitation))
            ->values()
            ->all();

        return [
            'exported_at' => now()->toAtomString(),
            'tenant' => $this->tenantPayload($tenant),
            'members' => $members,
            'invitations' => $invitations,
            'quota' => [
                'members_count' => count($members),
                'active_memories_count' => Memory::query()
                    ->where('tenant_id', $tenant->id)
                    ->count(),
                'categories_count' => Category::query()
                    ->where('tenant_id', $tenant->id)
                    ->count(),
            ],
            'memory_inventory' => $this->memoryInventory($tenant),
            'security_event_summary' => $this->securityEventSummary($tenant),
        ];
    }

    /**
     * @return list<array{
     *     owner_user_public_id: string,
     *     visibility: string,
     *     category_public_id: string|null,
     *     period_key: string|null,
     *     count: int
     * }>
     */
    private function memoryInventory(Tenant $tenant): array
    {
        return DB::table('memories')
            ->leftJoin('users as owners', 'owners.id', '=', 'memories.owner_user_id')
            ->leftJoin('categories', 'categories.id', '=', 'memories.category_id')
            ->where('memories.tenant_id', $tenant->id)
            ->whereNull('memories.deleted_at')
            ->select([
                'owners.public_id as owner_user_public_id',
                'memories.visibility',
                'categories.public_id as category_public_id',
                'memories.period_key',
            ])
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('owners.public_id', 'memories.visibility', 'categories.public_id', 'memories.period_key')
            ->orderBy('owners.public_id')
            ->orderBy('memories.visibility')
            ->orderBy('categories.public_id')
            ->orderBy('memories.period_key')
            ->get()
            ->map(static fn (object $row): array => [
                'owner_user_public_id' => (string) $row->owner_user_public_id,
                'visibility' => (string) $row->visibility,
                'category_public_id' => $row->category_public_id === null ? null : (string) $row->category_public_id,
                'period_key' => $row->period_key === null ? null : (string) $row->period_key,
                'count' => (int) $row->aggregate_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     event_type: string,
     *     outcome: string,
     *     count: int,
     *     last_seen_at: string|null
     * }>
     */
    private function securityEventSummary(Tenant $tenant): array
    {
        return DB::table('security_events')
            ->where('tenant_id', $tenant->id)
            ->select(['event_type', 'outcome'])
            ->selectRaw('COUNT(*) as aggregate_count')
            ->selectRaw('MAX(created_at) as last_seen_at')
            ->groupBy('event_type', 'outcome')
            ->orderBy('event_type')
            ->orderBy('outcome')
            ->get()
            ->map(static fn (object $row): array => [
                'event_type' => (string) $row->event_type,
                'outcome' => (string) $row->outcome,
                'count' => (int) $row->aggregate_count,
                'last_seen_at' => $row->last_seen_at === null
                    ? null
                    : Carbon::parse((string) $row->last_seen_at)->toAtomString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'account_status' => $user->account_status,
            'is_email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantPayload(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'public_id' => $tenant->public_id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan_key' => $tenant->plan_key,
            'subscription_status' => $tenant->subscription_status,
            'has_active_plan' => $tenant->hasActivePlan(),
            'trial_ends_at' => $tenant->trial_ends_at?->toAtomString(),
            'subscription_ends_at' => $tenant->subscription_ends_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationPayload(TenantMemberInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'public_id' => $invitation->public_id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'status' => $invitation->status(),
            'invited_by_user_id' => $invitation->invited_by_user_id,
            'invited_by_user_public_id' => $invitation->invitedBy?->public_id,
            'accepted_user_id' => $invitation->accepted_user_id,
            'accepted_user_public_id' => $invitation->acceptedUser?->public_id,
            'expires_at' => $invitation->expires_at?->toAtomString(),
            'accepted_at' => $invitation->accepted_at?->toAtomString(),
            'revoked_at' => $invitation->revoked_at?->toAtomString(),
            'created_at' => $invitation->created_at?->toAtomString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logTenantExport(
        Request $request,
        Tenant $tenant,
        User $user,
        string $outcome,
        array $metadata = [],
    ): void {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TENANT_EXPORT_REQUEST,
            outcome: $outcome,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logTenantArchive(
        Request $request,
        Tenant $tenant,
        User $user,
        string $outcome,
        array $metadata = [],
    ): void {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TENANT_ARCHIVE,
            outcome: $outcome,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
            metadata: $metadata,
        );
    }

    /**
     * @return array{status: string, provider?: string, reason?: string}
     */
    private function cancelArchivedTenantBillingSubscription(
        Request $request,
        Tenant $tenant,
        User $user,
        ?string $previousPlanKey,
        ?string $previousSubscriptionStatus,
    ): array {
        if (! filter_var(config('bunshin.billing.enabled'), FILTER_VALIDATE_BOOL)) {
            return $this->skipBillingSubscriptionCancellation(
                request: $request,
                tenant: $tenant,
                user: $user,
                provider: null,
                reason: 'billing_disabled',
                previousPlanKey: $previousPlanKey,
                previousSubscriptionStatus: $previousSubscriptionStatus,
            );
        }

        $provider = config('bunshin.billing.provider');

        if (! is_string($provider) || trim($provider) === '') {
            return $this->skipBillingSubscriptionCancellation(
                request: $request,
                tenant: $tenant,
                user: $user,
                provider: null,
                reason: 'provider_missing',
                previousPlanKey: $previousPlanKey,
                previousSubscriptionStatus: $previousSubscriptionStatus,
            );
        }

        $provider = trim($provider);

        if ($provider !== 'stripe') {
            return $this->skipBillingSubscriptionCancellation(
                request: $request,
                tenant: $tenant,
                user: $user,
                provider: $provider,
                reason: 'provider_unsupported',
                previousPlanKey: $previousPlanKey,
                previousSubscriptionStatus: $previousSubscriptionStatus,
            );
        }

        if ($tenant->billing_provider === null || $tenant->billing_provider === '') {
            return $this->skipBillingSubscriptionCancellation(
                request: $request,
                tenant: $tenant,
                user: $user,
                provider: $provider,
                reason: 'missing_billing_provider',
                previousPlanKey: $previousPlanKey,
                previousSubscriptionStatus: $previousSubscriptionStatus,
            );
        }

        if ($tenant->billing_provider !== $provider) {
            return $this->skipBillingSubscriptionCancellation(
                request: $request,
                tenant: $tenant,
                user: $user,
                provider: $provider,
                reason: 'provider_mismatch',
                previousPlanKey: $previousPlanKey,
                previousSubscriptionStatus: $previousSubscriptionStatus,
            );
        }

        $subscriptionId = is_string($tenant->billing_subscription_id) ? trim($tenant->billing_subscription_id) : '';

        if ($subscriptionId === '') {
            return $this->skipBillingSubscriptionCancellation(
                request: $request,
                tenant: $tenant,
                user: $user,
                provider: $provider,
                reason: 'missing_billing_subscription',
                previousPlanKey: $previousPlanKey,
                previousSubscriptionStatus: $previousSubscriptionStatus,
            );
        }

        foreach ([
            'bunshin.billing.providers.stripe.secret_key',
            'bunshin.billing.providers.stripe.api_base_url',
        ] as $configKey) {
            $value = config($configKey);

            if (! is_string($value) || trim($value) === '') {
                return $this->failBillingSubscriptionCancellation(
                    request: $request,
                    tenant: $tenant,
                    user: $user,
                    provider: $provider,
                    reason: 'provider_configuration_incomplete',
                    previousPlanKey: $previousPlanKey,
                    previousSubscriptionStatus: $previousSubscriptionStatus,
                );
            }
        }

        try {
            $this->stripe->cancelSubscriptionImmediately($subscriptionId);
        } catch (BillingProviderException) {
            return $this->failBillingSubscriptionCancellation(
                request: $request,
                tenant: $tenant,
                user: $user,
                provider: $provider,
                reason: 'provider_request_failed',
                previousPlanKey: $previousPlanKey,
                previousSubscriptionStatus: $previousSubscriptionStatus,
            );
        }

        $tenant->forceFill([
            'billing_cancel_at_period_end' => false,
            'billing_last_synced_at' => now(),
        ]);
        $changedFields = array_keys($tenant->getDirty());
        $tenant->save();

        $this->logBillingSubscriptionCancellation(
            request: $request,
            tenant: $tenant,
            user: $user,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            provider: $provider,
            result: 'succeeded',
            reason: 'provider_cancelled',
            previousPlanKey: $previousPlanKey,
            previousSubscriptionStatus: $previousSubscriptionStatus,
            changedFields: $changedFields,
        );

        return [
            'status' => 'succeeded',
            'provider' => $provider,
        ];
    }

    /**
     * @return array{status: string, provider?: string, reason: string}
     */
    private function skipBillingSubscriptionCancellation(
        Request $request,
        Tenant $tenant,
        User $user,
        ?string $provider,
        string $reason,
        ?string $previousPlanKey,
        ?string $previousSubscriptionStatus,
    ): array {
        $this->logBillingSubscriptionCancellation(
            request: $request,
            tenant: $tenant,
            user: $user,
            outcome: SecurityEvent::OUTCOME_SKIPPED,
            provider: $provider,
            result: 'skipped',
            reason: $reason,
            previousPlanKey: $previousPlanKey,
            previousSubscriptionStatus: $previousSubscriptionStatus,
        );

        return array_filter([
            'status' => 'skipped',
            'provider' => $provider,
            'reason' => $reason,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{status: string, provider: string, reason: string}
     */
    private function failBillingSubscriptionCancellation(
        Request $request,
        Tenant $tenant,
        User $user,
        string $provider,
        string $reason,
        ?string $previousPlanKey,
        ?string $previousSubscriptionStatus,
    ): array {
        $this->logBillingSubscriptionCancellation(
            request: $request,
            tenant: $tenant,
            user: $user,
            outcome: SecurityEvent::OUTCOME_FAILURE,
            provider: $provider,
            result: 'requires_operator_review',
            reason: $reason,
            previousPlanKey: $previousPlanKey,
            previousSubscriptionStatus: $previousSubscriptionStatus,
        );

        return [
            'status' => 'requires_operator_review',
            'provider' => $provider,
            'reason' => $reason,
        ];
    }

    /**
     * @param  list<string>  $changedFields
     */
    private function logBillingSubscriptionCancellation(
        Request $request,
        Tenant $tenant,
        User $user,
        string $outcome,
        ?string $provider,
        string $result,
        string $reason,
        ?string $previousPlanKey,
        ?string $previousSubscriptionStatus,
        array $changedFields = [],
    ): void {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_BILLING_SUBSCRIPTION_CANCEL_REQUEST,
            outcome: $outcome,
            tenant: $tenant,
            user: $user,
            metadata: [
                'provider' => $provider,
                'archive_cancellation_policy' => self::BILLING_CANCELLATION_POLICY,
                'result' => $result,
                'reason' => $reason,
                'previous_plan_key' => $previousPlanKey,
                'previous_subscription_status' => $previousSubscriptionStatus,
                'changed_fields' => $changedFields === [] ? null : $changedFields,
            ],
        );
    }

    private function tenantContextRequiredResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Tenant context is required for authenticated API access.',
        ], 403);
    }
}
