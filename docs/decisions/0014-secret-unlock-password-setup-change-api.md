# 0014: Secret Unlock Password Setup / Change API

Date: 2026-05-14

## Status

Accepted.

## Context

Decision 0013 separated secret memory unlock from the account password by storing a dedicated hash in `users.secret_unlock_password`. Users still needed a protected API to set that dedicated password when missing and change it later.

## Decision

- Add `PUT /api/v1/secret-unlock-password` under Bearer token auth.
- Require `account_password` for both setup and change.
- Require `current_password` only when `users.secret_unlock_password` is already configured.
- Accept the new dedicated unlock password as `password` with `password_confirmation`.
- Reject a new unlock password that is the same plain value as `account_password`.
- On change, reject a new unlock password that is the same plain value as `current_password`.
- Store only the hashed value in `users.secret_unlock_password`.
- Delete existing `secret_unlock_tokens` after a successful setup or change so previously issued unlock tokens stop working.
- Keep recovery / forced rotation out of this endpoint. Those flows are covered by decision 0019 and use separate endpoints.

## Consequences

- Users without a dedicated unlock password can configure one without operator-side provisioning.
- Existing users can change the unlock password only when they know both the account password and the current unlock password.
- The account password itself still cannot be used to unlock secret memories.
- A forgotten unlock password is handled by the recovery contract in decision 0019, which requires Bearer token auth, account password proof, and verified email.

## Next Task

Secret unlock password recovery request / completion, tenant member forced rotation, the prefixed ULID public id response baseline, memories / categories public id resolver support, first-party frontend request migration, tenant member route public id lookup, tenant member invitation `inv_` public id lookup, account status management API, account deletion / export scope, the self-service account export endpoint, and the self-service account deletion endpoint are complete. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
