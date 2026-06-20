# Billing Provider Production Smoke Runbook

## Purpose

This runbook verifies that production billing configuration, hosted checkout / customer portal handoff, provider webhooks, and frontend redirect routes are wired without making browser redirects authoritative for billing state.

Use it before enabling a production product frontend billing entry point, after rotating provider billing secrets, after changing price mappings, or after changing checkout / portal redirect URLs.

## Source Of Truth

Local tenant billing fields remain the API runtime source of truth:

- `tenants.plan_key`
- `tenants.subscription_status`
- `tenants.trial_ends_at`
- `tenants.subscription_ends_at`

Paid subscription state may update local fields only through:

- verified provider webhooks accepted by `POST /api/v1/billing/webhooks/{provider}`
- explicit operator reconciliation with `php artisan bunshin:reconcile-billing-provider ... --apply`

Checkout success redirects, checkout cancel redirects, customer portal returns, browser callbacks, and redirect query strings must not mutate local billing state or grant paid entitlements.

## Required Production Configuration

Set these through the approved secret / config deployment path. Do not paste secret values into tickets, chat, runbook notes, logs, or command output.

- `BUNSHIN_BILLING_ENABLED=true`
- `BUNSHIN_BILLING_PROVIDER=stripe`
- `BUNSHIN_STRIPE_SECRET_KEY`: server-side provider API key
- `BUNSHIN_STRIPE_WEBHOOK_SECRET`: webhook signing secret for the production webhook endpoint
- `BUNSHIN_STRIPE_API_BASE_URL`: optional override; defaults to `https://api.stripe.com`
- `BUNSHIN_STRIPE_PRO_PRICE_ID`: provider price id mapped to local `pro`
- `BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL`: product frontend success route
- `BUNSHIN_BILLING_CHECKOUT_CANCEL_URL`: product frontend cancel route
- `BUNSHIN_BILLING_PORTAL_RETURN_URL`: product frontend billing route

The three redirect URL values should point at the intended product frontend origin, not at backend callback endpoints. Keep `{CHECKOUT_SESSION_ID}` only where the provider requires a checkout-session placeholder. The frontend may display or retain it locally for support context, but it must not send it to a backend endpoint to grant entitlement.

After deployment, reload config / workers using the normal production release procedure before running smoke tests. If the app uses cached Laravel config, verify the running process sees the new values before sending provider traffic.

## Preflight Checklist

1. Confirm the deployment target and provider account are the intended production pair.
2. Confirm the smoke tenant public id and owner account are approved for billing testing.
3. Confirm the owner account is active, has role `owner`, and has verified email.
4. Confirm `BUNSHIN_STRIPE_PRO_PRICE_ID` maps to the intended production `pro` price.
5. Confirm the provider webhook endpoint is configured as `/api/v1/billing/webhooks/stripe`.
6. Confirm the provider webhook signing secret belongs to that endpoint and environment.
7. Confirm success / cancel / return URL env vars use the intended frontend origin.
8. Confirm no frontend route calls a backend plan mutation endpoint after redirect.

Do not perform a real paid transaction in production unless the billing owner has approved the test account, payment method, price, and cancellation / cleanup path.

## Operator Readiness Command

Run this inside the target app runtime before creating provider sessions:

```sh
php artisan bunshin:billing-smoke-readiness --tenant="${SMOKE_TENANT_PUBLIC_ID}"
```

Provide smoke hints through environment variables or the approved operator secret path:

- `BUNSHIN_BILLING_SMOKE_API_ORIGIN`
- `BUNSHIN_BILLING_SMOKE_FRONTEND_ORIGIN`
- `BUNSHIN_BILLING_SMOKE_OWNER_TOKEN`
- `BUNSHIN_BILLING_SMOKE_PROVIDER_CONFIRMED=true`

The command performs no provider calls and creates no checkout, portal, subscription, or webhook records. It checks only scrub-safe prerequisites: billing provider configuration, pro price mapping presence, explicit redirect URL configuration, API / frontend origin hints, owner token presence, provider-account confirmation, and the approved smoke tenant's active verified owner state.

The output intentionally does not print secret values, Bearer tokens, hosted URLs, provider customer / subscription / price ids, tenant public ids, tenant slugs, or owner emails. If the command exits non-zero, do not run production billing smoke yet.

## Backend Session Smoke

Use a verified tenant owner Bearer token. Filter API output so hosted URLs are not copied into terminal logs or tickets.

Create a checkout session:

```sh
curl -sS \
  -H "Authorization: Bearer ${OWNER_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"plan_key":"pro"}' \
  "${API_ORIGIN}/api/v1/billing/checkout-sessions" \
  | jq '.data | del(.url)'
```

Expected:

- response status is `201`
- `data.mode` is `checkout`
- `data.provider` is `stripe`
- `data.plan_key` is `pro`
- `data.tenant.plan_key` and `data.tenant.subscription_status` still show the current local tenant state until a verified webhook is processed
- `security_events` has a scrub-safe `billing.checkout_session.create` success event with provider / plan only

Create a portal session only after the tenant has a provider customer:

```sh
curl -sS \
  -H "Authorization: Bearer ${OWNER_TOKEN}" \
  -H "Content-Type: application/json" \
  -X POST \
  "${API_ORIGIN}/api/v1/billing/portal-sessions" \
  | jq '.data | del(.url)'
```

