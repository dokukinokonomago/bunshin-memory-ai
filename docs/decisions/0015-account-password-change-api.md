# 0015: Account Password Change API

Date: 2026-05-14

## Status

Accepted.

## Context

Token-first auth already supports login, password reset, current session, token lifecycle, email verification, and dedicated secret unlock password setup / change. Authenticated users still needed a direct account password change API.

## Decision

- Add protected `PUT /api/v1/auth/password`.
- Require `current_password`, `password`, and `password_confirmation`.
- Verify `current_password` against `users.password`; invalid current password returns `422` on `current_password`.
- Reject a new password that is the same plain value as `current_password`.
- Store only the new password hash in `users.password`.
- Revoke all existing Bearer tokens for the user after a successful password change, including the token used for the request.
- Do not change `users.secret_unlock_password`; secret unlock remains a separate credential.
- Record `auth.password_change` security events for success and invalid current password failure.
- Apply a named protected route rate limiter keyed by authenticated user id.

## Consequences

- Clients must re-login with the new account password after a successful change.
- Password change cannot be used to rotate or recover the dedicated secret unlock password.
- Existing short-lived secret unlock tokens are not directly changed by this API, but they still require a valid Bearer token to be useful through protected endpoints.

## Next Task

Secret unlock password recovery request / completion, tenant member forced rotation, the prefixed ULID public id response baseline, memories / categories public id resolver support, first-party frontend request migration, tenant member route public id lookup, tenant member invitation `inv_` public id lookup, account status management API, account deletion / export scope, the self-service account export endpoint, and the self-service account deletion endpoint are complete. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
