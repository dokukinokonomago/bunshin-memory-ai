<?php

return [
    'onboarding' => [
        'invite_token' => env('BUNSHIN_ONBOARDING_INVITE_TOKEN'),
    ],
    'billing' => [
        'enabled' => env('BUNSHIN_BILLING_ENABLED', false),
        'provider' => env('BUNSHIN_BILLING_PROVIDER'),
        'checkout' => [
            'success_url' => env(
                'BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL',
                rtrim((string) env('APP_URL', 'http://localhost'), '/').'/billing/success?session_id={CHECKOUT_SESSION_ID}',
            ),
            'cancel_url' => env(
                'BUNSHIN_BILLING_CHECKOUT_CANCEL_URL',
                rtrim((string) env('APP_URL', 'http://localhost'), '/').'/billing/cancel',
            ),
        ],
        'portal' => [
            'return_url' => env(
                'BUNSHIN_BILLING_PORTAL_RETURN_URL',
                rtrim((string) env('APP_URL', 'http://localhost'), '/').'/billing',
            ),
        ],
        'webhook_tolerance_seconds' => (int) env('BUNSHIN_BILLING_WEBHOOK_TOLERANCE_SECONDS', 300),
        'providers' => [
            'stripe' => [
                'secret_key' => env('BUNSHIN_STRIPE_SECRET_KEY'),
                'webhook_secret' => env('BUNSHIN_STRIPE_WEBHOOK_SECRET'),
                'api_base_url' => env('BUNSHIN_STRIPE_API_BASE_URL', 'https://api.stripe.com'),
            ],
        ],
        'price_plan_map' => [
            'stripe' => array_filter([
                env('BUNSHIN_STRIPE_PRO_PRICE_ID', '') => 'pro',
            ], static fn (string $planKey, string $priceId): bool => $priceId !== '', ARRAY_FILTER_USE_BOTH),
        ],
    ],
    'operations' => [
        'alert_email' => env('BUNSHIN_OPERATIONS_ALERT_EMAIL'),
        'tenant_purge' => [
            'schedule_enabled' => env('BUNSHIN_TENANT_PURGE_SCHEDULE_ENABLED', env('APP_ENV') === 'production'),
            'schedule_time' => env('BUNSHIN_TENANT_PURGE_SCHEDULE_TIME', '03:30'),
            'schedule_timezone' => env('BUNSHIN_TENANT_PURGE_SCHEDULE_TIMEZONE', 'UTC'),
            'schedule_limit' => (int) env('BUNSHIN_TENANT_PURGE_SCHEDULE_LIMIT', 50),
            'schedule_output_log' => env(
                'BUNSHIN_TENANT_PURGE_SCHEDULE_OUTPUT_LOG',
                storage_path('logs/tenant-purge-schedule.log'),
            ),
        ],
        'security_event_prune' => [
            'schedule_enabled' => env('BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_ENABLED', env('APP_ENV') === 'production'),
            'schedule_time' => env('BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_TIME', '04:15'),
            'schedule_timezone' => env('BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_TIMEZONE', 'UTC'),
            'schedule_limit' => (int) env('BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_LIMIT', 5000),
            'schedule_output_log' => env(
                'BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_OUTPUT_LOG',
                storage_path('logs/security-event-prune-schedule.log'),
            ),
        ],
    ],
    'security' => [
        'event_retention_days' => env('BUNSHIN_SECURITY_EVENT_RETENTION_DAYS', 180),
        'rate_limits' => [
            'login' => [
                'per_minute' => 10,
            ],
            'signup' => [
                'per_minute' => 5,
            ],
            'password_forgot' => [
                'per_minute' => 5,
            ],
            'password_reset' => [
                'per_minute' => 5,
            ],
            'password_change' => [
                'per_minute' => 5,
            ],
            'invitation_accept' => [
                'per_minute' => 5,
            ],
            'email_verification' => [
                'per_minute' => 5,
            ],
            'email_change' => [
                'per_minute' => 5,
            ],
            'secret_unlock_password_recovery_request' => [
                'per_minute' => 5,
            ],
            'secret_unlock_password_recovery_complete' => [
                'per_minute' => 5,
            ],
            'tenant_security_action' => [
                'per_minute' => 10,
            ],
            'account_lifecycle' => [
                'per_minute' => 5,
            ],
            'tenant_lifecycle' => [
                'per_minute' => 5,
            ],
            'billing' => [
                'per_minute' => 5,
            ],
        ],
        'secret_unlock_password_recovery' => [
            'expires_minutes' => 30,
        ],
    ],
    'plans' => [
        'free' => [
            'name' => 'Free',
            'limits' => [
                'memories' => 1000,
                'categories' => 100,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'limits' => [
                'memories' => null,
                'categories' => null,
            ],
        ],
    ],
];
