# 0031: Tenant Archive Billing Cancellation Retry Command

Date: 2026-05-16

## Status

Accepted. Dedicated retry command is deferred.

## Context

Decision 0030 made tenant archive an archive-first flow. After local archive commits, the backend attempts immediate provider subscription cancellation for the already-linked local subscription id. Provider cancellation success, safe skipped states, and `requires_operator_review` failures are recorded as scrub-safe `billing.subscription_cancel.request` events.

The operations runbook now gives operators a manual provider-console workflow for `provider_configuration_incomplete` and `provider_request_failed`. The open question was whether this needs a dedicated `artisan` retry command now.

## Decision

Do not implement a dedicated tenant archive billing cancellation retry command in v1.

Current handling is sufficient because:

- The failure path is rare and operational, not a normal user-facing workflow.
- The local tenant archive already remains authoritative; retry must not change local access, token, member, memory, or subscription entitlement state.
- The only safe automated provider action is the same immediate cancellation attempted during archive. If that request failed because of provider outage, auth/config, rate limits, unexpected provider state, or finance-sensitive provider prompts, human triage is still required.
- The current local schema does not store a retry queue, failure attempt counter, next retry time, or operator assignment. Adding a command without those controls would create an implicit operations queue in audit events.
- A command would need privileged access to provider-linked subscription ids. Keeping this workflow inside approved provider console access avoids exposing provider ids in command output, logs, tickets, chat, or copied runbook notes.
- Refunds, credits, proration, invoice finalization, dunning, disputes, and period-end cancellation remain outside v1 and are deferred by decision 0032 until separate product / finance / legal policy exists. Customer-visible dispute / refund request intake is also deferred by decision 0033 and must not be bolted onto a retry workflow.

## Required Current Workflow

- Use `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md` for `requires_operator_review`.
- Do not re-run `POST /api/v1/tenant/archive`.
- Do not reactivate the tenant to retry.
- Do not edit local subscription state back to active.
- Do not replay unrelated billing webhooks to force state.
- Use approved provider console access to inspect and cancel the already-linked provider subscription when safe.
- Record only safe references and outcomes in tickets and chat.

## Future Trigger For A Command

Design a dedicated retry command only if at least one of these becomes concrete:

- repeated provider cancellation failures make manual console triage unreliable or too slow
- operations needs controlled batch retry after a known provider outage
- support needs a safer internal tool than direct provider console access
- compliance requires structured retry attempts, attempt counts, or review assignment
- provider adapter behavior becomes stable enough to make automated retry materially safer than manual triage

## Future Command Constraints

If a future command is approved, it must be a separate implementation task with these constraints:

- Internal `artisan` command only; no public API endpoint.
- Default dry-run; mutation requires an explicit `--apply`.
- Target a single archived tenant by tenant public id or slug unless a separate batch policy exists.
- Require `archived_at` and a non-purged tenant.
- Use only the existing local `billing_provider` and `billing_subscription_id`; do not search, infer, create, or guess provider subscriptions.
- Require configured provider to match the tenant provider.
- Keep local archive state unchanged.
- Do not grant paid entitlements or reactivate local subscription state.
- Use the same immediate no-proration, no-refund cancellation policy as decision 0030.
- Print and log only scrub-safe provider key, result, reason, tenant public id/slug, and changed local field names.
- Write only scrub-safe `billing.subscription_cancel.request` metadata.
- Never print or persist provider customer id, subscription id, price id, invoice id, refund id, hosted URL, raw provider response, raw provider error body, provider secret, raw customer email, card data, billing address, tax id, memory content, password, or token material.

## Current Boundary

Automated refund, credit, proration, invoice finalization, dunning, dispute, and period-end cancellation policy is documented in decision 0032. Customer-visible dispute / refund request intake is documented in decision 0033. Do not implement those flows until product / finance / legal policy exists and a separate implementation task is approved.
