# 0027: Security Event Pruning Policy

Date: 2026-05-16

## Status

Accepted. Command, scheduler, schedule tests, and operations runbook are complete.

## Context

`security_events` is now the v1 audit sink for auth, tenant lifecycle, token lifecycle, tenant member management, profile / secret unlock password changes, memory writes, and category writes. Decision 0026 set the initial active-tenant retention target to 180 days. Decision 0025 also keeps scrubbed minimal audit rows after tenant purge, but no command currently enforces retention for old audit rows.

Without pruning, `security_events` will grow indefinitely and retain PII-bearing fields such as `subject_email`, `ip_address`, `user_agent`, and safe-but-operational metadata longer than the accepted initial policy.

## Decision

- Audit pruning is an internal operational job, not a public API endpoint.
- Initial implementation exposes a console command named `bunshin:prune-security-events`.
- Default retention is 180 days, matching decision 0026 and product policy decision 0011.
- Retention days will be configured via `BUNSHIN_SECURITY_EVENT_RETENTION_DAYS`, defaulting to `180`. The command should reject unsafe values outside a conservative range such as 30 to 3650 days.
- Events with `tenant_id = null` are pruned when `created_at` is older than the retention cutoff.
- Events for non-purged tenants are pruned when `created_at` is older than the retention cutoff. This includes active tenants and archived tenants that have not completed purge.
- Events for purged tenants are also pruning targets, including scrubbed pre-existing tenant rows and `auth.tenant.purge` rows. However, they are retained until the tenant's `purged_at` is older than the retention cutoff, then pruned in batches. This keeps minimal scrubbed closure evidence for 180 days after irreversible purge while still avoiding indefinite retention.
- Tenant tombstone rows remain after security events are pruned. The tombstone, not old audit rows, is the long-term proof that the tenant was archived and purged.
- No event type gets indefinite retention in v1. Legal hold, compliance export, and external audit archive are future tasks and must be implemented explicitly before changing this policy.

## Command Contract

- Command: `php artisan bunshin:prune-security-events`
- Options:
  - `--dry-run`: report candidate counts without deleting rows.
  - `--limit=<n>`: maximum rows to delete in one run. Default `5000`; implementation caps the range at 1 to 50000.
  - optional `tenant` argument: tenant public id or slug. This targets tenant-bound events only. For purged tenants, operators should prefer the stable `ten_...` public id because slug is scrubbed during purge.
- Dry run must report counts by retention bucket: null-tenant events, non-purged tenant events, and purged-tenant events. It must not print `subject_email`, IP addresses, user agents, raw metadata, memory/category names, token material, or secret content.
- Mutation mode should delete only `security_events` rows. It must not scrub rows; tenant purge already performs PII scrubbing for purged tenants.
- The command should process deterministic batches ordered by `created_at` and `id`, delete up to `--limit`, and be safe to run repeatedly.
- The command should not create a `security_events` row for its own pruning work. Scheduled output and operational logs are sufficient for v1 and avoid self-generating audit rows that later need pruning.

## Scheduler And Operations

- The command is registered in Laravel Scheduler.
- Default cadence: daily after tenant purge at `04:15 UTC`.
- Default enablement: production only, controlled by `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_ENABLED`.
- Additional config should mirror tenant purge operations:
  - `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_TIME`
  - `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_TIMEZONE`
  - `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_LIMIT`
  - `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_OUTPUT_LOG`
- Scheduled runs should use `withoutOverlapping(120)` and `onOneServer()`.
- If `BUNSHIN_OPERATIONS_ALERT_EMAIL` is configured, scheduled failure output should be emailed.
- Operational monitoring should alert on repeated non-zero exits and on rows that remain older than the retention cutoff after more than one scheduled run.

## Query Shape

The implementation should build separate candidate queries rather than one broad `OR` query:

1. Null-tenant events: `tenant_id is null` and `created_at < cutoff`.
2. Non-purged tenant events: join `tenants` where `tenants.purged_at is null` and `security_events.created_at < cutoff`.
3. Purged tenant events: join `tenants` where `tenants.purged_at < cutoff`.

For tenant-targeted runs, only the tenant-bound branches should run and the command should fail if the target public id / slug does not exist.

## Failure Handling

- A failed run may leave some eligible rows unpruned. Re-running the command must converge without changing newer rows.
- The command should return non-zero if validation fails or a delete batch throws.
- Operators should run `--dry-run` after fixing the root cause, then re-run mutation mode with a conservative `--limit`.
- Failure output must not include row metadata or PII.

## Consequences

- Active tenant audit retention is now enforceable at the accepted 180-day target.
- Purged tenant audit rows are not retained indefinitely, but the system still keeps minimal scrubbed purge context for 180 days after irreversible purge.
- External logging/search remains separate and is designed in decision 0028. If the product later needs longer analytics, compliance archive, or support search, it should be implemented as a separate sanitized pipeline before relying on this prune command as the only event copy.

## Implementation Split

1. Implement `bunshin:prune-security-events` with config, dry-run, limit, tenant targeting, deterministic batch deletion, and Feature tests. Complete.
2. Register the scheduler with production-safe config, output log, overlap controls, failure email hook, and schedule tests. Complete.
3. Add an operations runbook for manual dry run, mutation mode, monitoring, and failure handling. Complete.
4. Separately design external logging/search integration if longer audit search or analytics becomes a product requirement. Complete in decision 0028; implementation deferred.

## Next Task

External logging/search integration is designed in decision 0028 and implementation is deferred until longer audit search, analytics, compliance archive, or support investigation workflows become a concrete product requirement. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
