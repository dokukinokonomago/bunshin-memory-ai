# 0018: Account Status Auth Rejection

Date: 2026-05-14

## Status

Accepted.

## Context

Token-first auth is implemented and account lifecycle now includes login, password reset, password change, profile update, email verification, and email change. The backend still needed a minimal way to stop a user from authenticating without deleting the user row or tenant data.

## Decision

- Add `users.account_status` with initial values `active`, `disabled`, and `suspended`.
- Default all new users to `active`, including signup, tenant invitation accept, local seed, and admin token command-created users.
- Allow only `active` users to receive login Bearer tokens.
- Reject `disabled` and `suspended` users at login with `403 Forbidden` and no token issuance.
- Record status-based login rejection as `auth.login` failure with `metadata.reason=account_not_active` and `metadata.account_status`.
- Reject existing Bearer tokens for `disabled` and `suspended` users in the `auth:sanctum` guard.
- Do not update `personal_access_tokens.last_used_at` when a token is rejected due to account status.
- Do not auto-delete existing tokens in the guard. A future status-management API can decide whether disabling should revoke tokens immediately or preserve them for possible reactivation.

## Consequences

- Existing data and tokens are not migrated destructively; existing users become `active`.
- Disabled / suspended users cannot use protected API routes even if they still possess an unexpired token.
- If a future admin flow reactivates a user without revoking tokens, unexpired tokens can become usable again; that policy must be decided when status-management endpoints are added.

## Next Task

Secret unlock password recovery request / completion, tenant member forced rotation, the prefixed ULID public id response baseline, memories / categories public id resolver support, first-party frontend request migration, tenant member route public id lookup, tenant member invitation `inv_` public id lookup, account status management API, account deletion / export scope, the self-service account export endpoint, and the self-service account deletion endpoint are complete. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
