<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTenantMemberInvitationRequest;
use App\Http\Requests\ForceSecretUnlockPasswordRotationRequest;
use App\Http\Requests\StoreTenantMemberInvitationRequest;
use App\Http\Requests\UpdateTenantMemberAccountStatusRequest;
use App\Http\Requests\UpdateTenantMemberRoleRequest;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\TenantMemberInvitation;
use App\Models\User;
use App\Notifications\TenantMemberInvitationNotification;
use App\Support\NewAccessToken;
use App\Support\ScopedPublicIdResolver;
use App\Support\SecurityEventLogger;
use App\Support\TenantUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantMemberController extends Controller
{
    private const INVITATION_TTL_DAYS = 7;

    public function __construct(private readonly SecurityEventLogger $securityEvents) {}

    public function members(Request $request): JsonResponse
    {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $members = $tenant->users()
            ->orderByRaw("case role when 'owner' then 0 when 'admin' then 1 else 2 end")
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->userPayload($user))
            ->values()
            ->all();

        return response()->json([
            'data' => $members,
        ]);
    }

    public function invitations(Request $request): JsonResponse
    {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $invitations = $tenant->memberInvitations()
            ->with(['invitedBy', 'acceptedUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TenantMemberInvitation $invitation): array => $this->invitationPayload($invitation))
            ->values()
            ->all();

        return response()->json([
            'data' => $invitations,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function invite(StoreTenantMemberInvitationRequest $request): JsonResponse
    {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        /** @var User $manager */
        $manager = $request->user();
        $data = $request->validated();
        $role = (string) $data['role'];
        $email = (string) $data['email'];

        $this->ensureManagerCanAssignRole($manager, $role);
        $this->ensureNoActiveInvitation($tenant, $email);

        $plainToken = Str::random(40);

        $invitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $manager->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
        ]);

        $inviteToken = $invitation->public_id.'|'.$plainToken;

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TENANT_INVITATION_CREATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $manager,
            metadata: [
                'resource_type' => 'tenant_member_invitation',
                'resource_public_id' => $invitation->public_id,
                'target_role' => $role,
            ],
        );

        Notification::route('mail', $email)->notify(new TenantMemberInvitationNotification(
            tenantName: $tenant->name,
            inviterName: $manager->name,
            role: $role,
            inviteToken: $inviteToken,
            expiresAt: $invitation->expires_at,
        ));

        return response()->json([
            'data' => [
                ...$this->invitationPayload($invitation),
                'invite_token' => $inviteToken,
            ],
        ], 201);
    }

    /**
     * @throws ValidationException
     */
    public function acceptInvitation(AcceptTenantMemberInvitationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $invitation = $this->validInvitationForToken((string) $data['token']);

        if (! $invitation instanceof TenantMemberInvitation) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_TENANT_INVITATION_ACCEPT,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: [
                    'reason' => 'invalid_or_expired_token',
                ],
            );

            throw ValidationException::withMessages([
                'token' => ['The invitation token is invalid or expired.'],
            ]);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_TENANT_INVITATION_ACCEPT,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $invitation->tenant,
                subjectEmail: $invitation->email,
                metadata: [
                    'reason' => 'email_already_exists',
                    'invitation_id' => $invitation->id,
                ],
            );

            throw ValidationException::withMessages([
                'email' => ['A user with the invitation email already exists.'],
            ]);
        }

        /** @var array{user: User, tenant: Tenant, token: NewAccessToken} $result */
        $result = DB::transaction(function () use ($data, $invitation): array {
            /** @var TenantMemberInvitation $lockedInvitation */
            $lockedInvitation = TenantMemberInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedInvitation->isPending()) {
                throw ValidationException::withMessages([
                    'token' => ['The invitation token is invalid or expired.'],
                ]);
            }

            /** @var Tenant $tenant */
            $tenant = $lockedInvitation->tenant()->firstOrFail();

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'role' => $lockedInvitation->role,
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                'name' => $data['name'],
                'email' => $lockedInvitation->email,
                'password' => $data['password'],
            ]);

            $lockedInvitation->forceFill([
                'accepted_user_id' => $user->id,
                'accepted_at' => now(),
            ])->save();

            return [
                'user' => $user,
                'tenant' => $tenant,
                'token' => $user->createApiToken('tenant-invite'),
            ];
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TENANT_INVITATION_ACCEPT,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $result['tenant'],
            user: $result['user'],
            subjectEmail: $result['user']->email,
            metadata: [
                'invitation_id' => $invitation->id,
            ],
        );

        $result['user']->sendEmailVerificationNotification();

        return $this->authTokenResponse($result['user'], $result['tenant'], $result['token']);
    }

    public function revokeInvitation(Request $request, int|string $invitation): Response|JsonResponse
    {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        /** @var User $manager */
        $manager = $request->user();
        $invitation = $this->resolveInvitationForManager($manager, $invitation);

        if ($invitation->accepted_at !== null) {
            throw ValidationException::withMessages([
                'invitation' => ['Accepted invitations cannot be revoked.'],
            ]);
        }

        $wasAlreadyRevoked = $invitation->revoked_at !== null;

        if ($invitation->revoked_at === null) {
            $invitation->forceFill([
                'revoked_at' => now(),
            ])->save();
        }

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TENANT_INVITATION_REVOKE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $manager,
            metadata: [
                'resource_type' => 'tenant_member_invitation',
                'resource_public_id' => $invitation->public_id,
                'target_role' => $invitation->role,
                'was_already_revoked' => $wasAlreadyRevoked,
            ],
        );

        return response()->noContent();
    }

    /**
     * @throws ValidationException
     */
    public function updateRole(UpdateTenantMemberRoleRequest $request, int|string $member): JsonResponse
    {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        /** @var User $manager */
        $manager = $request->user();
        $member = $this->resolveMemberForManager($manager, $member);
        $role = (string) $request->validated('role');

        $this->ensureCanManageMember($manager, $member);
        $this->ensureManagerCanAssignRole($manager, $role);
        $this->ensureTenantKeepsOwner($tenant, $member, $role);

        $previousRole = $member->role;

        $member->forceFill([
            'role' => $role,
        ])->save();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TENANT_MEMBER_ROLE_CHANGE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $manager,
            metadata: [
                'resource_type' => 'tenant_member',
                'subject_user_public_id' => $member->public_id,
                'manager_role' => $manager->role,
                'previous_role' => $previousRole,
                'new_role' => $role,
            ],
        );

        return response()->json([
            'data' => $this->userPayload($member),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function updateAccountStatus(UpdateTenantMemberAccountStatusRequest $request, int|string $member): JsonResponse
    {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        /** @var User $manager */
        $manager = $request->user();
        $member = $this->resolveMemberForManager($manager, $member);
        $data = $request->validated();
        $newStatus = (string) $data['account_status'];
        $statusReason = isset($data['reason']) && is_string($data['reason']) && $data['reason'] !== ''
            ? $data['reason']
            : null;

        if ($manager->is($member)) {
            $this->logAccountStatusChange(
                request: $request,
                manager: $manager,
                member: $member,
                tenant: $tenant,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                newStatus: $newStatus,
                metadata: [
                    'reason' => 'self_target',
                    'requested_reason' => $statusReason,
                ],
            );

            throw ValidationException::withMessages([
                'member' => ['You cannot change your own account status.'],
            ]);
        }

        if ($member->isTenantOwner() && ! $manager->isTenantOwner()) {
            $this->logAccountStatusChange(
                request: $request,
                manager: $manager,
                member: $member,
                tenant: $tenant,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                newStatus: $newStatus,
                metadata: [
                    'reason' => 'owner_boundary',
                    'requested_reason' => $statusReason,
                ],
            );

            throw ValidationException::withMessages([
                'member' => ['Only an owner can manage owner members.'],
            ]);
        }

        if ($this->wouldDeactivateLastActiveOwner($tenant, $member, $newStatus)) {
            $this->logAccountStatusChange(
                request: $request,
                manager: $manager,
                member: $member,
                tenant: $tenant,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                newStatus: $newStatus,
                metadata: [
                    'reason' => 'last_active_owner',
                    'requested_reason' => $statusReason,
                ],
            );

            throw ValidationException::withMessages([
                'account_status' => ['A tenant must keep at least one active owner.'],
            ]);
        }

        $previousStatus = $member->account_status;

        DB::transaction(function () use ($member, $newStatus): void {
            $member->forceFill([
                'account_status' => $newStatus,
            ])->save();

            $member->personalAccessTokens()->delete();
            $member->secretUnlockTokens()->delete();
        });

        $member->refresh();

        $this->logAccountStatusChange(
            request: $request,
            manager: $manager,
            member: $member,
            tenant: $tenant,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            newStatus: $newStatus,
            previousStatus: $previousStatus,
            metadata: ['reason' => $statusReason],
        );

        return response()->json([
            'data' => $this->userPayload($member),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function revokeMember(Request $request, int|string $member): Response|JsonResponse
    {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        /** @var User $manager */
        $manager = $request->user();
        $member = $this->resolveMemberForManager($manager, $member);

        $this->ensureCanManageMember($manager, $member);
        $this->ensureTenantKeepsOwner($tenant, $member, null);

        $memberPublicId = $member->public_id;
        $previousRole = $member->role;
        $tokensRevoked = $member->personalAccessTokens()->count();

        DB::transaction(function () use ($member): void {
            $member->personalAccessTokens()->delete();

            $member->forceFill([
                'tenant_id' => null,
                'role' => User::ROLE_MEMBER,
            ])->save();
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TENANT_MEMBER_REVOKE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $manager,
            metadata: [
                'resource_type' => 'tenant_member',
                'subject_user_public_id' => $memberPublicId,
                'manager_role' => $manager->role,
                'previous_role' => $previousRole,
                'tokens_revoked' => $tokensRevoked,
            ],
        );

        return response()->noContent();
    }

    /**
     * @throws ValidationException
     */
    public function forceSecretUnlockPasswordRotation(
        ForceSecretUnlockPasswordRotationRequest $request,
        int|string $member,
    ): JsonResponse {
        $tenant = $this->managerTenantOrResponse($request);

        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        /** @var User $manager */
        $manager = $request->user();
        $member = $this->resolveMemberForManager($manager, $member);

        if ($manager->is($member)) {
            $this->logSecretUnlockPasswordForceRotation(
                request: $request,
                manager: $manager,
                member: $member,
                tenant: $tenant,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: ['reason' => 'self_target'],
            );

            throw ValidationException::withMessages([
                'member' => ['You cannot force rotation for your own secret unlock password.'],
            ]);
        }

        if ($member->isTenantOwner() && ! $manager->isTenantOwner()) {
            $this->logSecretUnlockPasswordForceRotation(
                request: $request,
                manager: $manager,
                member: $member,
                tenant: $tenant,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                metadata: ['reason' => 'owner_boundary'],
            );

            throw ValidationException::withMessages([
                'member' => ['Only an owner can manage owner members.'],
            ]);
        }

        $data = $request->validated();
        $rotationReason = isset($data['reason']) && is_string($data['reason']) && $data['reason'] !== ''
            ? $data['reason']
            : null;

        DB::transaction(function () use ($member): void {
            $member->forceFill([
                'secret_unlock_password' => null,
            ])->save();

            $member->secretUnlockTokens()->delete();
        });

        $this->logSecretUnlockPasswordForceRotation(
            request: $request,
            manager: $manager,
            member: $member,
            tenant: $tenant,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            metadata: ['reason' => $rotationReason],
        );

        return response()->json([
            'data' => [
                'user_id' => $member->id,
                'user_public_id' => $member->public_id,
                'has_secret_unlock_password' => false,
                'mode' => 'forced_rotation',
            ],
        ]);
    }

    private function managerTenantOrResponse(Request $request): Tenant|JsonResponse
    {
        /** @var User|null $manager */
        $manager = $request->user();

        if (! $manager instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $manager->loadMissing('tenant');
        $tenant = $manager->tenant;

        if ($manager->tenant_id === null || ! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant context is required for authenticated API access.',
            ], 403);
        }

        if (! Gate::forUser($manager)->allows('manage-tenant-members', $tenant)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return $tenant;
    }

    /**
     * @throws ValidationException
     */
    private function ensureNoActiveInvitation(Tenant $tenant, string $email): void
    {
        $exists = $tenant->memberInvitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['A pending invitation already exists for this email.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanManageMember(User $manager, User $member): void
    {
        if ($manager->is($member)) {
            throw ValidationException::withMessages([
                'member' => ['You cannot change your own tenant membership.'],
            ]);
        }

        if ($member->isTenantOwner() && ! $manager->isTenantOwner()) {
            throw ValidationException::withMessages([
                'member' => ['Only an owner can manage owner members.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureManagerCanAssignRole(User $manager, string $role): void
    {
        if ($role === User::ROLE_OWNER && ! $manager->isTenantOwner()) {
            throw ValidationException::withMessages([
                'role' => ['Only an owner can assign the owner role.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureTenantKeepsOwner(Tenant $tenant, User $member, ?string $newRole): void
    {
        $removesOwner = $member->isTenantOwner() && $newRole !== User::ROLE_OWNER;

        if (! $removesOwner) {
            return;
        }

        $hasOtherOwner = $tenant->users()
            ->whereKeyNot($member->id)
            ->where('role', User::ROLE_OWNER)
            ->exists();

        if (! $hasOtherOwner) {
            throw ValidationException::withMessages([
                'role' => ['A tenant must keep at least one owner.'],
            ]);
        }
    }

    private function wouldDeactivateLastActiveOwner(Tenant $tenant, User $member, string $newStatus): bool
    {
        if (! $member->isTenantOwner() || $newStatus === User::ACCOUNT_STATUS_ACTIVE) {
            return false;
        }

        return ! $tenant->users()
            ->whereKeyNot($member->id)
            ->where('role', User::ROLE_OWNER)
            ->where('account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->exists();
    }

    private function resolveMemberForManager(User $manager, int|string $member): User
    {
        $model = ScopedPublicIdResolver::user(TenantUserContext::fromUser($manager), $member);

        if (! $model instanceof User) {
            throw new NotFoundHttpException;
        }

        return $model;
    }

    private function resolveInvitationForManager(User $manager, int|string $invitation): TenantMemberInvitation
    {
        $model = ScopedPublicIdResolver::tenantMemberInvitation(TenantUserContext::fromUser($manager), $invitation);

        if (! $model instanceof TenantMemberInvitation) {
            throw new NotFoundHttpException;
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logSecretUnlockPasswordForceRotation(
        Request $request,
        User $manager,
        User $member,
        Tenant $tenant,
        string $outcome,
        array $metadata = [],
    ): void {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_FORCED_ROTATION,
            outcome: $outcome,
            tenant: $tenant,
            user: $manager,
            subjectEmail: $member->email,
            metadata: [
                'manager_role' => $manager->role,
                'target_user_id' => $member->id,
                'target_user_public_id' => $member->public_id,
                'target_role' => $member->role,
                ...$metadata,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logAccountStatusChange(
        Request $request,
        User $manager,
        User $member,
        Tenant $tenant,
        string $outcome,
        string $newStatus,
        ?string $previousStatus = null,
        array $metadata = [],
    ): void {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_ACCOUNT_STATUS_CHANGE,
            outcome: $outcome,
            tenant: $tenant,
            user: $manager,
            subjectEmail: $member->email,
            metadata: [
                'manager_role' => $manager->role,
                'target_user_id' => $member->id,
                'target_user_public_id' => $member->public_id,
                'target_role' => $member->role,
                'previous_account_status' => $previousStatus ?? $member->account_status,
                'new_account_status' => $newStatus,
                ...$metadata,
            ],
        );
    }

    private function validInvitationForToken(string $token): ?TenantMemberInvitation
    {
        if (! str_contains($token, '|')) {
            return null;
        }

        [$lookup, $plainToken] = explode('|', $token, 2);

        $lookup = trim($lookup);

        if ($lookup === '' || $plainToken === '') {
            return null;
        }

        $query = TenantMemberInvitation::query()->with('tenant');

        $invitation = ctype_digit($lookup)
            ? $query->whereKey((int) $lookup)->first()
            : $query->where('public_id', $lookup)->first();

        if (! $invitation instanceof TenantMemberInvitation) {
            return null;
        }

        if (! hash_equals($invitation->token_hash, hash('sha256', $plainToken))) {
            return null;
        }

        if (! $invitation->isPending()) {
            return null;
        }

        return $invitation;
    }

    private function authTokenResponse(User $user, Tenant $tenant, NewAccessToken $newAccessToken): JsonResponse
    {
        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $newAccessToken->plainTextToken,
                'expires_at' => $newAccessToken->accessToken->expires_at?->toAtomString(),
                'user' => $this->userPayload($user),
                'tenant' => $this->tenantPayload($tenant),
            ],
        ], 201);
    }

    /**
     * @return array{
     *     id: int,
     *     public_id: string|null,
     *     name: string,
     *     email: string,
     *     role: string,
     *     account_status: string,
     *     is_email_verified: bool,
     *     email_verified_at: string|null
     * }
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
     * @return array{
     *     id: int,
     *     public_id: string|null,
     *     name: string,
     *     slug: string,
     *     plan_key: string|null,
     *     subscription_status: string|null,
     *     has_active_plan: bool,
     *     trial_ends_at: string|null,
     *     subscription_ends_at: string|null
     * }
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
     * @return array{
     *     id: int,
     *     public_id: string|null,
     *     email: string,
     *     role: string,
     *     status: string,
     *     invited_by_user_id: int|null,
     *     invited_by_user_public_id: string|null,
     *     accepted_user_id: int|null,
     *     accepted_user_public_id: string|null,
     *     expires_at: string|null,
     *     accepted_at: string|null,
     *     revoked_at: string|null,
     *     created_at: string|null
     * }
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
}
