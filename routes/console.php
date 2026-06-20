<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tenantPurgeLimit = max(1, min(500, (int) config('bunshin.operations.tenant_purge.schedule_limit', 50)));
$tenantPurgeOutputLog = (string) config(
    'bunshin.operations.tenant_purge.schedule_output_log',
    storage_path('logs/tenant-purge-schedule.log'),
);

$tenantPurgeSchedule = Schedule::command('bunshin:purge-archived-tenants', [
    '--limit' => $tenantPurgeLimit,
])
    ->dailyAt((string) config('bunshin.operations.tenant_purge.schedule_time', '03:30'))
    ->timezone((string) config('bunshin.operations.tenant_purge.schedule_timezone', 'UTC'))
    ->withoutOverlapping(120)
    ->onOneServer()
    ->when(static fn (): bool => (bool) config('bunshin.operations.tenant_purge.schedule_enabled', false))
    ->appendOutputTo($tenantPurgeOutputLog)
    ->description('Purge archived tenants past retention');

$tenantPurgeAlertEmail = trim((string) config('bunshin.operations.alert_email', ''));

if ($tenantPurgeAlertEmail !== '') {
    $tenantPurgeSchedule->emailOutputOnFailure($tenantPurgeAlertEmail);
}

$securityEventPruneLimit = max(1, min(50000, (int) config('bunshin.operations.security_event_prune.schedule_limit', 5000)));
$securityEventPruneOutputLog = (string) config(
    'bunshin.operations.security_event_prune.schedule_output_log',
    storage_path('logs/security-event-prune-schedule.log'),
);

$securityEventPruneSchedule = Schedule::command('bunshin:prune-security-events', [
    '--limit' => $securityEventPruneLimit,
])
    ->dailyAt((string) config('bunshin.operations.security_event_prune.schedule_time', '04:15'))
    ->timezone((string) config('bunshin.operations.security_event_prune.schedule_timezone', 'UTC'))
    ->withoutOverlapping(120)
    ->onOneServer()
    ->when(static fn (): bool => (bool) config('bunshin.operations.security_event_prune.schedule_enabled', false))
    ->appendOutputTo($securityEventPruneOutputLog)
    ->description('Prune security events past retention');

$securityEventPruneAlertEmail = trim((string) config('bunshin.operations.alert_email', ''));

if ($securityEventPruneAlertEmail !== '') {
    $securityEventPruneSchedule->emailOutputOnFailure($securityEventPruneAlertEmail);
}
