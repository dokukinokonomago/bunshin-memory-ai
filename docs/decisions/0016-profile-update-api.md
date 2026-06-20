# 0016: Profile Update API

Date: 2026-05-14

## Status

Accepted.

## Context

Token-first auth already supports signup, login, current session, token lifecycle, password reset, account password change, and email verification. Authenticated users still needed a minimal way to update their own display profile without involving password or email verification flows.

## Decision

- Add protected `PATCH /api/v1/auth/profile`.
- Initial contract updates only `users.name`.
- Trim `name` before validation and storage. Empty names are rejected with `422`.
- Reject `email` in this payload with validation error. Email change remains a separate task because it needs uniqueness, normalization, and verification semantics.
- Require a valid Bearer token and tenant context.
- Return the updated `AuthUser` payload.
- Do not revoke Bearer tokens after profile update.
- Do not write auth security events for this baseline; broader audit logging is a later task.

## Consequences

- Clients can update the visible account name without forcing re-login.
- Email remains stable until a dedicated email change API is designed.
- Future profile fields can be added to this endpoint as explicit allowed fields.

## Next Task

Secret unlock password recovery request / completion, tenant member forced rotation, the prefixed ULID public id response baseline, memories / categories public id resolver support, first-party frontend request migration, tenant member route public id lookup, tenant member invitation `inv_` public id lookup, account status management API, account deletion / export scope, the self-service account export endpoint, and the self-service account deletion endpoint are complete. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
