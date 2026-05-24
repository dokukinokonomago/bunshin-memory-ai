# Tenant Archive Billing Cancellation Failure Runbook

## Purpose

`POST /api/v1/tenant/archive` archives the tenant locally first, then attempts to cancel a linked provider subscription as a side effect. Local archive remains authoritative even when provider cancellation cannot be completed.

This runbook covers the operator workflow when the archive response or `billing.subscription_cancel.request` event reports `requires_operator_review`.

## When To Use

Use this runbook when `data.billing_provider_cancellation.status` is `requires_operator_review`.

Current operator-review reasons are:

- `provider_configuration_incomplete`: billing is enabled, but the configured provider adapter is missing required local configuration.
- `provider_request_failed`: the provider cancellation request failed or returned an invalid response.

Do not use this runbook for `skipped` outcomes. Skipped outcomes are safe no-ops, for example billing disabled, missing local subscription link, provider mismatch, or unsupported provider. They can be reviewed during normal billing reconciliation, but they do not indicate a failed linked cancellation attempt.

## Local State Rules

- Do not roll back the tenant archive.
- Do not reactivate the tenant.
- Do not create a new provider subscription.
- Do not infer a provider subscription during triage.
- Do not expose refund, credit, proration, invoice-finalization, period-end cancellation, or cancellation-date options from the tenant archive flow.
- Do not manually grant paid entitlements to an archived tenant.

The archived tenant should stay inactive even if a later provider webhook or provider-local reconciliation sees an active provider subscription.

## Safe References

Use these in tickets, incident notes, and chat:

- tenant public id
- tenant slug when needed
- local archive timestamp
- `billing.subscription_cancel.request` event type
- event outcome
- `result`
- `reason`
- provider key, for example `stripe`
- archive cancellation policy, `immediate_no_proration_no_refund`
- previous local plan/status
- changed local field names
- timestamps and aggregate counts

Do not paste or store:

- provider API keys or webhook signing secrets
- provider customer ids, subscription ids, price ids, invoice ids, refund ids, checkout session ids, portal session ids, or provider object URLs
- raw provider responses, raw provider error bodies, stack traces containing provider payloads, or webhook payloads
- card data, billing address, tax id, raw customer email, or billing contact PII
- memory titles, memory bodies, category names, tag names, or secret content
- account passwords, secret unlock passwords, invite tokens, reset tokens, Bearer tokens, signed URL secrets

Provider identifiers may be viewed only inside approved production consoles or privileged database access paths for the duration of triage. They must not be copied into durable notes or shared channels.

## Triage Workflow

1. Confirm the tenant archive completed locally.
2. Confirm the response or latest `billing.subscription_cancel.request` event has `result=requires_operator_review`.
3. Record only safe references in the operator ticket.
4. Identify the reason code.
5. Follow the reason-specific section below.
6. If manual provider action is taken, record the safe outcome in the ticket without provider identifiers.
7. Verify the tenant remains archived locally.
8. Close the ticket only after provider subscription state is no longer active or the remaining action is assigned to finance / legal / support.

## Provider Configuration Incomplete

Reason code: `provider_configuration_incomplete`.

Expected causes:

- `BUNSHIN_BILLING_ENABLED=true` but `BUNSHIN_BILLING_PROVIDER` is not usable.
- Stripe-compatible adapter is selected but `BUNSHIN_STRIPE_SECRET_KEY` is missing.
- Stripe-compatible adapter is selected but `BUNSHIN_STRIPE_API_BASE_URL` is missing or malformed.

Steps:

1. Confirm the production environment variable source.
2. Fix missing or malformed configuration through the normal secret/config deployment path.
3. Do not paste secret values into the ticket.
4. After config is corrected, use the provider console to locate the linked subscription through approved access.
5. Cancel the linked subscription manually if it is still active.
6. Record safe completion text such as `provider subscription manually canceled after config fix`.

Do not re-run tenant archive to retry. The tenant is already archived, and the endpoint is not a retry API.

## Provider Request Failed

Reason code: `provider_request_failed`.

Expected causes:

- provider API outage or timeout
- provider auth failure
- provider rate limit
- provider subscription already changed in the provider
- provider returned an unexpected response

Steps:

1. Check provider status and application logs without copying raw response bodies.
2. Confirm whether the provider subscription is already canceled in the provider console.
3. If it is already canceled, record a safe note such as `provider subscription already canceled`.
4. If it is active and cancellation is allowed, cancel it manually in the provider console using immediate cancellation with no proration and no refund.
5. If the provider console requires refund, credit, proration, invoice finalization, dispute, or dunning decisions, follow decision 0032: stop and escalate to the product / finance / legal owner. Do not decide these inside engineering triage.
6. Record safe completion text, reason code, and timestamp.

Manual provider cancellation should follow decision 0030 and decision 0032: immediate cancellation, no automatic refund, no automatic credit, no proration, and no invoice-finalization action unless a separate product / finance / legal policy approves it.

## Retry Guidance

There is no dedicated retry command in v1. Decision 0031 defers it until repeated failures, batch retry needs, safer support tooling, compliance requirements, or provider adapter maturity make a command clearly safer than manual triage.

Allowed:

- provider console cancellation for the already-linked subscription
- waiting for provider recovery before manual console cancellation
- opening a follow-up task to design a dedicated retry command if one of the decision 0031 triggers becomes concrete

Not allowed:

- reactivating the tenant to retry archive
- editing local tenant subscription state to active
- creating or guessing provider subscriptions
- replaying unrelated checkout or subscription webhooks to force local state on an archived tenant
- adding provider ids or raw provider errors to `security_events`, tickets, or chat

If a retry command is later approved, it must target archived tenants by tenant public id or slug, read only the existing local subscription link, keep local archive state unchanged, default to dry-run unless explicitly applied, and write only scrub-safe `billing.subscription_cancel.request` metadata.

## Verification

Use aggregate or public-id based checks.

Confirm the local archive state:

```sql
select public_id, slug, archived_at, subscription_status, subscription_ends_at
from tenants
where public_id = 'ten_01EXAMPLE';
```

Confirm the scrub-safe cancellation event exists:

```sql
select event_type, outcome, json_extract(metadata, '$.result') as result, json_extract(metadata, '$.reason') as reason, created_at
from security_events
where event_type = 'billing.subscription_cancel.request'
order by created_at desc
limit 10;
```

Do not select provider id columns into exported output for routine verification. If privileged lookup of provider ids is needed, keep it inside the approved console/session and do not paste values elsewhere.

## Escalation

Escalate when:

- provider cancellation cannot be completed manually
- provider account access or credentials appear compromised
- provider state conflicts with local archived state in a way that affects billing
- refund, credit, proration, invoice, dunning, or dispute decisions are required
- repeated failures suggest a need for a retry command or provider adapter change

Escalation notes must use tenant public id, safe result codes, timestamps, and high-level provider status only.

## Related References

- Archive billing decision: `docs/decisions/0030-tenant-archive-billing-provider-cancellation.md`
- Retry command decision: `docs/decisions/0031-tenant-archive-billing-cancellation-retry-command.md`
- Billing provider decision: `docs/decisions/0029-billing-provider-integration.md`
- Tenant archive policy: `docs/decisions/0024-tenant-export-deletion-archive.md`
- Billing adjustment policy: `docs/decisions/0032-automated-billing-adjustments-policy.md`
- Billing reconciliation runbook: `docs/operations/billing_provider_reconciliation_runbook.md`
- Provider client: `app/Support/Billing/StripeBillingClient.php`
- Archive controller: `app/Http/Controllers/Api/V1/TenantLifecycleController.php`
