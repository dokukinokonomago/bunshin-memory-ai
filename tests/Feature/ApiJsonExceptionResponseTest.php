<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiJsonExceptionResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_api_routes_return_json_unauthorized_without_accept_header(): void
    {
        $this
            ->get('/api/v1/categories')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_api_validation_errors_return_json_without_accept_header(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->withApiToken($user)
            ->post('/api/v1/memories', [
                'body' => '   ',
                'period_key' => 'future',
                'visibility' => 'public',
            ])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors([
                'body',
                'period_key',
                'visibility',
            ]);
    }
}
