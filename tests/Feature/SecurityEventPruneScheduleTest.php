<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionProperty;
use Tests\TestCase;

class SecurityEventPruneScheduleTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $originalEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::tearDown();
    }

    public function test_security_event_prune_command_is_registered_with_production_safe_guards(): void
    {
        $event = $this->securityEventPruneEvent();

        $this->assertStringContainsString('bunshin:prune-security-events', (string) $event->command);
        $this->assertStringContainsString('--limit=5000', (string) $event->command);
        $this->assertSame('15 4 * * *', $event->expression);
        $this->assertSame('UTC', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(120, $event->expiresAt);
        $this->assertTrue($event->onOneServer);
        $this->assertSame('Prune security events past retention', $event->description);
        $this->assertSame(storage_path('logs/security-event-prune-schedule.log'), $event->output);
        $this->assertTrue($event->shouldAppendOutput);

        config(['bunshin.operations.security_event_prune.schedule_enabled' => false]);
        $this->assertFalse($event->filtersPass($this->app));

        config(['bunshin.operations.security_event_prune.schedule_enabled' => true]);
        $this->assertTrue($event->filtersPass($this->app));
    }

    public function test_security_event_prune_schedule_clamps_configured_limit(): void
    {
        $this->refreshApplicationWithEnv([
            'BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_LIMIT' => '60000',
        ]);

        $this->assertStringContainsString('--limit=50000', (string) $this->securityEventPruneEvent()->command);

        $this->refreshApplicationWithEnv([
            'BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_LIMIT' => '0',
        ]);

        $this->assertStringContainsString('--limit=1', (string) $this->securityEventPruneEvent()->command);
    }

    public function test_security_event_prune_schedule_adds_failure_email_hook_when_alert_email_is_configured(): void
    {
        $this->refreshApplicationWithEnv([
            'BUNSHIN_OPERATIONS_ALERT_EMAIL' => 'ops@example.test',
        ]);

        $this->assertGreaterThan(0, $this->afterCallbackCount($this->securityEventPruneEvent()));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function refreshApplicationWithEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $this->originalEnv)) {
                $this->originalEnv[$key] = getenv($key);
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $this->refreshApplication();
    }

    private function securityEventPruneEvent(): Event
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(static fn (Event $event): bool => str_contains(
                (string) $event->command,
                'bunshin:prune-security-events',
            ));

        $this->assertInstanceOf(Event::class, $event);

        return $event;
    }

    private function afterCallbackCount(Event $event): int
    {
        $property = new ReflectionProperty(Event::class, 'afterCallbacks');
        $property->setAccessible(true);

        return count($property->getValue($event));
    }
}
