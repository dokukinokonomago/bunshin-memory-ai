<?php

namespace App\Providers;

use App\Auth\BearerTokenGuard;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'bunshin-auth-login',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.login.per_minute', 10),
            )->by(self::rateLimitKey($request, 'login', 'email')),
        );

        RateLimiter::for(
            'bunshin-auth-signup',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.signup.per_minute', 5),
            )->by(self::rateLimitKey($request, 'signup', 'email')),
        );

        RateLimiter::for(
            'bunshin-auth-password-forgot',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.password_forgot.per_minute', 5),
            )->by(self::rateLimitKey($request, 'password-forgot', 'email')),
        );

        RateLimiter::for(
            'bunshin-auth-password-reset',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.password_reset.per_minute', 5),
            )->by(self::rateLimitKey($request, 'password-reset', 'email')),
        );

        RateLimiter::for(
            'bunshin-auth-password-change',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.password_change.per_minute', 5),
            )->by('password-change:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for(
            'bunshin-auth-invitation-accept',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.invitation_accept.per_minute', 5),
            )->by(self::rateLimitKey($request, 'invitation-accept', 'token')),
        );

        RateLimiter::for(
            'bunshin-auth-email-verification',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.email_verification.per_minute', 5),
            )->by('email-verification:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for(
            'bunshin-auth-email-change',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.email_change.per_minute', 5),
            )->by('email-change:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for(
            'bunshin-secret-unlock-password-recovery-request',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.secret_unlock_password_recovery_request.per_minute', 5),
            )->by('secret-unlock-password-recovery-request:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for(
            'bunshin-secret-unlock-password-recovery-complete',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.secret_unlock_password_recovery_complete.per_minute', 5),
            )->by('secret-unlock-password-recovery-complete:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for(
            'bunshin-tenant-security-action',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.tenant_security_action.per_minute', 10),
            )->by('tenant-security-action:'.(string) ($request->user()?->tenant_id ?? 'no-tenant').':'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for(
            'bunshin-account-lifecycle',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.account_lifecycle.per_minute', 5),
            )->by(self::authenticatedUserRateLimitKey($request, 'account-lifecycle')),
        );

        RateLimiter::for(
            'bunshin-tenant-lifecycle',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.tenant_lifecycle.per_minute', 5),
            )->by('tenant-lifecycle:'.(string) ($request->user()?->tenant_id ?? 'no-tenant').':'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for(
            'bunshin-billing',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('bunshin.security.rate_limits.billing.per_minute', 5),
            )->by('billing:'.(string) ($request->user()?->tenant_id ?? 'no-tenant').':'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')),
        );

        ResetPasswordNotification::createUrlUsing(
            static fn (User $user, string $token): string => rtrim((string) config('app.url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]),
        );

        VerifyEmailNotification::createUrlUsing(
            static fn (User $user): string => URL::temporarySignedRoute(
                'api.v1.auth.email.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            ),
        );

        Gate::define(
            'manage-tenant-members',
            static fn (User $user, Tenant $tenant): bool => $user->tenant_id !== null
                && (int) $user->tenant_id === (int) $tenant->id
                && $user->canManageTenantMembers(),
        );

        Auth::extend('sanctum_token', function (Application $app, string $name, array $config): BearerTokenGuard {
            $guard = new BearerTokenGuard(
                static function (Request $request): ?User {
                    $accessToken = PersonalAccessToken::findToken($request->bearerToken());

                    if (! $accessToken instanceof PersonalAccessToken || $accessToken->isExpired()) {
                        return null;
                    }

                    $tokenable = $accessToken->tokenable;

                    if (! $tokenable instanceof User) {
                        return null;
                    }

                    if (! $tokenable->canAccessApi()) {
                        return null;
                    }

                    $accessToken->forceFill(['last_used_at' => now()])->save();

                    return $tokenable;
                },
                $app['request'],
                $app['auth']->createUserProvider($config['provider'] ?? null),
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    private static function rateLimitKey(Request $request, string $scope, string $subjectField): string
    {
        $subject = $request->input($subjectField);

        if (is_scalar($subject)) {
            $subject = Str::lower(trim((string) $subject));
        } else {
            $subject = '';
        }

        return $scope.':'.sha1($subject.'|'.($request->ip() ?? 'unknown'));
    }

    private static function authenticatedUserRateLimitKey(Request $request, string $scope): string
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $scope.':'.(string) $user->getAuthIdentifier();
        }

        $accessToken = PersonalAccessToken::findToken($request->bearerToken());
        $tokenable = $accessToken?->tokenable;

        if ($tokenable instanceof User) {
            return $scope.':'.(string) $tokenable->getAuthIdentifier();
        }

        return $scope.':'.(string) ($request->ip() ?? 'unknown');
    }
}
