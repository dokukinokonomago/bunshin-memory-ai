<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

class PruneSecurityEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_prunes_eligible_security_event_buckets_without_self_audit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-16 00:00:00 UTC'));
        config(['bunshin.security.event_retention_days' => 180]);

        $activeTenant = $this->tenant('active-tenant');
        $oldPurgedTenant = $this->tenant('old-purged-tenant', [
            'purged_at' => now()->subDays(181),
        ]);
        $recentPurgedTenant = $this->tenant('recent-purged-tenant', [
            'purged_at' => now()->subDays(179),
        ]);

        $oldNullEvent = $this->securityEvent(null, now()->subDays(181));
        $recentNullEvent = $this->securityEvent(null, now()->subDays(179));
        $oldActiveEvent = $this->securityEvent($activeTenant, now()->subDays(181));
        $recentActiveEvent = $this->securityEvent($activeTenant, now()->subDays(179));
        $oldPurgedRecentEvent = $this->securityEvent($oldPurgedTenant, now()->subDays(10));
        $oldPurgedOldEvent = $this->securityEvent($oldPurgedTenant, now()->subDays(400));
        $recentPurgedEvent = $this->securityEvent($recentPurgedTenant, now()->subDays(400));
        $initialCount = SecurityEvent::query()->count();

        $exitCode = Artisan::call('bunshin:prune-security-events');
        $output = Artisan::output();

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('Pruned security events. Deleted: 4.', $output);
        $this->assertSafeOutput($output);

        foreach ([$oldNullEvent, $oldActiveEvent, $oldPurgedRecentEvent, $oldPurgedOldEvent] as $event) {
            $this->assertDatabaseMissing('security_events', ['id' => $event->id]);
        }

        foreach ([$recentNullEvent, $recentActiveEvent, $recentPurgedEvent] as $event) {
            $this->assertDatabaseHas('security_events', ['id' => $event->id]);
        }

        $this->assertSame($initialCount - 4, SecurityEvent::query()->count());
    }

    public function test_dry_run_reports_candidate_counts_without_mutating_or_exposing_metadata(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-16 00:00:00 UTC'));
        config(['bunshin.security.event_retention_days' => 180]);

        $activeTenant = $this->tenant('dry-active-tenant');
        $oldPurgedTenant = $this->tenant('dry-old-purged-tenant', [
            'purged_at' => now()->subDays(181),
        ]);
        $oldNullEvent = $this->securityEvent(null, now()->subDays(181));
        $oldActiveEvent = $this->securityEvent($activeTenant, now()->subDays(181));
        $oldPurgedEvent = $this->securityEvent($oldPurgedTenant, now()->subDay());

        $exitCode = Artisan::call('bunshin:prune-security-events', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('Dry run: no rows changed.', $output);
        $this->assertStringContainsString('Null-tenant events', $output);
        $this->assertStringContainsString('Non-purged tenant events', $output);
        $this->assertStringContainsString('Purged tenant events', $output);
        $this->assertSafeOutput($output);

        foreach ([$oldNullEvent, $oldActiveEvent, $oldPurgedEvent] as $event) {
            $this->assertDatabaseHas('security_events', ['id' => $event->id]);
        }
    }

    public function test_limit_and_tenant_target_prune_only_target_tenant_bound_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-16 00:00:00 UTC'));
        config(['bunshin.security.event_retention_days' => 180]);

        $targetTenant = $this->tenant('target-tenant');
        $otherTenant = $this->tenant('other-tenant');
        $targetOldestEvent = $this->securityEvent($targetTenant, now()->subDays(220));
        $targetNewerEvent = $this->securityEvent($targetTenant, now()->subDays(210));
        $otherTenantEvent = $this->securityEvent($otherTenant, now()->subDays(220));
        $nullTenantEvent = $this->securityEvent(null, now()->subDays(220));

        $firstExitCode = Artisan::call('bunshin:prune-security-events', [
            'tenant' => $targetTenant->public_id,
            '--limit' => 1,
        ]);

        $this->assertSame(SymfonyCommand::SUCCESS, $firstExitCode);
        $this->assertDatabaseMissing('security_events', ['id' => $targetOldestEvent->id]);
        $this->assertDatabaseHas('security_events', ['id' => $targetNewerEvent->id]);
        $this->assertDatabaseHas('security_events', ['id' => $otherTenantEvent->id]);
        $this->assertDatabaseHas('security_events', ['id' => $nullTenantEvent->id]);

        $secondExitCode = Artisan::call('bunshin:prune-security-events', [
            'tenant' => $targetTenant->slug,
        ]);

        $this->assertSame(SymfonyCommand::SUCCESS, $secondExitCode);
        $this->assertDatabaseMissing('security_events', ['id' => $targetNewerEvent->id]);
        $this->assertDatabaseHas('security_events', ['id' => $otherTenantEvent->id]);
        $this->assertDatabaseHas('security_events', ['id' => $nullTenantEvent->id]);
    }

    public function test_command_rejects_invalid_options_and_unknown_target_without_deleting_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-16 00:00:00 UTC'));
        $oldEvent = $this->securityEvent(null, now()->subDays(220));

        config(['bunshin.security.event_retention_days' => 10]);

        $invalidRetentionExitCode = Artisan::call('bunshin:prune-security-events');
        $invalidRetentionOutput = Artisan::output();

        $this->assertSame(SymfonyCommand::FAILURE, $invalidRetentionExitCode);
        $this->assertStringContainsString('BUNSHIN_SECURITY_EVENT_RETENTION_DAYS', $invalidRetentionOutput);
        $this->assertDatabaseHas('security_events', ['id' => $oldEvent->id]);

        config(['bunshin.security.event_retention_days' => 180]);

        $invalidLimitExitCode = Artisan::call('bunshin:prune-security-events', ['--limit' => 0]);
        $invalidLimitOutput = Artisan::output();

        $this->assertSame(SymfonyCommand::FAILURE, $invalidLimitExitCode);
        $this->assertStringContainsString('The --limit option must be an integer between 1 and 50000.', $invalidLimitOutput);
        $this->assertDatabaseHas('security_events', ['id' => $oldEvent->id]);

        $unknownTargetExitCode = Artisan::call('bunshin:prune-security-events', ['tenant' => 'missing-tenant']);
        $unknownTargetOutput = Artisan::output();

        $this->assertSame(SymfonyCommand::FAILURE, $unknownTargetExitCode);
        $this->assertStringContainsString('Tenant target not found.', $unknownTargetOutput);
        $this->assertDatabaseHas('security_events', ['id' => $oldEvent->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenant(string $slug, array $attributes = []): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant '.$slug,
            'slug' => $slug,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function securityEvent(?Tenant $tenant, Carbon $createdAt, array $attributes = []): SecurityEvent
    {
        return SecurityEvent::query()->create([
            'tenant_id' => $tenant?->id,
            'user_id' => null,
            'event_type' => SecurityEvent::TYPE_LOGIN,
            'outcome' => SecurityEvent::OUTCOME_SUCCESS,
            'subject_email' => 'sensitive-subject@example.test',
            'ip_address' => '192.0.2.44',
            'user_agent' => 'Sensitive User Agent',
            'metadata' => [
                'raw_email' => 'sensitive-subject@example.test',
                'secret_content' => 'secret body should never print',
                'token' => 'plain-token-should-never-print',
            ],
            'created_at' => $createdAt,
            ...$attributes,
        ]);
    }

    private function assertSafeOutput(string $output): void
    {
        $this->assertStringNotContainsString('sensitive-subject@example.test', $output);
        $this->assertStringNotContainsString('192.0.2.44', $output);
        $this->assertStringNotContainsString('Sensitive User Agent', $output);
        $this->assertStringNotContainsString('secret body should never print', $output);
        $this->assertStringNotContainsString('plain-token-should-never-print', $output);
    }
}
