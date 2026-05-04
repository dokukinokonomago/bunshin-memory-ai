<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_categories_inside_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $school = $this->createCategory($tenant, $user, '学校', 'school', 2);
        $family = $this->createCategory($tenant, $user, '家族', 'family', 1);
        $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work', 3);
        $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel', 4);
        $this->createMemory($tenant, $user, $school, '高校の記憶');
        $this->createMemory($tenant, $user, $school, '大学の記憶');
        $this->createMemory($tenant, $user, $family, '家族の記憶');

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $family->id)
            ->assertJsonPath('data.0.name', '家族')
            ->assertJsonPath('data.0.slug', 'family')
            ->assertJsonPath('data.0.sort_order', 1)
            ->assertJsonPath('data.0.memory_count', 1)
            ->assertJsonPath('data.0.archived', false)
            ->assertJsonPath('data.1.id', $school->id)
            ->assertJsonPath('data.1.memory_count', 2);
    }

    public function test_authenticated_user_can_create_category_inside_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => ' 学校 ',
                'slug' => ' School-Life ',
                'sort_order' => 10,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', '学校')
            ->assertJsonPath('data.slug', 'school-life')
            ->assertJsonPath('data.sort_order', 10)
            ->assertJsonPath('data.memory_count', 0)
            ->assertJsonPath('data.archived', false);

        $category = Category::query()->firstOrFail();

        $this->assertSame($tenant->id, $category->tenant_id);
        $this->assertSame($user->id, $category->owner_user_id);
    }

    public function test_category_payload_validation_and_scoped_slug_uniqueness(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->createCategory($tenant, $user, '学校', 'school');
        $this->createCategory($tenant, $sameTenantOtherUser, '学校', 'school');
        $this->createCategory($otherTenant, $otherTenantUser, '学校', 'school');

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => '   ',
                'slug' => 'bad slug',
                'sort_order' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug', 'sort_order']);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => '学校重複',
                'slug' => 'school',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        $this
            ->withApiToken($sameTenantOtherUser)
            ->postJson('/api/v1/categories', [
                'name' => '部活',
                'slug' => 'club',
            ])
            ->assertCreated();

        $this
            ->withApiToken($otherTenantUser)
            ->postJson('/api/v1/categories', [
                'name' => '部活',
                'slug' => 'club',
            ])
            ->assertCreated();
    }

    public function test_category_show_update_and_delete_stay_inside_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $category = $this->createCategory($tenant, $user, '学校', 'school');
        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');
        $memory = $this->createMemory($tenant, $user, $category, 'カテゴリ付きの記憶');

        $this
            ->withApiToken($user)
            ->getJson("/api/v1/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.memory_count', 1);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/categories/{$category->id}", [
                'name' => '学び',
                'slug' => 'learning',
                'sort_order' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '学び')
            ->assertJsonPath('data.slug', 'learning')
            ->assertJsonPath('data.sort_order', 5);

        foreach ([$sameTenantOtherOwnerCategory, $otherTenantCategory] as $outsideCategory) {
            $this
                ->withApiToken($user)
                ->getJson("/api/v1/categories/{$outsideCategory->id}")
                ->assertNotFound();

            $this
                ->withApiToken($user)
                ->patchJson("/api/v1/categories/{$outsideCategory->id}", [
                    'name' => '境界外更新',
                ])
                ->assertNotFound();

            $this
                ->withApiToken($user)
                ->deleteJson("/api/v1/categories/{$outsideCategory->id}")
                ->assertNotFound();
        }

        $this
            ->withApiToken($user)
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertNull($memory->refresh()->category_id);
    }

    public function test_categories_require_authentication(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->postJson('/api/v1/categories', ['name' => '学校', 'slug' => 'school'])->assertUnauthorized();
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createCategory(Tenant $tenant, User $owner, string $name, string $slug, int $sortOrder = 0): Category
    {
        return Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
        ]);
    }

    private function createMemory(Tenant $tenant, User $owner, Category $category, string $body): Memory
    {
        return Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'category_id' => $category->id,
            'body' => $body,
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
    }
}
