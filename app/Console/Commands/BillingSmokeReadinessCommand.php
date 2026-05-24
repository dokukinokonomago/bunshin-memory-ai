<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class BillingSmokeReadinessCommand extends Command
{
    private const API_ORIGIN_ENV_KEYS = [
        'BUNSHIN_BILLING_SMOKE_API_ORIGIN',
        'API_ORIGIN',
    ];

    private const FRONTEND_ORIGIN_ENV_KEYS = [
        'BUNSHIN_BILLING_SMOKE_FRONTEND_ORIGIN',
        'FRONTEND_ORIGIN',
    ];

    private const OWNER_TOKEN_ENV_KEYS = [
        'BUNSHIN_BILLING_SMOKE_OWNER_TOKEN',
        'OWNER_TOKEN',
    ];

    private const PROVIDER_CONFIRMATION_ENV_KEYS = [
        'BUNSHIN_BILLING_SMOKE_PROVIDER_CONFIRMED',
        'PROVIDER_ACCOUNT_CONFIRMED',
    ];

    private const SMOKE_TENANT_ENV_KEYS = [
        'BUNSHIN_BILLING_SMOKE_TENANT',
        'SMOKE_TENANT_PUBLIC_ID',
    ];

    protected $signature = 'bunshin:billing-smoke-readiness
        {--tenant= : Approved smoke tenant public id or slug}';

    protected $description = 'Check production billing smoke prerequisites without printing secrets or provider identifiers.';

    public function handle(): int
    {
        $frontendOrigin = $this->firstRuntimeEnv(self::FRONTEND_ORIGIN_ENV_KEYS);
        $tenantTarget = $this->optionString('tenant') ?? $this->firstRuntimeEnv(self::SMOKE_TENANT_ENV_KEYS);
        $checks = [
            $this->check(
                'Billing enabled',
                filter_var(config('bunshin.billing.enabled'), FILTER_VALIDATE_BOOL),
                'Set billing enabled through approved config.',
            ),
            $this->check(
                'Stripe provider selected',
                $this->stringConfig('bunshin.billing.provider') === 'stripe',
                'Set the supported billing provider.',
            ),
            $this->check(
                'Stripe server key present',
                $this->hasStringConfig('bunshin.billing.providers.stripe.secret_key'),
                'Deploy the server-side provider key.',
            ),
            $this->check(
                'Stripe webhook secret present',
                $this->hasStringConfig('bunshin.billing.providers.stripe.webhook_secret'),
                'Deploy the webhook signing secret.',
            ),
            $this->check(
                'Stripe API base configured',
                $this->validUrlConfig('bunshin.billing.providers.stripe.api_base_url'),
                'Configure a valid provider API base URL.',
            ),
            $this->check(
                'Pro price mapping present',
                $this->hasProPriceMapping(),
                'Map the provider pro price to the local pro plan.',
            ),
            $this->check(
                'Checkout success URL explicit',
                $this->hasRuntimeEnv('BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL')
                    && $this->validUrlConfig('bunshin.billing.checkout.success_url')
                    && str_contains($this->stringConfig('bunshin.billing.checkout.success_url'), '{CHECKOUT_SESSION_ID}'),
                'Set an explicit valid checkout success URL with the session placeholder.',
            ),
            $this->check(
                'Checkout cancel URL explicit',
                $this->hasRuntimeEnv('BUNSHIN_BILLING_CHECKOUT_CANCEL_URL')
                    && $this->validUrlConfig('bunshin.billing.checkout.cancel_url'),
                'Set an explicit valid checkout cancel URL.',
            ),
            $this->check(
                'Portal return URL explicit',
                $this->hasRuntimeEnv('BUNSHIN_BILLING_PORTAL_RETURN_URL')
                    && $this->validUrlConfig('bunshin.billing.portal.return_url'),
                'Set an explicit valid portal return URL.',
            ),
            $this->check(
                'API origin hint present',
                $this->validUrlString($this->firstRuntimeEnv(self::API_ORIGIN_ENV_KEYS)),
                'Provide the production API origin hint for the smoke run.',
            ),
            $this->check(
                'Frontend origin hint present',
                $this->validUrlString($frontendOrigin),
                'Provide the production frontend origin hint for redirect verification.',
            ),
            $this->check(
                'Redirects match frontend origin',
                $this->redirectsMatchFrontendOrigin($frontendOrigin),
                'Align checkout and portal redirect URLs with the frontend origin.',
            ),
            $this->check(
                'Owner token hint present',
                $this->firstRuntimeEnv(self::OWNER_TOKEN_ENV_KEYS) !== null,
                'Provide an approved owner token through the smoke environment.',
            ),
            $this->check(
                'Provider account confirmed',
                $this->truthyRuntimeEnv(self::PROVIDER_CONFIRMATION_ENV_KEYS),
                'Confirm the intended production provider account out-of-band.',
            ),
            $this->smokeTenantCheck($tenantTarget),
        ];

        $this->table(['Check', 'Status', 'Detail'], array_map(
            static fn (array $check): array => [
                $check['label'],
                $check['passed'] ? 'pass' : 'fail',
                $check['detail'],
            ],
            $checks,
        ));

        $failed = array_values(array_filter(
            $checks,
            static fn (array $check): bool => ! $check['passed'],
        ));

        if ($failed !== []) {
            $this->warn(sprintf(
                'Billing smoke readiness failed. Missing or invalid checks: %d. Do not run production billing smoke yet.',
                count($failed),
            ));

            return self::FAILURE;
        }

        $this->info('Billing smoke readiness passed. Proceed with the production runbook without copying secrets or hosted URLs.');

        return self::SUCCESS;
    }

    /**
     * @return array{label: string, passed: bool, detail: string}
     */
    private function check(string $label, bool $passed, string $failureDetail): array
    {
        return [
            'label' => $label,
            'passed' => $passed,
            'detail' => $passed ? 'ready' : $failureDetail,
        ];
    }

    /**
     * @return array{label: string, passed: bool, detail: string}
     */
    private function smokeTenantCheck(?string $target): array
    {
        if ($target === null) {
            return $this->check(
                'Smoke tenant ready',
                false,
                'Provide an approved smoke tenant public id or slug.',
            );
        }

        $tenant = Tenant::query()
            ->where('public_id', $target)
            ->orWhere('slug', $target)
            ->first();

        if (! $tenant instanceof Tenant) {
            return $this->check(
                'Smoke tenant ready',
                false,
                'Approved smoke tenant was not found.',
            );
        }

        if ($tenant->isArchived() || $tenant->purged_at !== null) {
            return $this->check(
                'Smoke tenant ready',
                false,
                'Approved smoke tenant is archived or purged.',
            );
        }

        $hasVerifiedOwner = $tenant->users()
            ->where('role', User::ROLE_OWNER)
            ->where('account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->whereNotNull('email_verified_at')
            ->exists();

        return $this->check(
            'Smoke tenant ready',
            $hasVerifiedOwner,
            'Approved smoke tenant needs an active verified owner.',
        );
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);
        $value = is_array($value) ? reset($value) : $value;
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }

    private function hasStringConfig(string $key): bool
    {
        return $this->stringConfig($key) !== '';
    }

    private function validUrlConfig(string $key): bool
    {
        return $this->validUrlString($this->stringConfig($key));
    }

    private function validUrlString(?string $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function hasProPriceMapping(): bool
    {
        $pricePlanMap = config('bunshin.billing.price_plan_map.stripe', []);

        if (! is_array($pricePlanMap)) {
            return false;
        }

        foreach ($pricePlanMap as $priceId => $planKey) {
            if (is_string($priceId)
                && trim($priceId) !== ''
                && $planKey === Tenant::PLAN_PRO) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstRuntimeEnv(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->runtimeEnv($key);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function hasRuntimeEnv(string $key): bool
    {
        return $this->runtimeEnv($key) !== null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function truthyRuntimeEnv(array $keys): bool
    {
        $value = $this->firstRuntimeEnv($keys);

        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'confirmed'], true);
    }

    private function runtimeEnv(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false || trim((string) $value) === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function redirectsMatchFrontendOrigin(?string $frontendOrigin): bool
    {
        if (! $this->validUrlString($frontendOrigin)) {
            return false;
        }

        $frontendParts = $this->urlOriginParts($frontendOrigin);

        if ($frontendParts === null) {
            return false;
        }

        foreach ([
            'bunshin.billing.checkout.success_url',
            'bunshin.billing.checkout.cancel_url',
            'bunshin.billing.portal.return_url',
        ] as $configKey) {
            $parts = $this->urlOriginParts($this->stringConfig($configKey));

            if ($parts !== $frontendParts) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{scheme: string, host: string, port: int|null}|null
     */
    private function urlOriginParts(string $url): ?array
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ! is_string($parts['scheme'] ?? null)
            || ! is_string($parts['host'] ?? null)) {
            return null;
        }

        return [
            'scheme' => strtolower($parts['scheme']),
            'host' => strtolower($parts['host']),
            'port' => isset($parts['port']) ? (int) $parts['port'] : null,
        ];
    }
}
