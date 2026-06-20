<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MemorySpaceController;
use App\Http\Controllers\Api\V1\SecretUnlockController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TenantLifecycleController;
use App\Http\Controllers\Api\V1\TenantMemberController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn (): JsonResponse => response()->json([
        'service' => 'bunshin-memory-api',
        'status' => 'ok',
        'version' => '0.1.0',
    ]))->name('api.v1.health');

    Route::post('/auth/signup', [AuthController::class, 'signup'])
        ->middleware('throttle:bunshin-auth-signup')
        ->name('api.v1.auth.signup');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:bunshin-auth-login')
        ->name('api.v1.auth.login');

    Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:bunshin-auth-password-forgot')
        ->name('api.v1.auth.password.forgot');

    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:bunshin-auth-password-reset')
        ->name('api.v1.auth.password.reset');

    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('api.v1.auth.email.verify');

    Route::get('/auth/email/change/verify/{id}/{hash}', [AuthController::class, 'verifyEmailChange'])
        ->name('api.v1.auth.email.change.verify');

    Route::post('/tenant/invitations/accept', [TenantMemberController::class, 'acceptInvitation'])
        ->middleware('throttle:bunshin-auth-invitation-accept')
        ->name('api.v1.tenant.invitations.accept');

    Route::post('/billing/webhooks/{provider}', [BillingController::class, 'webhook'])
        ->name('api.v1.billing.webhooks.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])
            ->name('api.v1.auth.me');

        Route::patch('/auth/profile', [AuthController::class, 'updateProfile'])
            ->name('api.v1.auth.profile.update');

        Route::post('/auth/account/export', [AuthController::class, 'exportAccount'])
            ->middleware('throttle:bunshin-account-lifecycle')
            ->name('api.v1.auth.account.export');

        Route::delete('/auth/account', [AuthController::class, 'deleteAccount'])
            ->middleware('throttle:bunshin-account-lifecycle')
            ->name('api.v1.auth.account.delete');

        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('api.v1.auth.logout');

        Route::put('/auth/password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:bunshin-auth-password-change')
            ->name('api.v1.auth.password.change');

        Route::post('/auth/email/verification-notification', [AuthController::class, 'resendEmailVerification'])
            ->middleware('throttle:bunshin-auth-email-verification')
            ->name('api.v1.auth.email.verification-notification');

        Route::put('/auth/email', [AuthController::class, 'requestEmailChange'])
            ->middleware('throttle:bunshin-auth-email-change')
            ->name('api.v1.auth.email.update');

        Route::get('/auth/tokens', [AuthController::class, 'tokens'])
            ->name('api.v1.auth.tokens.index');

        Route::post('/auth/tokens/revoke-all', [AuthController::class, 'revokeAllTokens'])
            ->name('api.v1.auth.tokens.revoke-all');

        Route::post('/auth/tokens/rotate', [AuthController::class, 'rotateToken'])
            ->name('api.v1.auth.tokens.rotate');

        Route::delete('/auth/tokens/{token}', [AuthController::class, 'revokeToken'])
            ->name('api.v1.auth.tokens.revoke');

        Route::get('/tenant/members', [TenantMemberController::class, 'members'])
            ->name('api.v1.tenant.members.index');

        Route::post('/tenant/export', [TenantLifecycleController::class, 'export'])
            ->middleware('throttle:bunshin-tenant-lifecycle')
            ->name('api.v1.tenant.export');

        Route::post('/tenant/archive', [TenantLifecycleController::class, 'archive'])
            ->middleware('throttle:bunshin-tenant-lifecycle')
            ->name('api.v1.tenant.archive');

        Route::post('/billing/checkout-sessions', [BillingController::class, 'checkout'])
            ->middleware('throttle:bunshin-billing')
            ->name('api.v1.billing.checkout-sessions.store');

        Route::post('/billing/portal-sessions', [BillingController::class, 'portal'])
            ->middleware('throttle:bunshin-billing')
            ->name('api.v1.billing.portal-sessions.store');

        Route::delete('/tenant/members/{member}', [TenantMemberController::class, 'revokeMember'])
            ->name('api.v1.tenant.members.revoke');

        Route::post('/tenant/members/{member}/secret-unlock-password/force-rotation', [TenantMemberController::class, 'forceSecretUnlockPasswordRotation'])
            ->middleware('throttle:bunshin-tenant-security-action')
            ->name('api.v1.tenant.members.secret-unlock-password.force-rotation');

        Route::patch('/tenant/members/{member}/account-status', [TenantMemberController::class, 'updateAccountStatus'])
            ->middleware('throttle:bunshin-tenant-security-action')
            ->name('api.v1.tenant.members.account-status.update');

        Route::patch('/tenant/members/{member}/role', [TenantMemberController::class, 'updateRole'])
            ->name('api.v1.tenant.members.role.update');

        Route::get('/tenant/invitations', [TenantMemberController::class, 'invitations'])
            ->name('api.v1.tenant.invitations.index');

        Route::post('/tenant/invitations', [TenantMemberController::class, 'invite'])
            ->name('api.v1.tenant.invitations.store');

        Route::delete('/tenant/invitations/{invitation}', [TenantMemberController::class, 'revokeInvitation'])
            ->name('api.v1.tenant.invitations.revoke');

        Route::get('/memories', [MemoryController::class, 'index'])
            ->name('api.v1.memories.index');

        Route::post('/memories', [MemoryController::class, 'store'])
            ->name('api.v1.memories.store');

        Route::get('/memories/{memory}', [MemoryController::class, 'show'])
            ->name('api.v1.memories.show');

        Route::patch('/memories/{memory}', [MemoryController::class, 'update'])
            ->name('api.v1.memories.update');

        Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])
            ->name('api.v1.memories.destroy');

        Route::get('/memory-space', [MemorySpaceController::class, 'show'])
            ->name('api.v1.memory-space.show');

        Route::post('/secret-unlocks', [SecretUnlockController::class, 'store'])
            ->name('api.v1.secret-unlocks.store');

        Route::put('/secret-unlock-password', [SecretUnlockController::class, 'updatePassword'])
            ->name('api.v1.secret-unlock-password.update');

        Route::post('/secret-unlock-password/recovery/request', [SecretUnlockController::class, 'requestPasswordRecovery'])
            ->middleware('throttle:bunshin-secret-unlock-password-recovery-request')
            ->name('api.v1.secret-unlock-password.recovery.request');

        Route::put('/secret-unlock-password/recovery/{id}/{hash}', [SecretUnlockController::class, 'completePasswordRecovery'])
            ->middleware('throttle:bunshin-secret-unlock-password-recovery-complete')
            ->name('api.v1.secret-unlock-password.recovery.complete');

        Route::apiResource('categories', CategoryController::class)
            ->names('api.v1.categories');

        Route::get('/tags', [TagController::class, 'index'])
            ->name('api.v1.tags.index');
    });
});
