# 0028: External Logging And Search Integration

Date: 2026-05-16

## Status

Accepted as a design baseline. Initial implementation is deferred.

## Context

`security_events` is the v1 application/security audit sink. It now records auth, tenant lifecycle, token lifecycle, tenant member management, profile / secret unlock password changes, memory writes, and category writes. Decision 0027 also makes primary database retention enforceable: null-tenant and non-purged tenant rows are pruned by `created_at`, while purged-tenant rows are pruned after `tenants.purged_at` is older than the retention cutoff.

The remaining question is whether the product should keep a longer searchable audit trail outside the primary database for support investigation, operational analytics, compliance archive, or incident response.

## Decision

- Do not implement an external logging/search pipeline in the initial backend baseline.
- Treat the current 180-day primary database retention as the active product behavior until a concrete longer-retention requirement exists.
- If longer audit search, analytics, compliance archive, or support investigation becomes a product requirement, add a separate provider-neutral external audit projection before relying on the prune job as the only copy of those events.
- The external pipeline must be separate from public HTTP APIs. It is an internal operations/analytics integration, not a tenant-facing endpoint.
- The primary `security_events` table remains the source of truth for application behavior during its retention window. External search must not be used for authorization decisions, secret unlock decisions, tenant boundary decisions, or user-visible data recovery.
- Do not send raw `security_events` rows directly to an external sink. Export a sanitized projection only.
- Do not choose a vendor in the design baseline. OpenSearch, Datadog, CloudWatch, BigQuery, or another sink can be selected later based on deployment environment and retention/cost requirements.

## Accepted Purposes

An external integration is allowed only for these purposes:

- support investigation using scrub-safe audit facts;
- operational analytics over event volume and failure trends;
- incident response over security event categories;
- compliance archive if a future policy explicitly requires retention beyond the primary database window.

It is not for memory content search, secret memory review, user impersonation, account recovery bypass, or broad tenant data export.

## Projection Contract

Future implementation should emit a sanitized audit event projection with only these field classes:

- event timestamp and event type;
- outcome: `success`, `failure`, or `requested`;
- tenant public id when known;
- actor user public id when known;
- subject user public id when already present in scrub-safe metadata;
- resource type and resource public id when applicable;
- scrub-safe flags and enums such as `visibility`, `reason`, `role`, `previous_role`, `new_role`, `account_status`, `plan_key`, and changed field names;
- scrub-safe aggregate counts such as token count, tag count, deleted row counts, or affected memory count;
- pipeline metadata such as sink name, schema version, delivery attempt count, and delivery timestamp.

The projection must not include:

- memory titles, memory bodies, category names, tag names, secret content, beliefs/chains text, export bundles, or raw memory metadata;
- plain passwords, Bearer tokens, token hashes, invite tokens, reset tokens, unlock tokens, signed URL secrets, session ids, or recovery URLs;
- raw request payloads, raw validation errors, raw audit metadata, stack traces with payloads, or query strings containing credentials;
- subject email, invited email, IP address, or user agent by default.

If fraud or incident response later needs IP address or user agent search, that requires a separate decision with explicit minimization such as hashing, truncation, shorter retention, access controls, and region/privacy review.

## Delivery Shape

- Synchronous HTTP delivery from controllers is rejected. Audit writes must not make user-facing API requests slower or less reliable.
- If implementation needs at-least-once delivery, add an internal outbox table or queue-backed job that stores only the sanitized projection.
- If best-effort delivery is acceptable, queued jobs may build the sanitized projection from recent `security_events` rows and send it to the configured sink.
- Delivery failures should be reported through operational logs/alerts and retried with bounded backoff. They should not create more `security_events` rows for every retry.
- Duplicate delivery must be tolerated by the sink. The projection should include a stable provider-neutral event key generated for the projection; do not expose database integer ids as external ids.

## Retention And Deletion

- Primary database pruning remains governed by decision 0027 and does not change for the deferred external integration.
- External retention must be explicitly configured per sink before implementation. No indefinite retention is allowed by default.
- A support-search sink should default to a limited retention window, for example 180 to 365 days, unless a future decision requires otherwise.
- A compliance archive requires a separate legal/product decision, including legal hold handling, tenant purge interaction, regional storage, access review, and export/delete procedures.
- Tenant purge must continue to scrub primary database rows as decision 0025 requires. External sinks must receive only sanitized projections and must have a documented deletion or retention-expiry process for tenant-bound records.

## Access Controls

- Access to external audit search is operations/support tooling, not application user access.
- Search access must be least-privilege and logged by the provider or surrounding operations process.
- Support search must not expose memory content, secret content, credentials, raw PII, or another tenant's private identifiers.
- Admin impersonation remains excluded as decision 0026 states. External search cannot be used to reconstruct or bypass impersonation.

## Consequences

- There is no new code, schema, provider dependency, queue, or public API for this decision.
- The product keeps a clear boundary: primary DB audit retention is short and enforceable, while longer audit search is an explicit future pipeline.
- The backend can keep pruning `security_events` without accidentally promising indefinite audit search.
- Future implementation can be split into small tasks: projection schema, outbox/queue delivery, sink configuration, runbook, and tests.

## Next Task

Implement billing webhook receiver with signature verification / idempotency tests.
