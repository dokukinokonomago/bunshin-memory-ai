# 0032: Automated Billing Adjustments Policy

Date: 2026-05-17

## Status

Accepted. Automated billing adjustments are deferred in v1 and require product / finance / legal policy before implementation.

## Context

Decision 0029 implemented provider-neutral billing linkage, hosted checkout / portal, verified webhooks, and provider-local reconciliation. Decision 0030 implemented archive-first tenant closure with immediate linked provider subscription cancellation as a side effect. Decision 0031 deferred a dedicated retry command for archive cancellation failures. Decision 0033 separately defers customer-visible dispute / refund request intake. Decision 0034 defines checkout / portal frontend redirect URL handoff without granting billing authority to redirects.

Those decisions intentionally excluded automated refunds, credits, proration, invoice finalization, dunning, disputes, and period-end cancellation. This decision records whether those flows can be implemented now and what must be decided before a future implementation.

## Decision

- Do not implement automated refunds, credits, prorations, invoice finalization, dunning handling, dispute handling, or period-end cancellation in v1.
- Keep tenant archive billing behavior as decision 0030 defines it: local archive first, then immediate cancellation of the already-linked provider subscription, with no proration, no refund, no invoice-finalization action, and no period-end cancellation option.
- Do not add public request fields, OpenAPI parameters, admin mockup controls, or memory-space controls for refund, credit, proration, invoice, dunning, dispute, cancellation date, provider object id, or period-end cancellation.
- Engineering triage may not decide financial adjustments. If provider console cancellation asks for refund, credit, proration, invoice, dunning, or dispute choices, stop and escalate to the product / finance / legal owner.
- Manual provider-console operations remain allowed only under approved operational access and an explicit product / finance / legal decision for the specific case. Durable notes must use scrub-safe references only.
- Future automation must be a separate task and decision. It must not be added as an extension of tenant archive retry handling or reconciliation without a signed-off adjustment policy.
- Customer-visible request intake, even if it does not issue refunds automatically, is out of scope for v1 and follows decision 0033.

## Required Future Policy Inputs

Before implementing any automated adjustment flow, product / finance / legal must decide:

- Eligible triggers: archive, downgrade, duplicate charge, provider outage, support goodwill, legal cancellation right, chargeback, tax correction, or other named cases.
- Adjustment type by trigger: refund, account credit, credit note, invoice void, invoice finalization, period-end cancellation, no-op, or manual review only.
- Amount calculation: full vs partial, proration formula, tax and fee treatment, minimum/maximum thresholds, currency handling, and rounding rules.
- Authority model: customer self-service, owner-only request, internal operator action, finance approval, dual approval, and role / verified-email requirements.
- Provider behavior: which provider APIs are allowed, whether invoice finalization is permitted, whether refunds can be issued against paid invoices only, and how provider failures are retried.
- Customer communication: receipt, credit memo, cancellation notice, support ticket language, and whether the API should return customer-visible adjustment status.
- Accounting and legal retention: what evidence must be retained, for how long, and which fields are prohibited from app DB, logs, tickets, chat, and `security_events`.
- Idempotency and reversal: idempotency keys, duplicate prevention, retry windows, reversal policy, dispute state transitions, and operator review queues.
- Rollout and verification: sandbox test matrix, provider webhook interactions, reconciliation behavior, reporting requirements, and production monitoring.

## Audit And Storage Rules

V1 stores no refund id, invoice id, credit note id, dispute id, provider object URL, raw provider response, raw provider error body, billing address, card data, tax id, raw customer email, provider API key, or signing secret in durable app storage, logs, OpenAPI examples, tickets, chat, or `security_events`.

If a future approved adjustment flow requires durable provider linkage beyond the existing tenant billing fields, that storage design must be reviewed separately before implementation. The default remains scrub-safe metadata only: local tenant public id, event type, result, reason code, policy key, timestamps, aggregate counts, and changed local field names.

## API Contract

No v1 public HTTP endpoint performs billing adjustments. Existing billing endpoints keep their current scope:

- `POST /api/v1/billing/checkout-sessions` creates a hosted checkout session and does not mutate local plan state.
- `POST /api/v1/billing/portal-sessions` creates a hosted portal URL and does not mutate local plan state.
- `POST /api/v1/billing/webhooks/{provider}` syncs known subscription state from verified provider events and does not issue adjustments.
- `POST /api/v1/tenant/archive` archives locally and attempts immediate linked subscription cancellation only.

## Consequences

- There is no runtime code, database, test, or OpenAPI endpoint implementation to add for this task.
- Existing archive behavior remains intentionally conservative and finance-safe.
- Future billing adjustment work starts from this decision and must first convert the required policy inputs into an explicit implementation decision.
- Future customer-visible dispute / refund intake starts from decision 0033, not from an incidental extension of checkout, portal, archive, reconciliation, or webhook handling.

## Related References

- Billing provider integration: `docs/decisions/0029-billing-provider-integration.md`
- Tenant archive cancellation: `docs/decisions/0030-tenant-archive-billing-provider-cancellation.md`
- Archive cancellation retry command: `docs/decisions/0031-tenant-archive-billing-cancellation-retry-command.md`
- Archive cancellation runbook: `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md`
- Customer-visible request flow: `docs/decisions/0033-customer-billing-dispute-refund-request-flow.md`
- Billing frontend redirect handoff: `docs/decisions/0034-billing-frontend-redirect-url-handoff.md`
