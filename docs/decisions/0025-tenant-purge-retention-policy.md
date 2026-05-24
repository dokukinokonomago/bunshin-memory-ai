# 0025: Tenant Purge Retention Policy

Date: 2026-05-15

## Status

Accepted. Implemented as `php artisan bunshin:purge-archived-tenants` on 2026-05-15. Scheduler registration and the production runbook were added on 2026-05-15.

## Context

Decision 0024 implemented archive-first tenant closure. Archive freezes a tenant, revokes tenant credentials, revokes pending invitations, closes local subscription state, and sets `scheduled_deletion_at` 30 days after archive. It intentionally does not hard-delete tenant data.

The remaining lifecycle gap is the irreversible purge after the retention window. The purge must remove memory content, secret content, credentials, invitations, and tenant-owned operational data while preserving only the minimal audit / billing context needed to explain that the tenant was closed.

## Decision

- Tenant purge is an internal operational job, not a public API endpoint.
- Initial implementation exposes a console command named `bunshin:purge-archived-tenants`. It is registered in Laravel Scheduler for daily execution, with production enablement controlled by `BUNSHIN_TENANT_PURGE_SCHEDULE_ENABLED`. The command supports `--dry-run`, `--limit`, and optional single-tenant targeting by tenant public id or slug for controlled operations.
- A tenant is purge-eligible only when `archived_at` is not null, `scheduled_deletion_at <= now()`, and `purged_at` is null.
- The default retention window remains 30 days from archive. The source of truth is the persisted `scheduled_deletion_at`, not recalculating from `archived_at`.
- Purge keeps a tenant tombstone row instead of deleting the tenant row. The tombstone preserves `public_id`, lifecycle timestamps, local plan / subscription state, and `purged_at`, while scrubbing direct identifiers such as `name`, `slug`, and `archive_reason`.
- Purge is irreversible. There is no restore path after `purged_at` is set.
- The job must be idempotent. It must lock each candidate tenant, re-check eligibility, delete or scrub child data, and set `purged_at` only after successful cleanup. Re-running after a partial failure should converge without exposing previously deleted data.
- The job should process tenants in small batches. Initial default limit should be conservative, for example 50 tenants per run.
- Dry run must report eligible tenants and estimated deletion / scrub counts without mutating rows.
- Failure for one tenant must not stop later tenants in the same batch. Failed tenants keep `purged_at = null` so the next run can retry.
- Scheduled runs capture output to `storage/logs/tenant-purge-schedule.log` by default. If `BUNSHIN_OPERATIONS_ALERT_EMAIL` is configured, Laravel emails command output on scheduled failure.
- Billing provider integration is still out of scope. Purge does not call a provider. Future billing sync must run before or during archive, and the purge job only preserves local billing timestamps / status needed for audit context.

## Table Rules

- `memories`: force delete all tenant memories, including soft-deleted rows and `visibility=secret` rows. This removes title, body, metadata, emotion values, and deleted-at content.
- `memory_tag`: delete all pivot rows for purged memories before deleting memories, or rely on cascade where safe.
- `categories`: delete all tenant categories after memories no longer reference them.
- `tags`: delete all tenant tags after memory pivots are gone.
- `personal_access_tokens`: delete all tokens for tenant users. Archive normally did this already, but purge must be idempotent.
- `secret_unlock_tokens`: delete all unlock tokens for tenant users.
- `password_reset_tokens`: delete reset tokens for tenant user emails before anonymizing those emails.
- `sessions`: delete session rows for tenant users, even though API auth is token-first.
- `tenant_member_invitations`: delete all tenant invitation rows, including accepted, expired, and revoked rows. Invitation emails and token hashes are operational data, not retained audit records.
- `users`: anonymize and detach tenant users instead of physically deleting user rows. Set `tenant_id = null`, `role = member`, `account_status = disabled`, clear pending email / remember token / secret unlock password, replace name and email with non-identifying values, invalidate password, and set `deleted_at` / `anonymized_at` when available.
- `security_events`: retain minimal event rows for audit, but scrub PII-heavy fields for the purged tenant. Set `subject_email`, `ip_address`, `user_agent`, and raw `metadata` to null for pre-existing tenant events. Keep `tenant_id`, `user_id`, `event_type`, `outcome`, and `created_at`.
- `tenants`: keep the row as a tombstone. Set `purged_at`, scrub `name`, `slug`, and `archive_reason`, and keep `public_id`, archive/deletion timestamps, and local subscription fields.

## Audit

- The implementation adds internal event type `auth.tenant.purge`.
- Success events may include scrub-safe counts such as deleted memory count, deleted category count, deleted tag count, deleted invitation count, anonymized user count, and scrubbed security event count.
- Failure events may include a machine-readable reason and exception class, but must not include memory content, invitation tokens, emails, raw metadata, password values, Bearer tokens, secret unlock tokens, IP addresses, or user agents.
- The purge event itself should be written after pre-existing tenant security events are scrubbed so its safe count metadata can remain useful.

## Rationale

Keeping a tenant tombstone avoids ambiguity around repeated purge runs, support investigations, and historical audit references. Deleting or anonymizing the child data removes the actual user content and credentials while preserving enough lifecycle state to prove that the tenant was archived and later purged.

Making purge an internal command first is safer than exposing an API. It keeps irreversible deletion under operator control until billing integration, broader audit retention, and tenant settings UI are more mature.

## Implementation Split

1. Completed: design tenant purge retention policy and table-level cleanup rules.
2. Completed: implement `bunshin:purge-archived-tenants` with dry-run, limit, eligibility query, row locking, table cleanup, anonymization, `auth.tenant.purge` logging, and tests.
3. Completed: schedule the command daily with production enablement config, failure email hook, output log, overlap protection, and `docs/operations/tenant_purge_runbook.md`.

## Next Task

Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
