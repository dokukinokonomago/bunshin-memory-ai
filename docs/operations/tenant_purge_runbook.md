# Tenant Purge Runbook

## Purpose

`bunshin:purge-archived-tenants` permanently purges archived tenants after their retention window. The command removes or anonymizes memory content, credentials, invitations, and tenant user PII, while keeping a minimal tenant tombstone and scrubbed audit records.

This operation is irreversible. Do not run mutation mode until the target tenant, retention state, backup posture, and approval path are confirmed.

## Scheduled Run

- Scheduler registration: `routes/console.php`
- Command: `php artisan bunshin:purge-archived-tenants --limit=<limit>`
- Default cadence: daily at `03:30 UTC`
- Default limit: `50` tenants per run
- Default enablement: enabled only when `APP_ENV=production`
- Overlap controls: `withoutOverlapping(120)` and `onOneServer()`
- Output log: `storage/logs/tenant-purge-schedule.log`

Production must run Laravel's scheduler every minute, for example:

```sh
* * * * * cd /path/to/bunshin-memory-ai && php artisan schedule:run >> /dev/null 2>&1
```

## Environment Variables

- `BUNSHIN_TENANT_PURGE_SCHEDULE_ENABLED`: set to `true` to allow the scheduled purge event to run. Defaults to true only in production.
- `BUNSHIN_TENANT_PURGE_SCHEDULE_TIME`: daily run time in `HH:MM` format. Defaults to `03:30`.
- `BUNSHIN_TENANT_PURGE_SCHEDULE_TIMEZONE`: scheduler timezone. Defaults to `UTC`.
- `BUNSHIN_TENANT_PURGE_SCHEDULE_LIMIT`: batch size. The scheduler clamps this to the command's supported range of 1 to 500. Defaults to `50`.
- `BUNSHIN_TENANT_PURGE_SCHEDULE_OUTPUT_LOG`: command output log path. Defaults to `storage/logs/tenant-purge-schedule.log`.
- `BUNSHIN_OPERATIONS_ALERT_EMAIL`: optional email recipient for failed scheduled runs.

## Alerting

Set `BUNSHIN_OPERATIONS_ALERT_EMAIL` in production. Laravel will email scheduled command output when the command exits non-zero. The command returns failure if one or more tenant purge attempts fail.

Operational monitoring should also alert on:

- scheduler host not running `php artisan schedule:run`
- repeated non-zero exits in `storage/logs/tenant-purge-schedule.log`
- eligible archived tenants remaining for more than one day after `scheduled_deletion_at`
- unexpected growth of archived tenants with `purged_at = null`

## Manual Dry Run

List eligible tenants and estimated cleanup counts without changing rows:

```sh
php artisan bunshin:purge-archived-tenants --dry-run --limit=50
```

Dry run a single tenant by public id or slug:

```sh
php artisan bunshin:purge-archived-tenants ten_01EXAMPLE --dry-run
php artisan bunshin:purge-archived-tenants tenant-slug --dry-run
```

Before mutation mode, verify:

- `archived_at` is not null
- `scheduled_deletion_at <= now()`
- `purged_at` is null
- the tenant is outside any support, billing, legal, or product hold
- latest backup policy is acceptable for an irreversible purge

## Manual Mutation

Run a small batch:

```sh
php artisan bunshin:purge-archived-tenants --limit=10
```

Run one confirmed tenant:

```sh
php artisan bunshin:purge-archived-tenants ten_01EXAMPLE
```

The command is idempotent. A purged tenant is skipped on later runs because `purged_at` is no longer null.

## Failure Handling

1. Read `storage/logs/tenant-purge-schedule.log` and application logs.
2. Check `security_events` for `auth.tenant.purge` failure records. Failure metadata must remain scrub-safe and must not include emails, tokens, IP addresses, user agents, memory body, or secret content.
3. Fix the root cause.
4. Re-run the failed tenant with `--dry-run`.
5. Run mutation mode for the single confirmed tenant.

If a purge must be reversed, treat it as a disaster recovery restore from backups. There is no application-level rollback after `purged_at` is set.
