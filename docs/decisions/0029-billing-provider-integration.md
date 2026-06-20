# 0029: Billing Provider Integration And Webhook Handling

Date: 2026-05-16

## Status

Accepted and implemented for provider-neutral billing schema, checkout / customer portal API, provider webhook processing, and provider-local reconciliation.

## Context

Decision 0009 added the local subscription / quota baseline on `tenants`: `plan_key`, `subscription_status`, `trial_ends_at`, and `subscription_ends_at`. `TenantQuotaGuard` already uses those local fields to decide whether `POST /api/v1/memories` and `POST /api/v1/categories` may create new rows.

This decision covers paid-plan production behavior: mapping a billing provider customer and subscription to a tenant, receiving subscription webhooks, and deciding how provider state becomes local plan / quota state without making every write API call synchronously depend on a billing provider. Tenant archive provider cancellation / refund handling is decided separately in decision 0030. Dedicated archive cancellation retry command scope is decided separately in decision 0031. Automated billing adjustments are deferred by decision 0032. Customer-visible dispute / refund request intake is deferred by decision 0033. Product frontend redirect URL handoff for checkout success / cancel and portal return is decided in decision 0034.

## Decision

- Keep local tenant fields as the API runtime source of truth. Protected API requests do not call the billing provider synchronously to decide quota or active subscription.
- Treat verified provider webhooks as the source of truth for paid subscription state updates. Checkout success redirects, client callbacks, and portal returns are not authoritative.
- Support one configured production billing provider at a time in v1. The first implementation may use a Stripe-compatible hosted checkout / customer portal / webhook adapter, but public API contracts and database field names should stay provider-neutral.
- Keep the free plan local. Free tenants do not require a provider customer id.
- Paid plan activation happens only after a verified webhook maps a provider subscription or checkout session to a tenant and the provider price id maps to a known local `plan_key`.
- Continue using `config/bunshin.php` plan limits as the entitlement source. Provider price ids select the local `plan_key`; they do not define resource limits directly.
- Do not expose provider ids in public tenant payloads by default. Tenant export may include scrub-safe billing state, but not raw webhook payloads, billing addresses, card details, or provider portal URLs.
- Billing management endpoints are future owner-only tenant lifecycle actions. They require Bearer token auth, active account, tenant context, `role=owner`, verified email, and a tenant lifecycle or billing-specific rate limit.
- Tenant archive remains stronger than billing state. An archived tenant is inactive even if a later provider webhook says the subscription is active. Archive provider cancellation / refund handling follows decision 0030, dedicated retry command handling follows decision 0031, automated refund / credit / proration / invoice / dunning / dispute / period-end cancellation handling follows decision 0032, and customer-visible dispute / refund request intake follows decision 0033.
- Checkout success URLs, checkout cancel URLs, and customer portal return URLs are server-side config for future product frontend routes. They are not backend billing callbacks, are not client request fields, and do not mutate local billing state. Detailed frontend handoff rules follow decision 0034.

## Planned Provider Data

Implemented provider-neutral billing fields on `tenants`:

- `billing_provider`: nullable string, for example `stripe`.
- `billing_customer_id`: nullable provider customer id, unique with `billing_provider`.
- `billing_subscription_id`: nullable provider subscription id, unique with `billing_provider`.
- `billing_price_id`: nullable provider price id used for the current subscription item.
- `billing_cancel_at_period_end`: boolean.
- `billing_last_synced_at`: nullable datetime.

Keep existing local fields:

- `plan_key`: local entitlement key such as `free` or `pro`.
- `subscription_status`: local gate value: `active`, `trialing`, `past_due`, `canceled`, or `incomplete`.
- `trial_ends_at`: local trial end used by `Tenant::hasActivePlan()`.
- `subscription_ends_at`: local access end used by `Tenant::hasActivePlan()`.

Provider status mapping:

- provider `trialing` maps to local `trialing`.
- provider `active` maps to local `active`.
- provider `past_due`, `unpaid`, or payment-failed states map to local `past_due`.
- provider `canceled` maps to local `canceled`.
- provider `incomplete` or `incomplete_expired` maps to local `incomplete`.
- unknown provider statuses must not grant paid entitlements; mark the webhook processing result as needing operator review and keep or downgrade local state according to the implementation's explicit safety rule.

