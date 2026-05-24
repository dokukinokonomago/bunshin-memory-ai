# 0013: Secret Unlock Password Separation

Date: 2026-05-13

## Status

Accepted.

## Context

Memory-space secret memories require an additional backend authorization step before `visibility=secret` content can be returned. The initial baseline reused the authenticated user's account password for `POST /api/v1/secret-unlocks`, but product policy decision 0011 requires a dedicated unlock credential.

## Decision

- Store the dedicated unlock credential as `users.secret_unlock_password`.
- Store only a password hash. Plain unlock passwords must not be persisted or returned.
- Keep `POST /api/v1/secret-unlocks` request shape as `{ "password": "..." }`, but validate it against `users.secret_unlock_password` instead of `users.password`.
- If `secret_unlock_password` is not configured, return `422 Unprocessable Entity` with a `password` validation error and do not issue a token.
- Account password alone must not issue a secret unlock token.
- Existing `secret_unlock_tokens` behavior remains unchanged: generated tokens are short-lived, user scoped, stored as sha256 hashes, and returned as plain text only once.

## Consequences

- Secret memory unlock is no longer coupled to account password login.
- Existing users without a dedicated unlock password can configure one through `PUT /api/v1/secret-unlock-password` after proving the account password.
- Local development seed users may set a known unlock password for smoke testing; this is not a production default.
- `PUT /api/v1/secret-unlock-password` now provides the minimal setup / change API for `secret_unlock_password`.
- Recovery / forced rotation contract is covered by decision 0019.

## Next Task

Secret unlock password recovery request / completion, tenant member forced rotation, the prefixed ULID public id response baseline, memories / categories public id resolver support, first-party frontend request migration, tenant member route public id lookup, tenant member invitation `inv_` public id lookup, account status management API, account deletion / export scope, the self-service account export endpoint, and the self-service account deletion endpoint are complete. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
