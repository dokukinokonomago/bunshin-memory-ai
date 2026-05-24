# 0019: Secret Unlock Password Recovery / Forced Rotation

Date: 2026-05-14

## Status

Accepted. Implemented for recovery request / completion and manager forced rotation on 2026-05-14.

## Context

Decision 0013 separated secret memory unlock from the account password, and decision 0014 added setup / change for the dedicated unlock password. A user who forgets the dedicated unlock password still needs a recovery path, and tenant managers need a way to force a reset without learning or replacing the user's secret unlock password.

## Decision

- Keep `PUT /api/v1/secret-unlock-password` limited to setup / normal change. Recovery and manager-forced reset use separate endpoints.
- Add a self-service recovery request endpoint: `POST /api/v1/secret-unlock-password/recovery/request`.
- The recovery request requires Bearer token auth, tenant context, an active account, a verified email address, and the current account password.
- A successful request sends a short-lived signed recovery link to the user's current verified email and returns `202 Accepted`. It does not change `users.secret_unlock_password` and does not revoke existing `secret_unlock_tokens`.
- Add a self-service recovery completion endpoint: `PUT /api/v1/secret-unlock-password/recovery/{id}/{hash}`.
- Recovery completion requires Bearer token auth for the same user, a valid signed URL, the current account password, and a new unlock password with confirmation.
- Recovery completion does not require `current_password`. It rejects the account password as the new unlock password and rejects the existing unlock password value when one is configured.
- Recovery completion updates only `users.secret_unlock_password` and deletes the user's existing `secret_unlock_tokens`. It does not revoke Bearer tokens.
- Add a manager forced rotation endpoint: `POST /api/v1/tenant/members/{member}/secret-unlock-password/force-rotation`.
- Forced rotation is authorized by the tenant member management policy, follows the same owner / admin role boundaries as member management, and cannot target the acting user.
- Forced rotation clears the target user's `secret_unlock_password` and deletes the target user's existing `secret_unlock_tokens`. It does not set a temporary unlock password, return secret data, or revoke Bearer tokens.
- After forced rotation, the target user must use the existing setup flow (`PUT /api/v1/secret-unlock-password` with `account_password`) to configure a new dedicated unlock password.
- Record recovery request / complete and forced rotation in `security_events`. Plain passwords, signed URL secrets, and unlock tokens are never stored in event metadata.
- Use named rate limits for recovery request and completion. Forced rotation uses tenant security action throttling when implemented.

## Consequences

- Account password alone still cannot immediately unlock secret memories.
- Recovery requires possession of the active Bearer token, the account password, and the verified email channel.
- Managers can break access to old unlock tokens and require a new setup, but they cannot impersonate the secret unlock credential.
- Existing `secret_unlock_tokens` remain short-lived and are invalidated by setup, change, recovery completion, and forced rotation.
- Users with unverified email must verify email before self-service recovery. If they cannot, an authorized tenant manager must force rotation or account support must handle the case outside the initial API.

## Next Task

Tenant member secret unlock password forced rotation, the prefixed ULID public id response baseline, memories / categories public id resolver support, first-party frontend request migration, tenant member route public id lookup, tenant member invitation `inv_` public id lookup, account status management API, account deletion / export scope, the self-service account export endpoint, and the self-service account deletion endpoint are complete. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