Cancellation at period end should keep local `subscription_status=active` and set `subscription_ends_at` to the provider period end. Immediate cancellation should set local `subscription_status=canceled` and `subscription_ends_at` to the cancellation time.

## Planned Webhook Storage

Implemented `billing_webhook_events` table for future idempotency and operational debugging without storing raw billing payloads:

- `billing_provider`
- `provider_event_id`, unique with `billing_provider`
- `event_type`
- `livemode`
- `tenant_id`, nullable until matched
- `billing_customer_id`, nullable
- `billing_subscription_id`, nullable
- `payload_hash`
- `received_at`
- `processed_at`, nullable
- `processing_status`: `received`, `processed`, `ignored`, or `failed`
- `error_code`, nullable
- `error_message`, nullable and scrubbed

Do not store the raw webhook body by default. The raw request body may be used in-memory for signature verification and hashed for deduplication evidence. Logs, security events, and webhook rows must not contain card data, billing address, tax id, raw customer email, raw payload, signature secret, portal URLs, checkout URLs, or provider API keys.

## Webhook Handling

The implemented webhook receiver is a public endpoint outside Bearer auth, protected by provider signature verification and an enabled provider config. Missing or invalid signature returns `400` or provider-compatible failure.

Implemented behavior:

- Process duplicate `provider_event_id` as idempotent no-ops and return success to the provider after confirming the first event was already accepted.
- Match tenants by existing `billing_provider` + `billing_customer_id` / `billing_subscription_id`; for checkout completion, match the session metadata or client reference `tenant_public_id` that was created by the authenticated backend checkout endpoint.
- Never create a tenant from a webhook.
- Never trust client-supplied checkout completion data to update `plan_key` or `subscription_status`.
- If a webhook references an unknown tenant, customer, subscription, or price id, store a scrubbed failed or ignored webhook record and avoid granting paid entitlements.
- Keep processing fast. Long provider fetches, reconciliation, or notification work should move to a queued job if needed.
- Emit only scrub-safe `billing.webhook.sync` security events for subscription sync result. Do not store raw provider payloads in `security_events`.

Initial event types to support:

- checkout session completed, to link tenant, customer, subscription, and selected price when it was initiated by this backend.
- subscription created / updated / deleted, to update local plan and status.
- invoice payment succeeded / failed, only when needed to confirm or explain subscription state; subscription events remain the primary state transition source.

## Checkout And Portal Scope

Implemented public API scope:

- `POST /api/v1/billing/checkout-sessions`: owner-only and verified-email gated. Creates a hosted checkout session for a requested known local plan / price mapping and returns a one-time provider URL. It may lazily create and store a provider customer id, but it does not change local `plan_key` or `subscription_status`.
- `POST /api/v1/billing/portal-sessions`: owner-only and verified-email gated. Creates a hosted customer portal session for an existing provider customer and returns a one-time provider URL. It does not change local `plan_key` or `subscription_status`.
- No public endpoint accepts provider customer id, subscription id, price id, or status from the client.

The checkout endpoint creates a provider customer lazily if the tenant has none. Provider metadata contains only stable public ids needed for matching, such as tenant public id and initiating owner public id. It must not include memory content, secret content, category/tag names, credentials, or raw PII beyond what the provider requires for billing. Checkout / portal URLs are returned to the authenticated client but are not stored in DB rows, logs, or `security_events`.

Configured redirect URLs are provider handoff routes for the future product frontend only:

- checkout success route: configured by `BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL`.
- checkout cancel route: configured by `BUNSHIN_BILLING_CHECKOUT_CANCEL_URL`.
- portal return route: configured by `BUNSHIN_BILLING_PORTAL_RETURN_URL`.

The frontend may refresh tenant state after these redirects, but it must not use redirect query strings, checkout session ids, or portal return visits to grant paid entitlement. Verified webhooks and explicit reconciliation `--apply` remain the only backend billing state synchronization paths. See decision 0034 for frontend UI state, error handling, and manual smoke rules.

## Consequences

