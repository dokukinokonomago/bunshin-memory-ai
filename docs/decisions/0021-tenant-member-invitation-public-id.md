# 0021: Tenant Member Invitation Public IDs

Date: 2026-05-14

## Status

Implemented.

## Context

Core tenant, user, category, and memory resources already expose prefixed ULID public ids, and new client requests use public ids as the canonical lookup values. Tenant member route params were also moved to `usr_` public id lookup.

`tenant_member_invitations` remained the last tenant management resource without a public id. The protected management API lists invitations and then revokes a selected invitation with `DELETE /api/v1/tenant/invitations/{invitation}`, so the route parameter is a client-constructed resource lookup value. That makes it different from signed framework URLs and from opaque credential tokens.

## Decision

- Add a prefixed ULID public id to `tenant_member_invitations`. The prefix is `inv_`.
- `GET /api/v1/tenant/invitations` and `POST /api/v1/tenant/invitations` should expose `public_id`.
- `DELETE /api/v1/tenant/invitations/{invitation}` should use `inv_01...` as the canonical route parameter and accept positive numeric ids only as v1 transition compatibility.
- Missing, malformed, wrong-prefix, or outside-tenant invitation route parameters should return `404 Not Found`.
- Invitation relationship fields should gain public counterparts where useful: `invited_by_user_public_id` and `accepted_user_public_id`.
- The invitation accept token remains an opaque credential, not the management resource identifier. New tokens may use `inv_...|plainTextToken` as lookup metadata, but clients must treat the entire `invite_token` string as opaque and must not derive route ids from it.
- The accept endpoint should continue to accept legacy numeric `id|plainTextToken` tokens during the v1 transition, so existing pending invitations are not invalidated by the public id migration.
- Signed auth / recovery URLs remain outside this migration because they are server-generated, signed, short-lived flows.

## Rationale

Keeping invitation revocation as a permanent numeric exception would make the public id migration harder to explain: every other client-constructed tenant management lookup now has a public id. Adding `inv_` is small, aligns management UI state with the rest of the API, and avoids teaching new clients to persist internal invitation ids.

The accept token is intentionally a credential. Its prefix is only a server lookup hint, similar to the token storage patterns already used for Bearer and secret unlock tokens. The route id and the token string must stay conceptually separate.

## Implementation Notes

Implemented on 2026-05-14 with nullable unique `tenant_member_invitations.public_id`, migration backfill, `TenantMemberInvitation` model generation, invitation payload `public_id` / related user public ids, `DELETE /api/v1/tenant/invitations/{invitation}` `inv_` lookup with numeric compatibility, new `inv_...|plainTextToken` invite tokens, and legacy numeric token accept compatibility.

## Next Task

Account status management API is complete in decision 0022, account deletion / export scope plus self-service account export and deletion are complete in decision 0023, and tenant-wide export and tenant archive policy is complete in decision 0024. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
