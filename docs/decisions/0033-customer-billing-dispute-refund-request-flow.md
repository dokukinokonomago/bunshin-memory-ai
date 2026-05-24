# 0033: Customer Billing Dispute And Refund Request Flow

Date: 2026-05-17

## Status

Accepted. Customer-visible billing dispute / refund request intake is deferred in v1. Current handling is support-only escalation outside the product backend.

## Context

Decision 0032 deferred automated refunds, credits, proration, invoice finalization, dunning, disputes, and period-end cancellation until product / finance / legal policy exists.

The remaining question is whether v1 should still add a customer-visible request flow, such as a public API endpoint or in-app form that lets a tenant owner ask for a refund, dispute review, credit, or cancellation adjustment without actually issuing the adjustment automatically.

## Decision

- Do not implement a customer-visible billing dispute / refund request endpoint, in-app form, admin mockup control, database table, status model, or provider workflow in v1.
- Treat refund / dispute intake as a finance-sensitive workflow, not a harmless contact form. Even request-only intake creates expectations, evidence retention obligations, response SLA questions, and billing PII handling requirements.
- Keep the existing hosted customer portal as the only customer-facing billing self-service surface exposed by the backend. Portal returns, checkout success redirects, and customer messages do not mutate local billing state. Frontend redirect URL handoff is decided in decision 0034.
- Use support-only escalation outside the product backend for exceptional refund, dispute, duplicate charge, goodwill credit, tax correction, or cancellation-right cases.
- Internal operators may review a case only under approved support / finance / legal process and provider-console access. Engineering triage must not decide eligibility, amount, provider action, customer communication, or legal response.
- Do not store a local refund request, dispute request, support ticket id, provider case id, invoice id, charge id, refund id, dispute id, provider object URL, raw customer explanation, attachment, receipt, billing address, tax id, card data, raw customer email, raw provider response, or provider error body in app DB rows, logs, OpenAPI examples, tickets copied from the app, or `security_events`.
- Future customer-visible intake requires a separate decision before implementation.

## Current Support-Only Workflow

- If a customer asks for a refund or dispute review, route them to the approved support channel or provider-hosted portal outside the product backend.
- Support / finance / legal decides whether the case is eligible, what evidence is required, what response should be sent, and whether provider-console action is allowed.
- Operators must use scrub-safe references in internal notes: local tenant public id, case category, policy decision key, outcome category, timestamp, and responsible owner. Do not copy billing provider ids, raw explanations, receipts, card data, billing addresses, tax ids, or provider raw responses into app-managed records.
- No local API response should claim that a refund, dispute, credit, or adjustment request has been accepted, queued, approved, denied, or completed.

## Future Policy Inputs

Before implementing customer-visible intake, product / finance / legal must decide:

- Eligible request types: duplicate charge, cancellation right, accidental renewal, tax correction, provider outage, service downtime, goodwill credit, chargeback, or other named cases.
- Intake authority: tenant owner only, verified email required, account status requirements, archived tenant behavior, and whether non-owner members can view or create requests.
- Required fields and prohibited fields: what the customer may submit, attachment rules, evidence retention, deletion, export, and regional storage.
- Status model: received, needs information, under review, approved, denied, provider pending, provider completed, reversed, closed, and which statuses are customer-visible.
- SLA and ownership: support owner, finance approval, legal approval, dual control, escalation windows, and out-of-band communication templates.
- Provider behavior: allowed APIs, manual-only provider console actions, idempotency keys, provider failure handling, webhook reconciliation interactions, and reversal policy.
- Audit and storage: retention period, audit metadata, security event boundaries, redaction, access controls, and whether support tooling stores the source of truth instead of the product DB.

## API Contract

No v1 public HTTP endpoint accepts or returns a billing dispute / refund request.

Do not add:

- `POST /api/v1/billing/refund-requests`
- `POST /api/v1/billing/dispute-requests`
- `GET /api/v1/billing/adjustment-requests`
- request fields on `POST /api/v1/tenant/archive`
- admin mockup controls or memory-space controls for dispute / refund intake

Existing billing endpoints keep their current scope:

- `POST /api/v1/billing/checkout-sessions` returns a hosted checkout URL only.
- `POST /api/v1/billing/portal-sessions` returns a hosted portal URL only.
- `POST /api/v1/billing/webhooks/{provider}` syncs verified provider subscription state and does not process customer-submitted requests.
- `POST /api/v1/tenant/archive` archives locally and attempts immediate linked subscription cancellation only.

## Consequences

- There is no runtime code, database migration, OpenAPI endpoint, Feature test, or admin mockup change to add for this task.
- The product avoids collecting billing-sensitive claims before policy, staffing, retention, and provider action rules are defined.
- Future work starts with a product / finance / legal policy decision, then a separate implementation task for the chosen intake surface.

## Related References

- Billing provider integration: `docs/decisions/0029-billing-provider-integration.md`
- Tenant archive cancellation: `docs/decisions/0030-tenant-archive-billing-provider-cancellation.md`
- Archive cancellation retry command: `docs/decisions/0031-tenant-archive-billing-cancellation-retry-command.md`
- Automated billing adjustments: `docs/decisions/0032-automated-billing-adjustments-policy.md`
- Billing frontend redirect handoff: `docs/decisions/0034-billing-frontend-redirect-url-handoff.md`
