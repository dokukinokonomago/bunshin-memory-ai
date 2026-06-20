# 0023: Account Deletion and Export Scope

Date: 2026-05-15

## Status

Accepted. Self-service export and deletion implemented.

## Context

The backend now has token-first auth, account password change, profile update, email change, account status management, tenant member revoke, secret unlock password management, and prefixed public ids. The next account lifecycle gap is privacy-oriented export and deletion.

This needs careful separation because the current product model is still 1 user to 1 tenant, while tenant member management already allows owners and admins to revoke or suspend other users. Revoking tenant access is not the same thing as deleting another person's account data.

## Decision

- Implement self-service account export before destructive deletion.
- The first export endpoint is `POST /api/v1/auth/account/export`, and it is implemented.
- Export requires Bearer token auth, tenant context, an active account, current account password, and an account lifecycle rate limit.
- Export returns a synchronous JSON bundle for the current user in the initial implementation. It includes profile, tenant summary, categories, tags, and non-deleted memories owned by the current user.
- `visibility=secret` memory bodies are not exported by Bearer token + account password alone. Export returns locked secret memory stubs unless the request explicitly sets `include_secret=true` and provides a valid current-user `X-Secret-Unlock` token.
- Export does not mutate tokens, account status, unlock password, memories, categories, or tags.
- Export logs `auth.account_export.request` as a security event. It must not store exported memory content, secret unlock token values, plain passwords, or the export bundle. Default export also omits secret-only tags from the top-level tag list; those tags are included only when secret memories are unlocked for the export.
- Self-service account deletion is implemented as `DELETE /api/v1/auth/account`.
- Account deletion requires Bearer token auth, tenant context, active account, current account password, an exact confirmation string, and the account lifecycle rate limit.
- Account deletion does not require secret unlock authorization because it must be able to erase secret memories without revealing them.
- Account deletion is rejected for the last active owner in a tenant. The user must transfer ownership, add another active owner, or use a future tenant deletion flow.
- Account deletion anonymizes the user row instead of physically deleting it. User fields include `deleted_at`, `anonymized_at`, and an anonymized email value that preserves unique constraints. The row remains available for historical foreign keys and audit retention.
- Account deletion sets the user to a non-authenticating state, clears tenant access, resets role to `member`, clears pending email, invalidates account credentials, deletes all Bearer tokens, and deletes all secret unlock tokens.
- Account deletion soft-deletes the current user's memories, including secret memories, and detaches `memory_tag` rows. It removes the user's categories and prunes tenant tags only when they are no longer attached to active memories.
- Account deletion revokes pending tenant invitations created by the deleted user. Accepted invitation history remains for audit context.
- Account deletion logs `auth.account.delete` without plain password, token, secret memory content, or old email in metadata.
- Existing `DELETE /api/v1/tenant/members/{member}` remains a tenant access revoke endpoint, not account deletion.
- Tenant owners and admins cannot delete another user's account data in the initial scope. They can revoke tenant access, change role, disable, suspend, or force secret unlock password rotation through existing endpoints.
- Tenant-wide export is implemented as `POST /api/v1/tenant/export` per decision 0024. It exports tenant operations metadata and aggregates, but it does not expose other users' private or secret memory bodies.

## Rationale

Export should come before deletion because it is non-destructive and gives users a recovery path before account closure. Keeping secret memory content behind the existing secret unlock boundary is consistent with the memory-space contract: ordinary API access should not accidentally disclose `visibility=secret` content in bulk.

Self-service deletion can erase secret memories without requiring secret unlock because deletion does not disclose the content. Requiring the unlock password for deletion would trap users who forgot it, even though recovery and forced rotation already treat the unlock password as a separate viewing credential rather than the authority to own or erase account data.

Tenant managers should not receive a broad "delete another account" capability yet. The current tenant management API is operational access control, not a legal/privacy data-subject workflow. Cross-user erasure needs a clearer policy for employment/team accounts, billing ownership, audit retention, and data ownership.

## Implementation Split

1. Implement `POST /api/v1/auth/account/export`. Completed 2026-05-15.
2. Implement `DELETE /api/v1/auth/account` with anonymization and data erasure. Completed 2026-05-15.
3. Revisit tenant-wide export and tenant deletion/archive after tenant settings and broader audit policy are designed. Completed in decision 0024 on 2026-05-15; tenant-wide export and tenant archive endpoints are implemented.

## Next Task

Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant purge command, scheduler registration, and production runbook are implemented in decision 0025. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