- Existing quota behavior remains valid until provider integration is implemented.
- Paid entitlements become deterministic: local DB fields drive API behavior, while webhooks are the only normal path that mutates those fields from provider state.
- The product avoids accidental plan upgrades from browser redirects or tampered client payloads.
- Provider-specific details are isolated to a future adapter and config mapping, while tenant payloads and first-party clients stay stable.
- Provider-local reconciliation is available as an internal operator command for webhook outages. It is dry-run by default and only mutates local tenant billing fields with explicit `--apply` when provider customer, subscription, price mapping, and status are all known and safe.

## Implementation Split

1. Done: add tenant billing provider fields, `billing_webhook_events` schema / model, config stubs for provider and price-to-plan mapping, and tests for model casting / uniqueness.
2. Done: implement billing checkout and portal session endpoints with owner-only and verified-email gates.
3. Done: implement provider webhook receiver with signature verification, idempotency, subscription status mapping, and scrub-safe audit events.
4. Done: add reconciliation command / operations runbook for provider-local drift after webhook outages.
5. Done: add tenant archive billing cancellation failure triage runbook.
6. Done: decide that a dedicated tenant archive billing cancellation retry command is deferred in v1.
7. Done: decide that automated billing adjustments remain deferred in v1 until product / finance / legal policy exists.
8. Done: decide that customer-visible billing dispute / refund request intake remains deferred in v1 and uses support-only escalation outside the product backend.
9. Done: add production billing provider env / frontend smoke runbook for hosted checkout, portal, webhook, redirect, and scrub verification.

## Current Boundary

Tenant archive billing provider cancellation handling is implemented in backend code and tests. The tenant archive billing cancellation failure triage runbook is complete at `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md`. Dedicated retry command implementation is deferred in decision 0031. Refunds, credits, proration, invoice finalization, dunning, disputes, and period-end cancellation are deferred in decision 0032 until a product / finance / legal policy exists. Customer-visible dispute / refund request intake is deferred in decision 0033 and remains support-only outside the product backend.

Production billing provider configuration and frontend handoff smoke are documented in `docs/operations/billing_provider_production_smoke_runbook.md`. The runbook preserves the same source-of-truth boundary: checkout / portal redirects are UX handoff only, while verified webhooks and explicit reconciliation `--apply` are the only paths that synchronize paid subscription state into local tenant fields.

## Reconciliation Command

Implemented `php artisan bunshin:reconcile-billing-provider`.

- Default mode is dry-run: it compares mapped provider subscription state with local tenant billing fields and changes no rows.
- `--apply` is explicit and updates only safe mapped drift: non-archived tenant, matching customer, known provider subscription, known price-to-plan mapping, and known provider status.
- Tenant target accepts public id or slug. `--limit` accepts `1` to `500`.
- Tenants with a customer id but no local subscription id are reconciled only when the provider has exactly one subscription for that customer. Ambiguous provider subscriptions are skipped.
- Unknown customer / subscription / price / status, archived tenants, and provider failures do not grant paid entitlements.
- Apply mode writes scrub-safe `billing.reconciliation` security events with provider, mode, result, local plan/status, and changed field names only.
- Command output and security events must not include provider customer ids, subscription ids, price ids, raw provider responses, raw webhook payloads, billing address, card data, tax id, raw customer email, provider API keys, or signing secrets.

Operations runbook: `docs/operations/billing_provider_reconciliation_runbook.md`.

## Tenant Archive Cancellation

Decision 0030 is the source of truth for archive-driven provider cancellation. Decision 0031 is the source of truth for dedicated retry command deferral. Decision 0032 is the source of truth for automated billing adjustment deferral. Decision 0033 is the source of truth for customer-visible dispute / refund request-flow deferral. The implemented short version is: archive remains local-first, provider cancellation is a side effect after the archive transaction, provider failure never reopens the tenant, initial cancellation is immediate, v1 has no dedicated retry command, v1 does not automate refunds, credits, proration, invoice finalization, dunning, disputes, or period-end cancellation, and v1 does not collect customer-visible dispute / refund requests in the product backend. Operator triage for cancellation failures is documented in `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md`.
