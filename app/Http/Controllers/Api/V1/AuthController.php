<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\ExportAccountRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Requests\UpdateEmailRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Models\Category;
use App\Models\Memory;
use App\Models\PersonalAccessToken;
use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantMemberInvitation;
use App\Models\User;
use App\Notifications\VerifyEmailChangeNotification;
use App\Support\NewAccessToken;
use App\Support\SecurityEventLogger;
use App\Support\TenantUserContext;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Auth\Events\Verified as EmailVerifiedEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthController extends Controller
{
    private const PASSWORD_RESET_REQUEST_MESSAGE = 'If an account exists for this email, a password reset link has been sent.';

    private const INVALID_ONBOARDING_INVITE_MESSAGE = 'Invalid onboarding invite token.';

    private const EMAIL_VERIFICATION_SENT_MESSAGE = 'Email verification link has been sent.';

    private const EMAIL_ALREADY_VERIFIED_MESSAGE = 'Email is already verified.';

    private const EMAIL_VERIFIED_MESSAGE = 'Email has been verified.';

    private const INVALID_EMAIL_VERIFICATION_LINK_MESSAGE = 'The email verification link is invalid or expired.';

    private const EMAIL_CHANGE_VERIFICATION_SENT_MESSAGE = 'Email change verification link has been sent.';

    private const EMAIL_CHANGED_MESSAGE = 'Email has been changed.';

    private const INVALID_EMAIL_CHANGE_LINK_MESSAGE = 'The email change verification link is invalid or expired.';

    private const ACCOUNT_NOT_ACTIVE_MESSAGE = 'Account is not active.';

    private const TENANT_ARCHIVED_MESSAGE = 'Tenant is archived.';

    public function __construct(private readonly SecurityEventLogger $securityEvents) {}

    public function signup(SignupRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $this->onboardingInviteTokenIsValid($data['invite_token'])) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_SIGNUP,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                subjectEmail: (string) $data['email'],
                metadata: [
                    'reason' => 'invalid_invite_token',
                    'tenant_slug' => $data['tenant_slug'],
                ],
            );

            return response()->json([
                'message' => self::INVALID_ONBOARDING_INVITE_MESSAGE,
            ], 403);
        }

        /** @var array{tenant: Tenant, user: User, token: NewAccessToken} $result */
        $result = DB::transaction(function () use ($data): array {
            $tenant = Tenant::query()->create([
                'name' => $data['tenant_name'],
                'slug' => $data['tenant_slug'],
                'plan_key' => Tenant::PLAN_FREE,
                'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            ]);

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'role' => User::ROLE_OWNER,
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            return [
                'tenant' => $tenant,
                'user' => $user,
                'token' => $user->createApiToken('signup'),
            ];
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_SIGNUP,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $result['tenant'],
            user: $result['user'],
            subjectEmail: $result['user']->email,
            metadata: [
                'tenant_slug' => $result['tenant']->slug,
            ],
        );

        $result['user']->sendEmailVerificationNotification();

        return $this->authTokenResponse($result['user'], $result['tenant'], $result['token']);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->with('tenant')
            ->where('email', $data['email'])
            ->first();

        if (! $user instanceof User || ! Hash::check($data['password'], (string) $user->password)) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_LOGIN,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $this->eventTenant($user),
                user: $user instanceof User ? $user : null,
                subjectEmail: (string) $data['email'],
                metadata: [
                    'reason' => 'invalid_credentials',
                ],
            );

            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ], 401);
        }

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_LOGIN,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'tenant_context_missing',
                ],
            );

            return response()->json([
                'message' => 'Tenant context is required for API login.',
            ], 403);
        }

        if (! $user->hasActiveAccount()) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_LOGIN,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'account_not_active',
                    'account_status' => $user->account_status,
                ],
            );

            return response()->json([
                'message' => self::ACCOUNT_NOT_ACTIVE_MESSAGE,
            ], 403);
        }

        if ($tenant->isArchived()) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_LOGIN,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'tenant_archived',
                ],
            );

            return response()->json([
                'message' => self::TENANT_ARCHIVED_MESSAGE,
            ], 403);
        }

        $newAccessToken = $user->createApiToken('login');

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_LOGIN,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
        );

        return $this->authTokenResponse($user, $tenant, $newAccessToken);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()
            ->with('tenant')
            ->where('email', $data['email'])
            ->first();

        $status = Password::broker()->sendResetLink([
            'email' => $data['email'],
        ]);

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_PASSWORD_RESET_REQUEST,
            outcome: SecurityEvent::OUTCOME_REQUESTED,
            tenant: $this->eventTenant($user),
            user: $user instanceof User ? $user : null,
            subjectEmail: (string) $data['email'],
            metadata: [
                'broker_status' => $status,
            ],
        );

        return response()->json([
            'message' => self::PASSWORD_RESET_REQUEST_MESSAGE,
        ], 202);
    }

    private function authTokenResponse(User $user, Tenant $tenant, NewAccessToken $newAccessToken): JsonResponse
    {
        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $newAccessToken->plainTextToken,
                'expires_at' => $newAccessToken->accessToken->expires_at?->toAtomString(),
                'user' => $this->userPayload($user),
                'tenant' => [
                    ...$this->tenantPayload($tenant),
                ],
            ],
        ], 201);
    }

    private function onboardingInviteTokenIsValid(string $inviteToken): bool
    {
        $configuredToken = config('bunshin.onboarding.invite_token');

        if (! is_string($configuredToken)) {
            return false;
        }

        $configuredToken = trim($configuredToken);

        return $configuredToken !== '' && hash_equals($configuredToken, $inviteToken);
    }

    /**
     * @throws ValidationException
     */
    public function resetPassword(ResetPasswordRequest $request): Response
    {
        $data = $request->validated();
        $resetUser = User::query()
            ->with('tenant')
            ->where('email', $data['email'])
            ->first();

        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password) use (&$resetUser): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->personalAccessTokens()->delete();

                event(new PasswordResetEvent($user));

                $resetUser = $user->loadMissing('tenant');
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_PASSWORD_RESET_COMPLETE,
                outcome: SecurityEvent::OUTCOME_SUCCESS,
                tenant: $this->eventTenant($resetUser),
                user: $resetUser instanceof User ? $resetUser : null,
                subjectEmail: (string) $data['email'],
            );

            return response()->noContent();
        }

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_PASSWORD_RESET_COMPLETE,
            outcome: SecurityEvent::OUTCOME_FAILURE,
            tenant: $this->eventTenant($resetUser),
            user: $resetUser instanceof User ? $resetUser : null,
            subjectEmail: (string) $data['email'],
            metadata: [
                'reason' => 'invalid_or_expired_token',
                'broker_status' => $status,
            ],
        );

        throw ValidationException::withMessages([
            'token' => ['The password reset token is invalid or expired.'],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function changePassword(UpdateAccountPasswordRequest $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('tenant');

        if (! $this->hasTenantContext($user)) {
            return $this->tenantContextRequiredResponse();
        }

        $data = $request->validated();

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_PASSWORD_CHANGE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $this->eventTenant($user),
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'invalid_current_password',
                ],
            );

            throw ValidationException::withMessages([
                'current_password' => ['The current password is invalid.'],
            ]);
        }

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();

            $user->personalAccessTokens()->delete();
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_PASSWORD_CHANGE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $this->eventTenant($user),
            user: $user,
            subjectEmail: $user->email,
        );

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('tenant');

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant context is required for authenticated API access.',
            ], 403);
        }

        $accessToken = $this->currentAccessToken($request, $user);

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => [
                    ...$this->userPayload($user),
                ],
                'tenant' => [
                    ...$this->tenantPayload($tenant),
                ],
                'token' => [
                    ...$this->tokenPayload($accessToken),
                ],
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->hasTenantContext($user)) {
            return $this->tenantContextRequiredResponse();
        }

        $data = $request->validated();

        $user->forceFill([
            'name' => $data['name'],
        ])->save();

        $user->refresh();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_PROFILE_UPDATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $this->eventTenant($user),
            user: $user,
            metadata: [
                'changed_fields' => ['name'],
            ],
        );

        return response()->json([
            'data' => [
                'user' => [
                    ...$this->userPayload($user),
                ],
            ],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function exportAccount(ExportAccountRequest $request): JsonResponse
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

        $data = $request->validated();

        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_ACCOUNT_EXPORT_REQUEST,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'invalid_current_password',
                ],
            );

            throw ValidationException::withMessages([
                'current_password' => ['The current password is invalid.'],
            ]);
        }

        $includeSecret = ($data['include_secret'] ?? false) === true;
        $secretUnlockToken = $includeSecret
            ? $this->validExportSecretUnlockToken($request, $user)
            : null;

        if ($includeSecret && ! $secretUnlockToken instanceof SecretUnlockToken) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_ACCOUNT_EXPORT_REQUEST,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'invalid_secret_unlock_token',
                    'include_secret' => true,
                ],
            );

            throw ValidationException::withMessages([
                'secret_unlock_token' => ['A valid X-Secret-Unlock token is required to export secret memories.'],
            ]);
        }

        $payload = $this->accountExportPayload(
            request: $request,
            user: $user,
            tenant: $tenant,
            includeUnlockedSecrets: $secretUnlockToken instanceof SecretUnlockToken,
        );

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_ACCOUNT_EXPORT_REQUEST,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
            metadata: [
                'include_secret' => $includeSecret,
                'secret_unlocked' => $secretUnlockToken instanceof SecretUnlockToken,
            ],
        );

        return response()->json([
            'data' => $payload,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function deleteAccount(DeleteAccountRequest $request): Response|JsonResponse
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

        $data = $request->validated();

        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_ACCOUNT_DELETE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'invalid_current_password',
                ],
            );

            throw ValidationException::withMessages([
                'current_password' => ['The current password is invalid.'],
            ]);
        }

        if ($this->isLastActiveTenantOwner($user, $tenant)) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_ACCOUNT_DELETE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'last_active_owner',
                ],
            );

            throw ValidationException::withMessages([
                'account' => ['The last active tenant owner cannot delete their account.'],
            ]);
        }

        $subjectEmail = $user->email;
        $reason = $data['reason'] ?? null;

        /** @var array{memories_deleted: int, categories_deleted: int, tags_pruned: int, pending_invitations_revoked: int} $result */
        $result = DB::transaction(function () use ($user, $tenant): array {
            $tenantId = (int) $tenant->id;
            $userId = (int) $user->id;
            $now = now();
            $memoryIds = Memory::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('owner_user_id', $userId)
                ->pluck('id');

            if ($memoryIds->isNotEmpty()) {
                DB::table('memory_tag')
                    ->whereIn('memory_id', $memoryIds)
                    ->delete();
            }

            $memoriesDeleted = Memory::query()
                ->where('tenant_id', $tenantId)
                ->where('owner_user_id', $userId)
                ->delete();

            $categoriesDeleted = Category::query()
                ->where('tenant_id', $tenantId)
                ->where('owner_user_id', $userId)
                ->delete();

            $tagsPruned = Tag::query()
                ->where('tenant_id', $tenantId)
                ->whereDoesntHave('memories')
                ->delete();

            $pendingInvitationsRevoked = TenantMemberInvitation::query()
                ->where('tenant_id', $tenantId)
                ->where('invited_by_user_id', $userId)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->update([
                    'revoked_at' => $now,
                    'updated_at' => $now,
                ]);

            $user->personalAccessTokens()->delete();
            $user->secretUnlockTokens()->delete();

            $user->forceFill([
                'tenant_id' => null,
                'role' => User::ROLE_MEMBER,
                'account_status' => User::ACCOUNT_STATUS_DISABLED,
                'name' => 'Deleted User',
                'email' => $this->anonymizedEmail($user),
                'pending_email' => null,
                'pending_email_requested_at' => null,
                'email_verified_at' => null,
                'password' => Str::random(64),
                'secret_unlock_password' => null,
                'remember_token' => Str::random(60),
                'deleted_at' => $now,
                'anonymized_at' => $now,
            ])->save();

            return [
                'memories_deleted' => $memoriesDeleted,
                'categories_deleted' => $categoriesDeleted,
                'tags_pruned' => $tagsPruned,
                'pending_invitations_revoked' => $pendingInvitationsRevoked,
            ];
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_ACCOUNT_DELETE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $user,
            subjectEmail: $subjectEmail,
            metadata: [
                'reason' => $reason,
                ...$result,
            ],
        );

        return response()->noContent();
    }

    public function resendEmailVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('tenant');

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            return $this->tenantContextRequiredResponse();
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => self::EMAIL_ALREADY_VERIFIED_MESSAGE,
                'data' => [
                    'user' => $this->userPayload($user),
                ],
            ]);
        }

        $user->sendEmailVerificationNotification();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_EMAIL_VERIFICATION_REQUEST,
            outcome: SecurityEvent::OUTCOME_REQUESTED,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
        );

        return response()->json([
            'message' => self::EMAIL_VERIFICATION_SENT_MESSAGE,
        ], 202);
    }

    public function requestEmailChange(UpdateEmailRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('tenant');

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            return $this->tenantContextRequiredResponse();
        }

        $data = $request->validated();
        $pendingEmail = (string) $data['email'];
        $previousEmail = $user->email;

        $user->forceFill([
            'pending_email' => $pendingEmail,
            'pending_email_requested_at' => now(),
        ])->save();

        Notification::route('mail', $pendingEmail)
            ->notify(new VerifyEmailChangeNotification((int) $user->id, $pendingEmail));

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_EMAIL_CHANGE_REQUEST,
            outcome: SecurityEvent::OUTCOME_REQUESTED,
            tenant: $tenant,
            user: $user,
            subjectEmail: $pendingEmail,
            metadata: [
                'current_email' => $previousEmail,
            ],
        );

        $user->refresh();

        return response()->json([
            'message' => self::EMAIL_CHANGE_VERIFICATION_SENT_MESSAGE,
            'data' => [
                'user' => $this->userPayload($user),
            ],
        ], 202);
    }

    public function verifyEmail(Request $request, int|string $id, string $hash): JsonResponse
    {
        $user = User::query()
            ->with('tenant')
            ->find($id);

        if (! $user instanceof User) {
            return response()->json([
                'message' => self::INVALID_EMAIL_VERIFICATION_LINK_MESSAGE,
            ], 404);
        }

        if (! $request->hasValidSignature()) {
            return $this->emailVerificationFailureResponse(
                request: $request,
                user: $user,
                reason: 'invalid_signature',
            );
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->emailVerificationFailureResponse(
                request: $request,
                user: $user,
                reason: 'invalid_hash',
            );
        }

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_EMAIL_VERIFICATION_COMPLETE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'tenant_context_missing',
                ],
            );

            return $this->tenantContextRequiredResponse();
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => self::EMAIL_ALREADY_VERIFIED_MESSAGE,
                'data' => [
                    'user' => $this->userPayload($user),
                ],
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new EmailVerifiedEvent($user));
            $user->refresh();

            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_EMAIL_VERIFICATION_COMPLETE,
                outcome: SecurityEvent::OUTCOME_SUCCESS,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
            );
        }

        return response()->json([
            'message' => self::EMAIL_VERIFIED_MESSAGE,
            'data' => [
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function verifyEmailChange(Request $request, int|string $id, string $hash): JsonResponse
    {
        $user = User::query()
            ->with('tenant')
            ->find($id);

        if (! $user instanceof User) {
            return response()->json([
                'message' => self::INVALID_EMAIL_CHANGE_LINK_MESSAGE,
            ], 404);
        }

        if (! $request->hasValidSignature()) {
            return $this->emailChangeFailureResponse(
                request: $request,
                user: $user,
                reason: 'invalid_signature',
            );
        }

        $pendingEmail = $this->pendingEmail($user);

        if ($pendingEmail === null) {
            return $this->emailChangeFailureResponse(
                request: $request,
                user: $user,
                reason: 'missing_pending_email',
            );
        }

        if (! hash_equals((string) $hash, sha1($pendingEmail))) {
            return $this->emailChangeFailureResponse(
                request: $request,
                user: $user,
                reason: 'invalid_hash',
                pendingEmail: $pendingEmail,
            );
        }

        $tenant = $user->tenant;

        if ($user->tenant_id === null || ! $tenant instanceof Tenant) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_EMAIL_CHANGE_COMPLETE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                user: $user,
                subjectEmail: $pendingEmail,
                metadata: [
                    'reason' => 'tenant_context_missing',
                ],
            );

            return $this->tenantContextRequiredResponse();
        }

        if ($this->emailIsUnavailable($user, $pendingEmail)) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_EMAIL_CHANGE_COMPLETE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $pendingEmail,
                metadata: [
                    'reason' => 'email_unavailable',
                ],
            );

            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        $previousEmail = $user->email;

        $user->forceFill([
            'email' => $pendingEmail,
            'pending_email' => null,
            'pending_email_requested_at' => null,
            'email_verified_at' => now(),
        ])->save();

        event(new EmailVerifiedEvent($user));

        $user->refresh();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_EMAIL_CHANGE_COMPLETE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
            metadata: [
                'previous_email' => $previousEmail,
            ],
        );

        return response()->json([
            'message' => self::EMAIL_CHANGED_MESSAGE,
            'data' => [
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessToken = $this->currentAccessToken($request, $user);

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $tokenId = (int) $accessToken->getKey();
        $accessToken->delete();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TOKEN_LOGOUT,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $this->eventTenant($user),
            user: $user,
            metadata: [
                'resource_type' => 'personal_access_token',
                'token_id' => $tokenId,
            ],
        );

        return response()->json(null, 204);
    }

    public function tokens(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->hasTenantContext($user)) {
            return $this->tenantContextRequiredResponse();
        }

        $currentToken = $this->currentAccessToken($request, $user);

        if (! $currentToken instanceof PersonalAccessToken) {
            return $this->unauthenticatedResponse();
        }

        $tokens = $user->personalAccessTokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                ...$this->tokenPayload($token),
                'is_current' => $token->is($currentToken),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $tokens,
        ]);
    }

    public function revokeToken(Request $request, int|string $token): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->hasTenantContext($user)) {
            return $this->tenantContextRequiredResponse();
        }

        $accessToken = $this->findUserToken($user, $token);
        $tokenId = (int) $accessToken->getKey();
        $revokedCurrentToken = $this->currentAccessToken($request, $user)?->is($accessToken) ?? false;

        $accessToken->delete();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TOKEN_REVOKE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $this->eventTenant($user),
            user: $user,
            metadata: [
                'resource_type' => 'personal_access_token',
                'token_id' => $tokenId,
                'revoked_current_token' => $revokedCurrentToken,
            ],
        );

        return response()->noContent();
    }

    public function revokeAllTokens(Request $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->hasTenantContext($user)) {
            return $this->tenantContextRequiredResponse();
        }

        $tokensRevoked = $user->personalAccessTokens()->count();
        $user->personalAccessTokens()->delete();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TOKEN_REVOKE_ALL,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $this->eventTenant($user),
            user: $user,
            metadata: [
                'resource_type' => 'personal_access_token',
                'tokens_revoked' => $tokensRevoked,
            ],
        );

        return response()->noContent();
    }

    public function rotateToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->hasTenantContext($user)) {
            return $this->tenantContextRequiredResponse();
        }

        $currentToken = $this->currentAccessToken($request, $user);

        if (! $currentToken instanceof PersonalAccessToken) {
            return $this->unauthenticatedResponse();
        }

        $rotatedFromTokenId = (int) $currentToken->getKey();
        $newAccessToken = DB::transaction(function () use ($user, $currentToken) {
            $newAccessToken = $user->createApiToken(
                name: $currentToken->name,
                abilities: $currentToken->abilities ?? ['*'],
                expiresAt: $currentToken->expires_at,
            );

            $currentToken->delete();

            return $newAccessToken;
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_TOKEN_ROTATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $this->eventTenant($user),
            user: $user,
            metadata: [
                'resource_type' => 'personal_access_token',
                'rotated_from_token_id' => $rotatedFromTokenId,
                'rotated_to_token_id' => (int) $newAccessToken->accessToken->getKey(),
            ],
        );

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $newAccessToken->plainTextToken,
                'expires_at' => $newAccessToken->accessToken->expires_at?->toAtomString(),
                'token' => [
                    ...$this->tokenPayload($newAccessToken->accessToken),
                    'is_current' => true,
                ],
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountExportPayload(
        Request $request,
        User $user,
        Tenant $tenant,
        bool $includeUnlockedSecrets,
    ): array {
        $context = TenantUserContext::fromUser($user);

        $categories = Category::queryForContext($context)
            ->with('parent')
            ->withCount(['memories' => function (Builder $query) use ($context, $includeUnlockedSecrets): void {
                $query
                    ->where('memories.tenant_id', $context->tenantId())
                    ->where('memories.owner_user_id', $context->userId())
                    ->when(
                        ! $includeUnlockedSecrets,
                        static fn (Builder $query): Builder => $query->where('memories.visibility', '!=', Memory::VISIBILITY_SECRET),
                    );
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $tags = $this->accountExportTags($context, $includeUnlockedSecrets);

        $memories = Memory::queryForContext($context)
            ->with(['category', 'tags'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Memory $memory): array => $this->accountExportMemoryPayload(
                memory: $memory,
                includeUnlockedSecrets: $includeUnlockedSecrets,
            ))
            ->values()
            ->all();

        return [
            'exported_at' => now()->toAtomString(),
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($tenant),
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'tags' => TagResource::collection($tags)->resolve($request),
            'memories' => $memories,
        ];
    }

    /**
     * @return Collection<int, Tag>
     */
    private function accountExportTags(TenantUserContext $context, bool $includeUnlockedSecrets): Collection
    {
        $constrainExportedMemories = static function (Builder $query) use ($context, $includeUnlockedSecrets): void {
            $query
                ->where('memories.tenant_id', $context->tenantId())
                ->where('memories.owner_user_id', $context->userId())
                ->when(
                    ! $includeUnlockedSecrets,
                    static fn (Builder $query): Builder => $query->where('memories.visibility', '!=', Memory::VISIBILITY_SECRET),
                );
        };

        return Tag::queryForContext($context)
            ->whereHas('memories', $constrainExportedMemories)
            ->withCount(['memories' => $constrainExportedMemories])
            ->orderByDesc('memories_count')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function accountExportMemoryPayload(Memory $memory, bool $includeUnlockedSecrets): array
    {
        if ($memory->visibility === Memory::VISIBILITY_SECRET && ! $includeUnlockedSecrets) {
            return [
                'id' => (int) $memory->getKey(),
                'public_id' => $memory->public_id,
                'visibility' => Memory::VISIBILITY_SECRET,
                'locked' => true,
            ];
        }

        return [
            'id' => (int) $memory->getKey(),
            'public_id' => $memory->public_id,
            'locked' => false,
            'period_key' => $memory->period_key,
            'occurred_on' => $memory->occurred_on?->toDateString(),
            'title' => $memory->title,
            'body' => $memory->body,
            'emotion_label' => $memory->emotion_label,
            'emotion_intensity' => $memory->emotion_intensity,
            'visibility' => $memory->visibility,
            'category' => $memory->category === null ? null : [
                'id' => (int) $memory->category->getKey(),
                'public_id' => $memory->category->public_id,
                'name' => $memory->category->name,
            ],
            'tags' => $memory->tags->pluck('name')->values()->all(),
            'metadata' => is_array($memory->metadata) ? $memory->metadata : null,
            'created_at' => $memory->created_at?->toAtomString(),
            'updated_at' => $memory->updated_at?->toAtomString(),
        ];
    }

    private function validExportSecretUnlockToken(Request $request, User $user): ?SecretUnlockToken
    {
        $unlockToken = SecretUnlockToken::findToken($request->header('X-Secret-Unlock'));

        if (
            ! $unlockToken instanceof SecretUnlockToken
            || $unlockToken->isExpired()
            || (int) $unlockToken->user_id !== (int) $user->id
        ) {
            return null;
        }

        return $unlockToken;
    }

    private function isLastActiveTenantOwner(User $user, Tenant $tenant): bool
    {
        if (! $user->isTenantOwner()) {
            return false;
        }

        return ! User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_OWNER)
            ->where('account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->whereKeyNot($user->id)
            ->exists();
    }

    private function anonymizedEmail(User $user): string
    {
        return 'deleted-user-'.$user->id.'-'.Str::lower((string) Str::ulid()).'@deleted.local';
    }

    private function currentAccessToken(Request $request, User $user): ?PersonalAccessToken
    {
        $accessToken = PersonalAccessToken::findToken($request->bearerToken());

        if (! $accessToken instanceof PersonalAccessToken) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        if (! $tokenable instanceof User || ! $tokenable->is($user)) {
            return null;
        }

        return $accessToken;
    }

    private function findUserToken(User $user, int|string $token): PersonalAccessToken
    {
        $accessToken = $user->personalAccessTokens()
            ->whereKey($token)
            ->first();

        if (! $accessToken instanceof PersonalAccessToken) {
            throw new NotFoundHttpException;
        }

        return $accessToken;
    }

    /**
     * @return array{
     *     id: int,
     *     public_id: string|null,
     *     name: string,
     *     abilities: array<int, string>,
     *     last_used_at: string|null,
     *     expires_at: string|null,
     *     created_at: string|null
     * }
     */
    private function tokenPayload(PersonalAccessToken $accessToken): array
    {
        return [
            'id' => $accessToken->id,
            'name' => $accessToken->name,
            'abilities' => $accessToken->abilities ?? [],
            'last_used_at' => $accessToken->last_used_at?->toAtomString(),
            'expires_at' => $accessToken->expires_at?->toAtomString(),
            'created_at' => $accessToken->created_at?->toAtomString(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     pending_email: string|null,
     *     pending_email_requested_at: string|null,
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
            'pending_email' => $this->pendingEmail($user),
            'pending_email_requested_at' => $user->pending_email_requested_at?->toAtomString(),
            'role' => $user->role,
            'account_status' => $user->account_status,
            'is_email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at?->toAtomString(),
        ];
    }

    private function hasTenantContext(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    private function tenantContextRequiredResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Tenant context is required for authenticated API access.',
        ], 403);
    }

    private function unauthenticatedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    private function eventTenant(?User $user): ?Tenant
    {
        if (! $user instanceof User) {
            return null;
        }

        $tenant = $user->tenant;

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function emailVerificationFailureResponse(Request $request, User $user, string $reason): JsonResponse
    {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_EMAIL_VERIFICATION_COMPLETE,
            outcome: SecurityEvent::OUTCOME_FAILURE,
            tenant: $this->eventTenant($user),
            user: $user,
            subjectEmail: $user->email,
            metadata: [
                'reason' => $reason,
            ],
        );

        return response()->json([
            'message' => self::INVALID_EMAIL_VERIFICATION_LINK_MESSAGE,
        ], 403);
    }

    private function emailChangeFailureResponse(
        Request $request,
        User $user,
        string $reason,
        ?string $pendingEmail = null,
    ): JsonResponse {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_EMAIL_CHANGE_COMPLETE,
            outcome: SecurityEvent::OUTCOME_FAILURE,
            tenant: $this->eventTenant($user),
            user: $user,
            subjectEmail: $pendingEmail ?? $this->pendingEmail($user) ?? $user->email,
            metadata: [
                'reason' => $reason,
            ],
        );

        return response()->json([
            'message' => self::INVALID_EMAIL_CHANGE_LINK_MESSAGE,
        ], 403);
    }

    private function pendingEmail(User $user): ?string
    {
        return is_string($user->pending_email) && $user->pending_email !== ''
            ? $user->pending_email
            : null;
    }

    private function emailIsUnavailable(User $user, string $email): bool
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->where(function ($query) use ($email): void {
                $query
                    ->where('email', $email)
                    ->orWhere('pending_email', $email);
            })
            ->exists();
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
}
