<?php

namespace App\Providers;

use App\Auth\BearerTokenGuard;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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
}
