<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

class IssueAdminTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_creates_tenant_user_and_usable_bearer_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 00:00:00 UTC'));

        [$exitCode, $output, $plainTextToken] = $this->issueToken([
            '--tenant' => 'demo-tenant',
            '--tenant-name' => 'Demo Tenant',
            '--email' => 'ADMIN@EXAMPLE.TEST',
            '--name' => 'Admin User',
            '--token-name' => 'admin-mockup',
            '--expires-days' => '14',
        ]);

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('Admin API token issued.', $output);
        $this->assertStringContainsString('Bearer token is shown once.', $output);
        $this->assertNotNull($plainTextToken);

        $tenant = Tenant::query()->where('slug', 'demo-tenant')->firstOrFail();
        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $token = PersonalAccessToken::query()->firstOrFail();
        [$tokenId, $rawToken] = explode('|', $plainTextToken, 2);

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame(User::ROLE_OWNER, $user->role);
        $this->assertSame('Admin User', $user->name);
        $this->assertSame((string) $token->id, $tokenId);
        $this->assertSame(hash('sha256', $rawToken), $token->token);
        $this->assertSame('admin-mockup', $token->name);
        $this->assertSame(['*'], $token->abilities);
        $this->assertTrue($token->expires_at->equalTo(now()->addDays(14)));

        $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/categories')
            ->assertOk();
    }

    public function test_command_reissues_token_by_revoking_existing_token_with_same_name(): void
    {
        [$firstExitCode, , $firstToken] = $this->issueToken([
            '--tenant' => 'demo-tenant',
            '--email' => 'admin@example.test',
            '--token-name' => 'admin-mockup',
        ]);
        $firstTokenId = PersonalAccessToken::query()->firstOrFail()->id;

        [$secondExitCode, $secondOutput, $secondToken] = $this->issueToken([
            '--tenant' => 'demo-tenant',
            '--email' => 'admin@example.test',
            '--token-name' => 'admin-mockup',
        ]);

        $this->assertSame(SymfonyCommand::SUCCESS, $firstExitCode);
        $this->assertSame(SymfonyCommand::SUCCESS, $secondExitCode);
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertStringContainsString('Revoked existing tokens: 1', $secondOutput);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $firstTokenId]);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this
            ->withHeader('Authorization', 'Bearer '.$firstToken)
            ->getJson('/api/v1/categories')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/v1/categories')
            ->assertOk();
    }

    public function test_command_rejects_invalid_options_without_creating_records(): void
    {
        [$exitCode, $output, $plainTextToken] = $this->issueToken([
            '--expires-days' => '0',
        ]);

        $this->assertSame(SymfonyCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid options.', $output);
        $this->assertStringContainsString('expires-days', $output);
        $this->assertNull($plainTextToken);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_command_validates_role_option_without_creating_records(): void
    {
        [$exitCode, $output, $plainTextToken] = $this->issueToken([
            '--role' => 'super-admin',
        ]);

        $this->assertSame(SymfonyCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid options.', $output);
        $this->assertStringContainsString('role', $output);
        $this->assertNull($plainTextToken);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{int, string, string|null}
     */
    private function issueToken(array $parameters): array
    {
        $exitCode = Artisan::call('bunshin:issue-admin-token', $parameters);
        $output = Artisan::output();
        preg_match('/Bearer token:\s*(\S+)/', $output, $matches);

        return [$exitCode, $output, $matches[1] ?? null];
    }
}
