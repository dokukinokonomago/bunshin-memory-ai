<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteSecretUnlockPasswordRecoveryRequest;
use App\Http\Requests\RequestSecretUnlockPasswordRecoveryRequest;
use App\Http\Requests\StoreSecretUnlockRequest;
use App\Http\Requests\UpdateSecretUnlockPasswordRequest;
use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SecretUnlockPasswordRecoveryNotification;
use App\Support\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecretUnlockController extends Controller
{
    private const RECOVERY_LINK_SENT_MESSAGE = 'Secret unlock password recovery link has been sent.';

    private const EMAIL_VERIFICATION_REQUIRED_MESSAGE = 'Email verification is required to recover the secret unlock password.';

    private const INVALID_RECOVERY_LINK_MESSAGE = 'The secret unlock password recovery link is invalid or expired.';

    public function __construct(private readonly SecurityEventLogger $securityEvents) {}

    public function store(StoreSecretUnlockRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! $user->hasSecretUnlockPassword()) {
            throw ValidationException::withMessages([
                'password' => ['シークレット解除用パスワードが設定されていません。'],
            ]);
        }

        if (! $user->checkSecretUnlockPassword((string) $data['password'])) {
            throw ValidationException::withMessages([
                'password' => ['シークレット解除用パスワードが正しくありません。'],
            ]);
        }

        $plainTextToken = Str::random(40);
        $expiresAt = now()->addMinutes(SecretUnlockToken::TTL_MINUTES);

        $unlockToken = $user->secretUnlockTokens()->create([
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'data' => [
                'unlock_token' => $unlockToken->getKey().'|'.$plainTextToken,
                'expires_at' => $unlockToken->expires_at?->toAtomString(),
            ],
        ], 201);
    }

    public function updatePassword(UpdateSecretUnlockPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $hasExistingUnlockPassword = $user->hasSecretUnlockPassword();

        if (! Hash::check((string) $data['account_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'account_password' => ['アカウントパスワードが正しくありません。'],
            ]);
        }

        if ((string) $data['password'] === (string) $data['account_password']) {
            throw ValidationException::withMessages([
                'password' => ['シークレット解除用パスワードにはアカウントパスワードと異なる値を指定してください。'],
            ]);
        }

        if ($hasExistingUnlockPassword) {
            if (! $user->checkSecretUnlockPassword((string) $data['current_password'])) {
                throw ValidationException::withMessages([
                    'current_password' => ['現在のシークレット解除用パスワードが正しくありません。'],
                ]);
            }

            if ((string) $data['password'] === (string) $data['current_password']) {
                throw ValidationException::withMessages([
                    'password' => ['新しいシークレット解除用パスワードには現在と異なる値を指定してください。'],
                ]);
            }
        }

        $user->forceFill([
            'secret_unlock_password' => $data['password'],
        ])->save();

        $user->secretUnlockTokens()->delete();

        $user->loadMissing('tenant');
        $tenant = $user->tenant;

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_CHANGE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant instanceof Tenant ? $tenant : null,
            user: $user,
            metadata: [
                'mode' => $hasExistingUnlockPassword ? 'changed' : 'set',
            ],
        );

        return response()->json([
            'data' => [
                'has_secret_unlock_password' => true,
                'mode' => $hasExistingUnlockPassword ? 'changed' : 'set',
            ],
        ]);
    }

    public function requestPasswordRecovery(RequestSecretUnlockPasswordRecoveryRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('tenant');

        $tenant = $user->tenant;

        if (! $tenant instanceof Tenant) {
            return $this->tenantContextRequiredResponse();
        }

        $data = $request->validated();

        if (! $user->hasVerifiedEmail()) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_REQUEST,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'email_not_verified',
                ],
            );

            return response()->json([
                'message' => self::EMAIL_VERIFICATION_REQUIRED_MESSAGE,
            ], 403);
        }

        if (! Hash::check((string) $data['account_password'], (string) $user->password)) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_REQUEST,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $user,
                subjectEmail: $user->email,
                metadata: [
                    'reason' => 'invalid_account_password',
                ],
            );

            throw ValidationException::withMessages([
                'account_password' => ['アカウントパスワードが正しくありません。'],
            ]);
        }

        $user->notify(new SecretUnlockPasswordRecoveryNotification(
            userId: (int) $user->id,
            email: (string) $user->email,
        ));

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_REQUEST,
            outcome: SecurityEvent::OUTCOME_REQUESTED,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
        );

        return response()->json([
            'message' => self::RECOVERY_LINK_SENT_MESSAGE,
        ], 202);
    }

    public function completePasswordRecovery(
        CompleteSecretUnlockPasswordRecoveryRequest $request,
        int|string $id,
        string $hash,
    ): JsonResponse {
        $authenticatedUser = $request->user();
        $authenticatedUser->loadMissing('tenant');

        $tenant = $authenticatedUser->tenant;

        if (! $tenant instanceof Tenant) {
            return $this->tenantContextRequiredResponse();
        }

        if (! $request->hasValidSignature()) {
            return $this->recoveryCompletionFailureResponse(
                request: $request,
                user: $authenticatedUser,
                reason: 'invalid_signature',
                tenant: $tenant,
            );
        }

        $recoveryUser = User::query()
            ->with('tenant')
            ->find($id);

        if (! $recoveryUser instanceof User) {
            $this->securityEvents->log(
                request: $request,
                eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_COMPLETE,
                outcome: SecurityEvent::OUTCOME_FAILURE,
                tenant: $tenant,
                user: $authenticatedUser,
                subjectEmail: $authenticatedUser->email,
                metadata: [
                    'reason' => 'user_not_found',
                    'recovery_user_id' => is_numeric($id) ? (int) $id : (string) $id,
                ],
            );

            return response()->json([
                'message' => self::INVALID_RECOVERY_LINK_MESSAGE,
            ], 404);
        }

        if ((int) $recoveryUser->id !== (int) $authenticatedUser->id) {
            return $this->recoveryCompletionFailureResponse(
                request: $request,
                user: $authenticatedUser,
                reason: 'user_mismatch',
                tenant: $tenant,
            );
        }

        if (! hash_equals((string) $hash, sha1((string) $recoveryUser->email))) {
            return $this->recoveryCompletionFailureResponse(
                request: $request,
                user: $authenticatedUser,
                reason: 'invalid_hash',
                tenant: $tenant,
            );
        }

        if (! $authenticatedUser->hasVerifiedEmail()) {
            return $this->recoveryCompletionFailureResponse(
                request: $request,
                user: $authenticatedUser,
                reason: 'email_not_verified',
                tenant: $tenant,
                message: self::EMAIL_VERIFICATION_REQUIRED_MESSAGE,
            );
        }

        $data = $request->validated();

        if (! Hash::check((string) $data['account_password'], (string) $authenticatedUser->password)) {
            $this->logRecoveryCompletionValidationFailure(
                request: $request,
                user: $authenticatedUser,
                tenant: $tenant,
                reason: 'invalid_account_password',
            );

            throw ValidationException::withMessages([
                'account_password' => ['アカウントパスワードが正しくありません。'],
            ]);
        }

        if ((string) $data['password'] === (string) $data['account_password']) {
            $this->logRecoveryCompletionValidationFailure(
                request: $request,
                user: $authenticatedUser,
                tenant: $tenant,
                reason: 'password_reuses_account_password',
            );

            throw ValidationException::withMessages([
                'password' => ['シークレット解除用パスワードにはアカウントパスワードと異なる値を指定してください。'],
            ]);
        }

        if ($authenticatedUser->checkSecretUnlockPassword((string) $data['password'])) {
            $this->logRecoveryCompletionValidationFailure(
                request: $request,
                user: $authenticatedUser,
                tenant: $tenant,
                reason: 'password_reuses_current_unlock_password',
            );

            throw ValidationException::withMessages([
                'password' => ['新しいシークレット解除用パスワードには現在と異なる値を指定してください。'],
            ]);
        }

        $authenticatedUser->forceFill([
            'secret_unlock_password' => $data['password'],
        ])->save();

        $authenticatedUser->secretUnlockTokens()->delete();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_COMPLETE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $tenant,
            user: $authenticatedUser,
            subjectEmail: $authenticatedUser->email,
        );

        return response()->json([
            'data' => [
                'has_secret_unlock_password' => true,
                'mode' => 'recovered',
            ],
        ]);
    }

    private function tenantContextRequiredResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Tenant context is required for authenticated API access.',
        ], 403);
    }

    private function recoveryCompletionFailureResponse(
        CompleteSecretUnlockPasswordRecoveryRequest $request,
        User $user,
        string $reason,
        ?Tenant $tenant = null,
        string $message = self::INVALID_RECOVERY_LINK_MESSAGE,
    ): JsonResponse {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_COMPLETE,
            outcome: SecurityEvent::OUTCOME_FAILURE,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
            metadata: [
                'reason' => $reason,
            ],
        );

        return response()->json([
            'message' => $message,
        ], 403);
    }

    private function logRecoveryCompletionValidationFailure(
        CompleteSecretUnlockPasswordRecoveryRequest $request,
        User $user,
        Tenant $tenant,
        string $reason,
    ): void {
        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_COMPLETE,
            outcome: SecurityEvent::OUTCOME_FAILURE,
            tenant: $tenant,
            user: $user,
            subjectEmail: $user->email,
            metadata: [
                'reason' => $reason,
            ],
        );
    }
}
