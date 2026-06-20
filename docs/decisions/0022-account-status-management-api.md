# 0022: Account Status Management API

Date: 2026-05-14

## Status

Accepted. Implemented 2026-05-15.

## Context

`users.account_status` already blocks login and Bearer token access for `disabled` and `suspended` users, but status changes still require direct database edits. The next account lifecycle step needs a tenant-scoped management API that can disable, suspend, and reactivate members without deleting user rows, tenant data, or memory ownership.

## Decision

- Add `PATCH /api/v1/tenant/members/{member}/account-status`.
- The endpoint is protected by Bearer token auth, tenant context, the existing `manage-tenant-members` guard, and the tenant security action rate limit.
- `{member}` uses `usr_01...` public id as the canonical lookup value. Positive numeric user ids remain accepted only during the v1 transition.
- Request payload is:
  - `account_status`: required, one of `active`, `disabled`, `suspended`.
  - `reason`: optional string, trimmed, max 500 characters, stored only in the security event metadata.
- `disabled` means a reversible tenant-manager administrative block. `suspended` means a reversible security / policy hold. Both block login and protected API access through the existing auth gate.
- The endpoint does not change `tenant_id`, `role`, memories, categories, pending email, email verification state, account password, or secret unlock password.
- Self-targeting is rejected with `422 Unprocessable Entity`.
- Admins cannot manage owner accounts. Owners can manage other owners.
- Disabling or suspending an owner is rejected if it would leave the tenant without another active owner.
- A successful status transition deletes all target user Bearer tokens. This applies to both deactivation and reactivation so old tokens cannot become usable again after a user is set back to `active`.
- A successful status transition also deletes existing secret unlock tokens, but it does not clear the dedicated secret unlock password hash.
- Reactivation does not issue a new Bearer token and does not resend email verification. The user must obtain a fresh token through normal login or password reset flow, and their existing email verification state is preserved.
- Every accepted request logs `auth.account_status.change` with manager, target, previous status, new status, roles, and optional reason. Boundary failures that happen after target lookup should log the same event type with `failure` and a machine-readable `metadata.reason`.

## Rationale

Deleting tokens on deactivation is expected, but deleting them on reactivation is the more important safety choice for this codebase. The current guard rejects inactive users without deleting stored tokens, so reactivating a user while preserving old tokens would let previously issued credentials work again. Status management should make the safer path the default: status changes are reversible, credentials are not silently revived.

Keeping this endpoint tenant-scoped also matches the current product model. A broader platform admin suspension system can be added later, but the current backend only has tenant managers, tenant roles, and one tenant per user.

## Next Task

Account deletion / export scope plus self-service account export and deletion are complete in decision 0023. Tenant-wide export and tenant archive policy is complete in decision 0024. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented.

Admin mockup connection was checked on 2026-05-15. The current static admin mockup has no tenant members view and no account status operation path; its documented smoke scope is memories, categories, tags, health, and Settings. Therefore no account status mockup wiring is required in the current backend automation. If a tenant members view is later added to the mockup, add a small follow-up task to connect `GET /api/v1/tenant/members` and `PATCH /api/v1/tenant/members/{member}/account-status` with existing Bearer token and 401 / 403 / 422 handling.
