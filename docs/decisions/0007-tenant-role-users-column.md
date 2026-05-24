# 0007. Tenant role baseline uses users.role

Date: 2026-05-12

## Status

Accepted

## Context

The backend currently uses `users.tenant_id` and keeps the MVP assumption that one user belongs to one tenant. SaaS readiness needs owner / admin / member semantics before member invitation and billing gates are added.

## Decision

- Keep the initial role baseline on `users.role`.
- Supported role values are `owner`, `admin`, and `member`.
- The database default role is `member`.
- Invite-only signup creates the initial tenant user as `owner`.
- Local seed and `bunshin:issue-admin-token` default to `owner` so local API smoke tests keep full tenant management permissions.
- Define `manage-tenant-members` as the first role guard. It allows `owner` and `admin` only inside their own tenant.
- Do not introduce a membership table yet. Add it only when one user must belong to multiple tenants.

## Consequences

- Auth responses expose `data.user.role`.
- Existing memories / categories remain owner-scoped by `tenant_id` and `owner_user_id`; role does not widen access to another user's memory data.
- The next backend task can add tenant member invite / accept / revoke / role update APIs on top of this role baseline.
