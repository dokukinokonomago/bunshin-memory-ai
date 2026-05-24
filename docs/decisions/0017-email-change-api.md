# 0017: Email Change API

Date: 2026-05-14

## Status

Accepted.

## Context

Profile update intentionally excludes email because email is the login identifier and needs uniqueness plus verification semantics. The backend already has token-first auth, signed email verification links, security events, and auth write rate limits.

## Decision

- Add protected `PUT /api/v1/auth/email` to request an email change.
- Normalize the requested email with trim + lowercase.
- Keep the current `users.email` unchanged until the new email is verified.
- Store the requested address in `users.pending_email` with `pending_email_requested_at`.
- Reject requested emails that match the current email or collide with another user's `email` or `pending_email`.
- Send an on-demand notification to the pending email with a signed `GET /api/v1/auth/email/change/verify/{id}/{hash}` link.
- On successful verification, move `pending_email` into `email`, clear pending fields, and set `email_verified_at` to the verification time.
- Re-check uniqueness at verification time to handle races or stale links.
- Require tenant context for the request and for verification completion.
- Record `auth.email_change.request` and `auth.email_change.complete` security events.
- Rate limit the request endpoint with `bunshin-auth-email-change`.
- Do not revoke Bearer tokens for this baseline; account suspension / broader dangerous-action policy is a later task.

## Consequences

- Clients can show pending email state from the `AuthUser` payload.
- Login email remains stable until the new address has proven ownership.
- A stale or superseded email change link fails because its hash no longer matches the current `pending_email`.
- Future UX can add cancel/resend endpoints without changing the core pending-email model.

## Next Task

Secret unlock password recovery request / completion, tenant member forced rotation, the prefixed ULID public id response baseline, memories / categories public id resolver support, first-party frontend request migration, tenant member route public id lookup, tenant member invitation `inv_` public id lookup, account status management API, account deletion / export scope, the self-service account export endpoint, and the self-service account deletion endpoint are complete. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