Expected:

- response status is `201`
- `data.mode` is `portal`
- `data.provider` is `stripe`
- local tenant plan / status do not change just because a portal session was created
- `security_events` has a scrub-safe `billing.portal_session.create` success event with provider only

Expected failures:

- missing token: `401`
- non-owner, inactive account, unverified email, or invalid tenant context: `403`
- unknown plan, missing provider customer for portal, or provider mismatch: `422`
- billing action rate limit: `429`
- provider request failure: `502`
- disabled or incomplete billing config: `503`

## Frontend Route Smoke

Run this with the product frontend that will own the billing UI.

1. From the billing UI, start checkout for `pro`.
2. Confirm the frontend navigates to the hosted checkout URL returned by the API.
3. Confirm the provider checkout request receives the configured success and cancel URLs.
4. Complete or simulate checkout according to the approved provider test procedure.
5. On success redirect, confirm the frontend shows payment confirmation pending when local tenant state has not yet changed.
6. Confirm the success route refreshes authenticated tenant state through existing authenticated API state, such as `GET /api/v1/auth/me`, and does not call a plan mutation endpoint.
7. Repeat checkout cancel and confirm the cancel route keeps the current local plan display.
8. Open the customer portal, return to the frontend, and confirm the return route refreshes tenant state without assuming a plan changed.
9. Confirm signed-out or expired-token redirect visits ask the owner to sign in before refreshing tenant state.
10. Confirm redirect query parameters are not sent back to the backend as billing authority.

The frontend may temporarily show pending state. It must not display `pro` entitlement as granted until the authenticated tenant state reflects the webhook- or reconciliation-updated local fields.

## Webhook Smoke

Verify provider webhook delivery using the provider dashboard or approved webhook test mechanism for the production endpoint.

Expected:

- missing or invalid signature returns `400`
- disabled or misconfigured provider returns `503`
- duplicate provider event ids are accepted idempotently
- known customer / subscription / price / status updates local tenant billing fields
- unknown tenant, customer, subscription, price, or status does not grant paid entitlements
- `billing_webhook_events` stores event id, type, livemode, payload hash, processing status, and scrubbed error fields only
- `security_events` stores scrub-safe `billing.webhook.sync` metadata only

If webhook delivery fails or provider-local state drifts, use `docs/operations/billing_provider_reconciliation_runbook.md`. Run reconciliation in dry-run first; use `--apply` only after the mapped drift is understood and safe.

## Data Scrub Verification

Use public ids, status codes, aggregate counts, and scrub-safe metadata in durable notes.

Confirm billing session security events do not contain hosted URLs:

```sql
select event_type, outcome, metadata, created_at
from security_events
where event_type in ('billing.checkout_session.create', 'billing.portal_session.create')
order by created_at desc
limit 10;
```

Confirm webhook rows store hashes and scrubbed status rather than raw payloads:

```sql
select billing_provider, event_type, livemode, processing_status, error_code, received_at, processed_at
from billing_webhook_events
order by received_at desc
limit 10;
```

Do not export routine verification output containing provider customer ids, subscription ids, price ids, checkout session ids, portal session ids, card data, billing address, tax id, raw customer email, hosted URLs, raw webhook payloads, raw redirect query strings, provider API keys, webhook signing secrets, Bearer tokens, password material, memory content, category names, or tag names.

If privileged lookup of provider ids is required, keep it inside approved production consoles or privileged database sessions and do not paste those values into tickets or chat.

## Failure Handling

- Config failure: fix env / secret deployment, reload the app, then repeat backend session smoke.
- Provider request failure: check provider status and application logs without copying raw provider response bodies.
- Webhook signature failure: confirm endpoint URL, signing secret, provider environment, timestamp tolerance, and app clock.
- Unknown price mapping: confirm `BUNSHIN_STRIPE_PRO_PRICE_ID` and provider subscription item price.
- Frontend grants plan on redirect: stop rollout and remove the state mutation path before continuing.
- DB / log scrub failure: stop rollout, remove persisted sensitive data through an approved incident process, and add regression coverage before retrying.
- Production paid test needs refund, credit, proration, invoice, dunning, or dispute handling: stop and follow decision 0032. Engineering should not make finance / legal policy decisions inside smoke testing.

## Rollback

If the production smoke fails before customer exposure:

1. Disable the frontend billing entry point.
2. Set `BUNSHIN_BILLING_ENABLED=false` or remove the frontend billing action while keeping webhook handling decisions documented.
3. Do not edit tenant local plan / status manually to compensate for redirects.
4. Use verified webhooks or reconciliation dry-run / approved `--apply` to correct known provider-local drift.
5. Keep a scrub-safe incident note with tenant public ids, result codes, timestamps, and field names only.

## Related References

- Billing provider decision: `docs/decisions/0029-billing-provider-integration.md`
- Billing redirect handoff decision: `docs/decisions/0034-billing-frontend-redirect-url-handoff.md`
- Billing reconciliation runbook: `docs/operations/billing_provider_reconciliation_runbook.md`
- Tenant archive cancellation failure runbook: `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md`
- API contract billing section: `docs/architecture/api_contract.md`
- Billing controller: `app/Http/Controllers/Api/V1/BillingController.php`
- Provider client: `app/Support/Billing/StripeBillingClient.php`
