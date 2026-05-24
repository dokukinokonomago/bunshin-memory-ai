# 0030: Tenant Archive Billing Provider Cancellation And Refund Handling

Date: 2026-05-16

## Status

Accepted. Backend implementation, operations runbook, and retry-command necessity decision are complete.

## Context

Decision 0024 implemented archive-first tenant closure. `POST /api/v1/tenant/archive` freezes the tenant, revokes tenant credentials, revokes pending invitations, and closes local subscription state immediately. Decision 0029 then implemented provider-neutral billing fields, hosted checkout / portal endpoints, verified webhook processing, and provider-local reconciliation.

The remaining gap is what archive should do to an already-linked provider subscription. The product must avoid leaving a paid provider subscription running after local tenant access is revoked, but provider failure must not leave the tenant half-open after a confirmed archive request.

## Decision

- Keep archive-first semantics. Local tenant archive, credential revocation, invitation revocation, `subscription_status=canceled`, and `subscription_ends_at=archive time` remain authoritative once the archive transaction commits.
- Provider cancellation is a side effect of archive, not part of the database transaction. Do not call the billing provider while holding tenant row locks.
- Provider cancellation failure must not roll back local archive or reactivate the tenant. The archived tenant remains inactive even if cancellation fails, if a later provider webhook reports `active`, or if reconciliation sees an active provider subscription.
- Initial implementation should attempt provider cancellation only when billing is enabled, the tenant has an existing `billing_provider` and `billing_subscription_id`, and the tenant provider matches the configured provider adapter.
- Do not search for, create, or infer provider subscriptions during archive. Use only the subscription id already linked on the tenant.
- Initial provider action is immediate subscription cancellation. Do not expose period-end cancellation, refund, credit, proration, invoice-finalization, or cancellation-date options on the tenant archive API.
- No automatic refunds or credits in v1. Refunds, credits, disputed invoices, and proration policy are deferred in decision 0032 and require separate product / finance / legal approval before any future implementation.
- Do not add customer-visible refund / dispute request intake to tenant archive in v1. Request-flow intake is deferred in decision 0033 and remains support-only outside the product backend.
- Provider cancellation should be idempotent where the provider allows it. A subscription already canceled is a successful no-op. Unknown provider references or provider request failures require operator review but do not change the local archive result.
- If cancellation succeeds, keep local archive fields canceled and set only scrub-safe local sync fields that are truthful after success, such as `billing_cancel_at_period_end=false` and `billing_last_synced_at=now()`.
- If cancellation is skipped or fails, preserve the local archive result and record a scrub-safe event for operator follow-up. Do not mark local subscription active or extend access to match provider state.

## Audit And Storage Rules

- Add a scrub-safe billing security event named `billing.subscription_cancel.request` for archive-driven cancellation.
- Event outcome should distinguish `success`, `failure`, and safe skipped states via metadata reason codes.
- Allowed metadata: provider key, archive cancellation policy (`immediate_no_proration_no_refund`), result (`succeeded`, `skipped`, `requires_operator_review`), reason code, previous local plan/status, and changed local field names.
- Do not store provider customer id, provider subscription id, provider price id, refund id, invoice id, hosted URL, provider raw response, raw provider error body, provider API key, raw customer email, card data, billing address, tax id, or cancellation provider secret in DB rows, logs, OpenAPI examples, or `security_events`.
- `auth.tenant.archive` remains the tenant lifecycle audit event. The billing cancellation event is a separate billing side-effect audit event.

## API Contract

- `POST /api/v1/tenant/archive` remains owner-only and archive-first.
- The implementation adds `data.billing_provider_cancellation.status` as an additive response field. Clients must not rely on provider cancellation as proof of local archive. Local archive success is represented by the existing `202 Accepted` response and archive timestamps.
- User request payload must not accept provider ids, refund flags, proration flags, cancellation date, or period-end cancellation flags.
- If provider cancellation requires operator review after local archive succeeded, the endpoint should still return `202 Accepted` with a safe status code or reason in the response body. It should not return raw provider errors.

## Webhook And Reconciliation Interaction

- Verified provider webhooks after archive must continue to ignore archived tenants and must not reactivate access or paid entitlements.
- Provider-local reconciliation must continue to skip archived tenants and must not apply active provider state to archived tenants.
- If a later provider cancellation webhook arrives, it may be recorded in `billing_webhook_events`, but it must not alter the archived tenant's local access state.

## Consequences

- Tenant closure remains reliable from the user's perspective: access is revoked and deletion is scheduled even when the billing provider is degraded.
- Billing operators get a clear follow-up signal for cancellation failures without leaking provider identifiers or billing PII into local logs.
- The product avoids accidental refunds or credits before a finance policy exists.
- The next implementation can be small: add a provider adapter cancellation method, call it after archive commit, log a scrub-safe billing event, and cover success / skipped / failure cases in Feature tests.

## Implementation Split

1. Done: decide archive billing cancellation / refund handling in this document.
2. Done: implement tenant archive billing provider cancellation handling in backend code and tests.
3. Done: add an operations runbook for cancellation failure triage.
4. Done: decide that a dedicated tenant archive billing cancellation retry command is deferred in v1.
5. Done: defer automated refunds, credits, proration, dunning, disputes, invoice finalization, and period-end cancellation in decision 0032.
6. Done: defer customer-visible billing dispute / refund request intake in decision 0033.

## Current Boundary

Dedicated retry command decision is complete in decision 0031 and is deferred in v1. Automated refund, credit, proration, invoice finalization, dunning, dispute, and period-end cancellation policy is complete in decision 0032: these flows remain deferred until product / finance / legal policy exists. Customer-visible dispute / refund request intake is complete in decision 0033 and remains support-only outside the product backend.

Operations runbook: `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md`.
Retry command decision: `docs/decisions/0031-tenant-archive-billing-cancellation-retry-command.md`.
Billing adjustment policy: `docs/decisions/0032-automated-billing-adjustments-policy.md`.
Customer-visible request flow: `docs/decisions/0033-customer-billing-dispute-refund-request-flow.md`.
