# Security Event Pruning Runbook

## Purpose

`bunshin:prune-security-events` deletes old rows from the `security_events` audit sink according to the accepted retention policy. It keeps the primary database from retaining auth, lifecycle, and broader audit records indefinitely.

This job deletes only `security_events` rows. It must not mutate tenants, users, memories, categories, tags, invitations, personal access tokens, or secret unlock tokens.

## Retention Policy

- Default retention: `180` days.
- Config source: `BUNSHIN_SECURITY_EVENT_RETENTION_DAYS`.
- Accepted range: `30` to `3650` days. The command exits non-zero outside that range.
- Null-tenant events are eligible when `security_events.created_at < cutoff`.
- Non-purged tenant events are eligible when tenant `purged_at` is null and `security_events.created_at < cutoff`.
- Purged tenant events are eligible when tenant `purged_at < cutoff`, including scrubbed pre-existing tenant audit rows and `auth.tenant.purge` rows.
- Tenant tombstones remain after audit rows are pruned.

## Scheduled Run

- Scheduler registration: `routes/console.php`
- Command: `php artisan bunshin:prune-security-events --limit=<limit>`
- Default cadence: daily at `04:15 UTC`
- Default limit: `5000` rows per run
- Default enablement: enabled only when `APP_ENV=production`
- Overlap controls: `withoutOverlapping(120)` and `onOneServer()`
- Output log: `storage/logs/security-event-prune-schedule.log`

Production must run Laravel's scheduler every minute, for example:

```sh
* * * * * cd /path/to/bunshin-memory-ai && php artisan schedule:run >> /dev/null 2>&1
```

## Environment Variables

- `BUNSHIN_SECURITY_EVENT_RETENTION_DAYS`: retention window in days. Defaults to `180`; command accepts `30` to `3650`.
- `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_ENABLED`: set to `true` to allow the scheduled prune event to run. Defaults to true only in production.
- `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_TIME`: daily run time in `HH:MM` format. Defaults to `04:15`.
- `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_TIMEZONE`: scheduler timezone. Defaults to `UTC`.
- `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_LIMIT`: batch size. The scheduler clamps this to the command's supported range of 1 to 50000. Defaults to `5000`.
- `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_OUTPUT_LOG`: command output log path. Defaults to `storage/logs/security-event-prune-schedule.log`.
- `BUNSHIN_OPERATIONS_ALERT_EMAIL`: optional email recipient for failed scheduled runs.

## Alerting

Set `BUNSHIN_OPERATIONS_ALERT_EMAIL` in production. Laravel will email scheduled command output when the command exits non-zero.

Operational monitoring should also alert on:

- scheduler host not running `php artisan schedule:run`
- repeated non-zero exits in `storage/logs/security-event-prune-schedule.log`
- candidate rows older than the retention cutoff remaining after more than one scheduled run
- unexpected growth of `security_events` row count relative to tenant and write volume
- output log path not writable by the scheduler process

## Manual Dry Run

Report candidate counts without changing rows:

```sh
php artisan bunshin:prune-security-events --dry-run --limit=5000
```

Dry run one tenant by public id or slug:

```sh
php artisan bunshin:prune-security-events ten_01EXAMPLE --dry-run
php artisan bunshin:prune-security-events tenant-slug --dry-run
```

For purged tenants, prefer the stable `ten_...` public id. Tenant purge may scrub or change slug-like operator context, while public id remains the durable reference.

Dry-run output is bucketed as:

- `Null-tenant events`
- `Non-purged tenant events`
- `Purged tenant events`

Before mutation mode, verify:

- `BUNSHIN_SECURITY_EVENT_RETENTION_DAYS` matches the approved retention window
- dry-run candidate counts are plausible for the current environment
- the tenant target is correct when using single-tenant mode
- no legal hold, incident investigation, or support hold requires temporarily disabling pruning
- database backup and restore posture is acceptable for deleting old audit rows

## Manual Mutation

Run a small batch:

```sh
php artisan bunshin:prune-security-events --limit=1000
```

Run the default batch:

```sh
php artisan bunshin:prune-security-events --limit=5000
```

Run one confirmed tenant:

```sh
php artisan bunshin:prune-security-events ten_01EXAMPLE --limit=1000
```

The command is safe to run repeatedly. It selects deterministic batches ordered by `security_events.created_at` and `security_events.id`, then deletes up to `--limit`.

Tenant-targeted mode prunes only tenant-bound events for the target tenant. It does not prune null-tenant events.

## Safety Rules

Do not paste or store the following in runbooks, tickets, alert annotations, chat, or incident notes:

- subject emails
- IP addresses
- user agents
- raw `security_events.metadata`
- memory titles or bodies
- category or tag names
- secret memory content
- plain account passwords, secret unlock passwords, invite tokens, reset tokens, Bearer tokens, or signed URL secrets

Use counts, bucket names, event types, public ids, timestamps, command exit codes, and scrub-safe error classes instead.

The command output is intentionally limited to bucket labels and counts. If deeper investigation is required, query production data through approved access paths and redact findings before sharing.

## Failure Handling

1. Read `storage/logs/security-event-prune-schedule.log` and application logs.
2. Confirm whether the failure was option validation, unknown tenant target, database connectivity, permission, lock, or delete failure.
3. Fix the root cause.
4. Re-run `php artisan bunshin:prune-security-events --dry-run --limit=5000`.
5. If counts are expected, run mutation mode with a conservative `--limit`.
6. Re-check the scheduled run after the next `04:15 UTC` window.

If a delete failure occurs after a partial batch, re-run the command. Already deleted rows are skipped and eligible remaining rows converge over later runs.

## Verification Queries

Use aggregate checks only. Avoid selecting PII-bearing columns.

Example retention check for old rows:

```sql
select count(*) as old_security_events
from security_events
where created_at < datetime('now', '-180 days');
```

Example per-tenant volume check:

```sql
select tenant_id, count(*) as event_count
from security_events
group by tenant_id
order by event_count desc
limit 20;
```

Adapt date functions to the production database engine. Do not export raw audit rows for routine pruning verification.

## Related References

- Policy: `docs/decisions/0027-security-event-pruning-policy.md`
- Broader audit scope: `docs/decisions/0026-broader-audit-log-admin-impersonation.md`
- Tenant purge retention: `docs/decisions/0025-tenant-purge-retention-policy.md`
- Scheduler: `routes/console.php`
- Config: `config/bunshin.php`
