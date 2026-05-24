<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TenantSubscriptionQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_defaults_to_active_free_plan_with_configured_limits(): void
    {
        $tenant = $this->tenant();

        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan_key);
        $this->assertSame(Tenant::SUBSCRIPTION_STATUS_ACTIVE, $tenant->subscription_status);
        $this->assertTrue($tenant->hasActivePlan());
        $this->assertSame(1000, $tenant->memoryQuotaLimit());
        $this->assertSame(100, $tenant->categoryQuotaLimit());
    }

    public function test_active_tenant_can_create_memory_and_category_under_quota(): void
    {
        config([
            'bunshin.plans.free.limits.memories' => 1,
            'bunshin.plans.free.limits.categories' => 1,
        ]);

        $tenant = $this->tenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'body' => 'quota 内の記憶。',
                'visibility' => Memory::VISIBILITY_PRIVATE,
            ])
            ->assertCreated();

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => '学校',
                'slug' => 'school',
            ])
            ->assertCreated();
    }

    public function test_inactive_subscription_blocks_memory_and_category_creation(): void
    {
        $tenant = $this->tenant([
            'subscription_status' => Tenant::SUBSCRIPTION_STATUS_CANCELED,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'body' => 'inactive tenant では作成できない記憶。',
                'visibility' => Memory::VISIBILITY_PRIVATE,
            ])
            ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
            ->assertJsonPath('message', 'Tenant subscription is not active.');

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => '学校',
                'slug' => 'school',
            ])
            ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
            ->assertJsonPath('message', 'Tenant subscription is not active.');

        $this->assertDatabaseCount('memories', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_memory_creation_rejects_tenant_over_plan_quota(): void
    {
        config(['bunshin.plans.free.limits.memories' => 1]);

        $tenant = $this->tenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'body' => '既存の記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'body' => 'quota 超過の記憶。',
                'visibility' => Memory::VISIBILITY_PRIVATE,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quota', 'memories']);

        $this->assertDatabaseCount('memories', 1);
    }

    public function test_category_creation_rejects_tenant_over_plan_quota(): void
    {
        config(['bunshin.plans.free.limits.categories' => 1]);

        $tenant = $this->tenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'name' => '学校',
            'slug' => 'school',
        ]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => '部活',
                'slug' => 'club',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quota', 'categories']);

        $this->assertDatabaseCount('categories', 1);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Bunshin AI',
            'slug' => 'bunshin-ai',
            ...$attributes,
        ])->refresh();
    }
}
