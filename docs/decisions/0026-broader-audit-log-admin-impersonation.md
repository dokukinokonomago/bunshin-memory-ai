# 0026: Broader Audit Log And Admin Impersonation

Date: 2026-05-15

## Status

Accepted. Initial broader audit logging implementation completed on 2026-05-15.

## Context

The current backend already records auth and high-risk lifecycle events in `security_events`: login, signup, password reset, password change, email verification/change, secret unlock recovery/rotation, tenant invitation accept, account status change, account export/delete, tenant export/archive, and tenant purge.

The remaining SaaS gap is broader audit coverage for token lifecycle, tenant member management, and memory/category write operations. The product also needs an explicit position on admin impersonation before support/admin tooling grows.

## Decision

- Use the existing `security_events` table as the initial broader audit sink. Do not add a separate `audit_events` table yet.
- Treat `security_events` as append-only application/security audit records for v1, despite the table name. A later rename or separate analytics pipeline can be handled after admin search/export requirements are clearer.
- Broader audit logging will cover successful write operations for:
  - token lifecycle: logout, token revoke, revoke all, rotate;
  - tenant member management: invitation create/revoke, role update, member revoke;
  - account/profile/security settings: profile update, secret unlock password setup/change;
  - data writes: memory create/update/delete and category create/update/delete.
- Continue recording success/failure/requested outcomes for high-risk auth and lifecycle flows that already use `security_events`.
- Ordinary validation failures for memory/category/profile writes will not be logged. Authorization failures may be logged only when actor tenant/user context is known and the metadata can avoid leaking target resource details.
- New broader audit metadata must use public ids where possible. Do not store memory titles, memory bodies, category names, tag names, secret content, export bundles, plain passwords, plain Bearer tokens, invite tokens, reset tokens, unlock tokens, signed URL secrets, raw request payloads, or raw validation errors.
- Recommended safe metadata keys include `resource_type`, `resource_public_id`, `subject_user_public_id`, `target_role`, `previous_role`, `new_role`, `visibility`, `changed_fields`, `tag_count`, `category_public_id`, `reason`, and scrub-safe counts.
- `user_id` is the actor. Target users/resources belong in metadata by public id. `subject_email` is reserved for auth/account flows where email is already the subject identifier and should remain null for memory/category data writes.
- Every authenticated audit event must carry the actor tenant when available. Cross-tenant or out-of-context access must never log another tenant's resource ids, names, emails, or content.
- Initial retention target is 180 days for active tenants, matching `docs/decisions/0011-product-policy-decisions.md`. The pruning command and scheduler are implemented in decision 0027. External logging/search integration is designed in decision 0028 and remains deferred until a concrete longer-retention requirement exists.
- Tenant purge keeps the existing policy from `docs/decisions/0025-tenant-purge-retention-policy.md`: pre-existing tenant events are retained only as minimal scrubbed rows, while purge success/failure metadata is scrub-safe counts only.
- Admin impersonation is not part of the initial SaaS scope. Tenant owners/admins may manage members through explicit management endpoints, but they may not impersonate another user, mint a token for another user, bypass secret unlock, or read another user's private/secret memories as that user.
- Platform/support impersonation is also deferred. If added later, it requires a separate platform-admin identity model, explicit reason/ticket metadata, time-boxed sessions, least-privilege restrictions, no secret unlock bypass, user/tenant visibility where appropriate, a kill switch, and full start/end/action audit events.
- `php artisan bunshin:issue-admin-token` remains a local/operator token issuance tool for controlled verification. It is not a production impersonation mechanism.

## Consequences

- The initial implementation added event constants, controller logging, and focused tests without changing the schema.
- Keeping one audit table reduces migration work now, but the naming is broader than `security_events`. Documentation must consistently define the table as the v1 audit sink.
- Refusing impersonation preserves the current tenant/user boundary and avoids creating a support backdoor before platform admin, consent, and incident response policies exist.
- Product/support workflows that require viewing another user's content must use explicit export, archive, or future admin review flows rather than impersonation.

## Next Task

Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
