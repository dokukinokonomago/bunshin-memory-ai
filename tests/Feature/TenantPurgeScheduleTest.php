<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class TenantPurgeScheduleTest extends TestCase
{
    public function test_tenant_purge_command_is_registered_with_production_safe_guards(): void
    {
        $event = $this->tenantPurgeEvent();

        $this->assertStringContainsString('bunshin:purge-archived-tenants', (string) $event->command);
        $this->assertStringContainsString('--limit=50', (string) $event->command);
        $this->assertSame('30 3 * * *', $event->expression);
        $this->assertSame('UTC', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(120, $event->expiresAt);
        $this->assertTrue($event->onOneServer);
        $this->assertSame('Purge archived tenants past retention', $event->description);
        $this->assertSame(storage_path('logs/tenant-purge-schedule.log'), $event->output);
        $this->assertTrue($event->shouldAppendOutput);

        config(['bunshin.operations.tenant_purge.schedule_enabled' => false]);
        $this->assertFalse($event->filtersPass($this->app));

        config(['bunshin.operations.tenant_purge.schedule_enabled' => true]);
        $this->assertTrue($event->filtersPass($this->app));
    }

    private function tenantPurgeEvent(): Event
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(static fn (Event $event): bool => str_contains(
                (string) $event->command,
                'bunshin:purge-archived-tenants',
            ));

        $this->assertInstanceOf(Event::class, $event);

        return $event;
    }
}
