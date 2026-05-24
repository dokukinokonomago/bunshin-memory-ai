# 0008. Tenant member management uses hashed invitation tokens

Date: 2026-05-13

## Status

Accepted

## Context

The initial SaaS baseline keeps one user in one tenant through `users.tenant_id` and stores tenant role on `users.role`. The next backend step needs a minimal API for inviting members, accepting invitations, revoking access, and updating roles without introducing a membership table yet.

## Decision

- Add `tenant_member_invitations` as the invitation storage table.
- Store invitation tokens as sha256 hashes only. The API returns the plain `id|token` value once when an owner / admin creates an invitation.
- Decision 0021 adds `inv_` public ids for tenant member invitation management routes. The invitation accept token remains an opaque credential and must not be treated as the management resource id.
- Set the initial invitation TTL to 7 days.
- `POST /api/v1/tenant/invitations/accept` is public and creates a new user with the invitation email, tenant, and invited role.
- Existing user email acceptance is not supported in this baseline. Existing user linking is deferred until the product needs multi-tenant membership or account merge flows.
- Owners and admins can list members and invitations, invite members, revoke pending invitations, update member roles, and revoke tenant access.
- Decision 0022 adds tenant-scoped account status management through implemented `PATCH /api/v1/tenant/members/{member}/account-status` using the same manager and role boundaries.
- Admins cannot assign the `owner` role or manage existing owners. Owners can assign `owner` and manage other owners.
- A manager cannot change or revoke their own tenant membership.
- Revoking a member clears `users.tenant_id`, resets `users.role` to `member`, and deletes that user's Bearer tokens. The user row is not deleted.

## Consequences

- The project still avoids a membership table and keeps the current one-user-one-tenant assumption.
- Invitation delivery is now implemented with a mail notification sent to the invitee email when an invitation is created. The API still returns the plain invitation token once for local testing / operator fallback; only the token hash is stored server-side.
- Invitation public ids are a follow-up migration over this baseline. Numeric invitation route ids stay only as v1 transition compatibility after `inv_` lookup is implemented.
- A revoked user's owned memory rows are not deleted. Because their API tokens are revoked and `tenant_id` is cleared, they cannot access tenant APIs until a future account recovery or re-invitation policy exists.
- Existing-user invitation and multi-tenant membership remain future tasks.
