# 0024: Tenant Export and Tenant Archive Policy

Date: 2026-05-15

## Status

Accepted. Tenant-wide export, tenant archive lifecycle fields, archived-tenant auth rejection, tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Tenant purge retention policy is decided in decision 0025.

## Context

Self-service account export and account deletion are implemented in decision 0023. The remaining lifecycle gap is tenant-level export and tenant closure.

Tenant-level actions have a different risk profile from self-service account actions. A tenant owner may need operational metadata, member history, invitation history, quota state, and security summaries, but that does not automatically grant the right to bulk-read every member's private or secret memory content.

## Decision

- Add an implemented tenant-wide export endpoint: `POST /api/v1/tenant/export`.
- Tenant-wide export is owner-only in the initial contract. Admins can manage members, but tenant lifecycle export is reserved for `role=owner`.
- Export requires Bearer token auth, tenant context, active account, current account password, and a tenant lifecycle rate limit.
- Export is synchronous JSON in the initial contract.
- Export includes tenant metadata, member roster, invitation history, plan / subscription state, quota counts, memory inventory aggregates, and security event summary.
- Export does not include memory `title`, `body`, `metadata`, tags, category names attached to another user's memories, secret unlock token values, Bearer token values, password hashes, raw security event metadata, IP addresses, or user agents.
- Export does not include other users' private or secret memory bodies. `visibility=secret` content remains behind each user's own secret unlock boundary and is not exposed through a tenant owner export.
- The current owner's own memory content is also excluded from tenant-wide export to keep the endpoint's semantics consistent. Owners should use `POST /api/v1/auth/account/export` for their own content export.
- Export logs `auth.tenant_export.request`. The security event must not store export bundles, memory content, plain credentials, token values, raw audit metadata, or secret content.
- Add an implemented tenant archive endpoint: `POST /api/v1/tenant/archive`.
- Tenant archive is owner-only and requires Bearer token auth, tenant context, active account, current account password, exact confirmation, and the tenant lifecycle rate limit.
- The confirmation string is `ARCHIVE <tenant_slug>` so the user must name the tenant being closed.
- Tenant archive is the last-owner exit path. Unlike self-service account deletion, it may be requested by the last active owner because it closes the tenant itself.
- Initial tenant closure is archive-first, not hard delete. The archive operation freezes the tenant, revokes all tenant users' Bearer tokens and secret unlock tokens, revokes pending invitations, and prevents further login / protected API access for the archived tenant.
- Archive does not immediately soft-delete memories, categories, tags, users, invitations, or security events. It preserves data for a reversible retention window and for audit / billing context.
- Tenant lifecycle fields are implemented as `tenants.archived_at`, `tenants.archived_by_user_id`, `tenants.archive_reason`, `tenants.deletion_requested_at`, `tenants.scheduled_deletion_at`, and `tenants.purged_at`.
- Login for an archived tenant is rejected before issuing a new Bearer token. Existing Bearer tokens for users in an archived tenant are rejected by the token guard and do not update `personal_access_tokens.last_used_at`.
- Initial archive sets `archived_at`, `archived_by_user_id`, optional `archive_reason`, `deletion_requested_at`, and `scheduled_deletion_at`. The default scheduled deletion window is 30 days unless a later product/legal decision changes it.
- Billing provider integration scope and webhook handling are implemented in decision 0029. Archive provider cancellation handling is implemented from decision 0030: archive remains local-first, provider cancellation is a side effect after local archive, provider failure does not roll back archive, and v1 does not automate refunds, credits, proration, invoice finalization, or period-end cancellation.
- Permanent tenant purge is not implemented in the archive endpoint. Decision 0025 defines the purge retention policy and command: after the retention window, an internal scheduled job removes memory content and tenant-owned operational data while preserving only a tenant tombstone and scrubbed security / billing context.
- Existing `DELETE /api/v1/tenant/members/{member}` remains tenant access revoke, not account deletion or tenant deletion.

## Rationale

Tenant-wide export is useful for operations and compliance, but it should not become an accidental cross-user data disclosure endpoint. Keeping memory content out of tenant export avoids weakening the `visibility=secret` and owner-scoped memory contracts.

Archive-first tenant closure is safer than immediate hard deletion because current billing, audit retention, and support recovery policies are still minimal. It gives the product a clear tenant exit flow without forcing irreversible purge semantics before those policies are complete.

## Implementation Split

1. Completed: implement `POST /api/v1/tenant/export` with owner-only authorization, current password validation, tenant lifecycle rate limit, export bundle generation, and `auth.tenant_export.request` logging.
2. Completed: add tenant archive lifecycle fields and archived-tenant auth rejection.
3. Completed: implement `POST /api/v1/tenant/archive` with owner-only authorization, exact confirmation, token revocation, invitation revocation, local subscription closure, and `auth.tenant.archive` logging.
4. Completed: design tenant purge retention policy in decision 0025.
5. Completed: implement tenant purge job, tests, scheduler registration, failure alert hook, and production runbook.

## Next Task

Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
