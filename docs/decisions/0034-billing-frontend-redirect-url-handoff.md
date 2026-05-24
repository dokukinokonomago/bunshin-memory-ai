# 0034: Billing Frontend Redirect URL Handoff

Date: 2026-05-17

## Status

Accepted for future product frontend integration.

## Context

The billing checkout and customer portal endpoints are already implemented as owner-only, verified-email, Bearer-token API actions. They return one-time hosted provider URLs. The backend also sends configured redirect URLs to the provider:

- `BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL`
- `BUNSHIN_BILLING_CHECKOUT_CANCEL_URL`
- `BUNSHIN_BILLING_PORTAL_RETURN_URL`

The product frontend is not implemented in this backend automation. The backend still needs a clear handoff contract so future frontend work does not accidentally treat browser redirects as billing state callbacks.

## Decision

Use server-side configured URLs as frontend handoff routes only. Do not add backend callback endpoints for checkout success, checkout cancel, or portal return in v1.

- `POST /api/v1/billing/checkout-sessions` returns a hosted checkout URL. The client navigates the owner to that URL.
- `POST /api/v1/billing/portal-sessions` returns a hosted portal URL. The client navigates the owner to that URL.
- `success_url`, `cancel_url`, and `return_url` remain backend config values. They are not request fields and are not controlled by the client.
- Checkout success redirects, checkout cancel redirects, customer portal returns, and client callbacks do not mutate local `tenants.plan_key`, `subscription_status`, `subscription_ends_at`, or provider linkage.
- Verified provider webhooks remain the normal source of truth for paid subscription state. Explicit operator reconciliation `--apply` remains the fallback source of truth after webhook outage.
- The backend must not store hosted checkout URLs, hosted portal URLs, redirect URLs, provider session ids, or raw redirect query strings in DB rows, logs, or `security_events`.
- The future frontend may read current tenant billing state through existing authenticated API state such as `GET /api/v1/auth/me` or the `tenant` object returned by billing session creation. It must display pending state when a success redirect happens before the verified webhook is processed.

## URL Configuration

Production deployments should point all three values at product frontend routes on the intended frontend origin:

- checkout success: a route such as `/billing/success?session_id={CHECKOUT_SESSION_ID}`.
- checkout cancel: a route such as `/billing/cancel`.
- portal return: a route such as `/billing`.

`{CHECKOUT_SESSION_ID}` is a provider placeholder passed through to the provider. The frontend can use it only for local display or support context. It must not send it to a backend endpoint to grant entitlement or change plan state.

The backend should continue rejecting client-supplied redirect URLs in v1 to avoid open redirect and tenant-confusion risks. If per-tenant or per-client redirect customization is needed later, it should be a separate signed allowlist design.

## Frontend State Contract

Future product frontend behavior:

- checkout button: call `POST /api/v1/billing/checkout-sessions` with local `plan_key`, then navigate to `data.url`.
- portal button: call `POST /api/v1/billing/portal-sessions`, then navigate to `data.url`.
- success route: show a payment-confirmation-pending state, refresh authenticated tenant state, and explain that plan access updates after provider confirmation if local state is still unchanged.
- cancel route: show a non-destructive canceled checkout state and keep current tenant plan display.
- portal return route: refresh authenticated tenant state. Do not assume a plan changed just because the owner returned from the provider portal.
- signed-out or expired-token state: ask the user to sign in again before refreshing tenant state. Do not trust redirect query parameters as identity.

Error handling expectations:

- `401`: token missing or expired; ask the owner to sign in.
- `403`: current user is not allowed to manage billing, email is not verified, account is inactive, or tenant context is invalid.
- `422`: unknown plan mapping, tenant linked to another provider, or portal requested before a provider customer exists.
- `429`: billing action rate limited; show retry guidance.
- `502` or `503`: billing provider unavailable or misconfigured; keep local plan state unchanged.

## Manual Smoke Checklist

Before connecting a production product frontend, use the production runbook at `docs/operations/billing_provider_production_smoke_runbook.md`.

1. Configure the three redirect URL env vars to product frontend routes and verify the provider request receives those exact values.
2. Create a checkout session and confirm the API response still shows the tenant's current local plan until a verified webhook is processed.
3. Follow the success redirect and verify the frontend only refreshes tenant state; it must not call any plan mutation endpoint.
4. Follow the cancel redirect and verify no local billing state changes.
5. Create a portal session, return from the portal, and verify the frontend refreshes tenant state without assuming a plan change.
6. Process a verified provider webhook and verify local `plan_key` / `subscription_status` update only through webhook processing.
7. Confirm DB rows, application logs, and `security_events` do not contain hosted provider URLs, provider session ids, raw redirect query strings, provider ids, or billing PII.

## Consequences

- Product frontend work can connect billing actions without new backend state mutation endpoints.
- Redirect routes are safe UX handoff points, not billing authority.
- The existing `BillingSessionApiTest` coverage remains the backend regression anchor for configured provider redirect URLs and no local plan mutation during session creation.
- Production config and frontend route smoke are documented in `docs/operations/billing_provider_production_smoke_runbook.md`.
- Any future need for per-client redirects, success verification endpoints, billing polling endpoints, or customer-facing billing messages must be designed as a separate task without weakening webhook authority.
