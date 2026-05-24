<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicIdRequestLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_memory_and_category_routes_accept_public_ids_and_keep_numeric_compatibility(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '学校', 'school');
        $deleteCategory = $this->createCategory($tenant, $user, '写真', 'photos');
        $memory = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '放課後',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this
            ->withApiToken($user)
            ->getJson("/api/v1/memories/{$memory->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $memory->id)
            ->assertJsonPath('data.public_id', $memory->public_id);

        $this
            ->withApiToken($user)
            ->getJson("/api/v1/memories/{$memory->id}")
            ->assertOk()
            ->assertJsonPath('data.public_id', $memory->public_id);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/memories/{$memory->public_id}", [
                'title' => '放課後の教室',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', '放課後の教室');

        $this
            ->withApiToken($user)
            ->getJson("/api/v1/categories/{$category->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.public_id', $category->public_id);

        $this
            ->withApiToken($user)
            ->getJson("/api/v1/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.public_id', $category->public_id);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/categories/{$category->public_id}", [
                'name' => '学び',
                'slug' => 'learning',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '学び')
            ->assertJsonPath('data.slug', 'learning');

        $this
            ->withApiToken($user)
            ->deleteJson("/api/v1/categories/{$deleteCategory->public_id}")
            ->assertNoContent();

        $this
            ->withApiToken($user)
            ->deleteJson("/api/v1/memories/{$memory->public_id}")
            ->assertNoContent();

        foreach ([
            "/api/v1/memories/{$memory->public_id}",
            "/api/v1/memories/{$category->public_id}",
            '/api/v1/memories/not-a-public-id',
            "/api/v1/categories/{$memory->public_id}",
            '/api/v1/categories/not-a-public-id',
        ] as $path) {
            $this
                ->withApiToken($user)
                ->getJson($path)
                ->assertNotFound();
        }
    }

    public function test_memory_category_reference_fields_accept_public_ids_and_validate_boundaries(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $school = $this->createCategory($tenant, $user, '学校', 'school');
        $family = $this->createCategory($tenant, $user, '家族', 'family');
        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');

        $response = $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'body' => 'public id で category を指定する記憶。',
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'category_id' => ' '.$school->public_id.' ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.category.public_id', $school->public_id);

        $memory = Memory::query()->findOrFail($response->json('data.id'));

        $this->assertSame($school->id, $memory->category_id);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/memories/{$memory->public_id}", [
                'category_id' => $family->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.category.public_id', $family->public_id);

        $this->assertSame($family->id, $memory->refresh()->category_id);

        foreach ([$sameTenantOtherOwnerCategory, $otherTenantCategory] as $outsideCategory) {
            $this
                ->withApiToken($user)
                ->postJson('/api/v1/memories', [
                    'body' => '境界外 category では作成できない記憶。',
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'category_id' => $outsideCategory->public_id,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['category_id']);
        }

        foreach ([$memory->public_id, 'cat_01HX0000000000000000000000', 'cat_01hx0000000000000000000000'] as $badCategoryId) {
            $this
                ->withApiToken($user)
                ->patchJson("/api/v1/memories/{$memory->public_id}", [
                    'category_id' => $badCategoryId,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['category_id']);
        }
    }

    public function test_category_parent_field_accepts_public_ids_and_validates_boundaries(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $school = $this->createCategory($tenant, $user, '学校', 'school');
        $work = $this->createCategory($tenant, $user, '仕事', 'work');
        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherUser, '家族', 'family');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');

        $response = $this
            ->withApiToken($user)
            ->postJson('/api/v1/categories', [
                'name' => '部活',
                'slug' => 'club',
                'parent_id' => $school->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent_public_id', $school->public_id);

        $club = Category::query()->findOrFail($response->json('data.id'));

        $this->assertSame($school->id, $club->parent_id);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/categories/{$club->public_id}", [
                'parent_id' => $work->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.parent_public_id', $work->public_id);

        $this->assertSame($work->id, $club->refresh()->parent_id);

        foreach ([$sameTenantOtherOwnerCategory, $otherTenantCategory, $club] as $invalidParent) {
            $this
                ->withApiToken($user)
                ->postJson('/api/v1/categories', [
                    'name' => '境界外',
                    'slug' => 'outside-'.$invalidParent->id,
                    'parent_id' => $invalidParent->public_id,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['parent_id']);
        }

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/categories/{$school->public_id}", [
                'parent_id' => $school->public_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/categories/{$work->public_id}", [
                'parent_id' => 'mem_01HX0000000000000000000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_list_and_memory_space_filters_accept_public_ids_and_keep_context_misses_empty(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $school = $this->createCategory($tenant, $user, '学校', 'school');
        $club = $this->createCategory($tenant, $user, '部活', 'club', $school);
        $family = $this->createCategory($tenant, $user, '家族', 'family');
        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');

        $schoolMemory = $this->createMemory($tenant, $user, [
            'category_id' => $school->id,
            'title' => '学校 root',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $clubMemory = $this->createMemory($tenant, $user, [
            'category_id' => $club->id,
            'title' => '部活',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $familyMemory = $this->createMemory($tenant, $user, [
            'category_id' => $family->id,
            'title' => '家族',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?'.http_build_query([
                'category_id' => $school->public_id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $schoolMemory->public_id);

        $descendantResponse = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?'.http_build_query([
                'category_id' => $school->public_id,
                'include_descendants' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$schoolMemory->public_id, $clubMemory->public_id],
            collect($descendantResponse->json('data'))->pluck('public_id')->all(),
        );

        foreach ([$sameTenantOtherOwnerCategory, $otherTenantCategory] as $outsideCategory) {
            $this
                ->withApiToken($user)
                ->getJson('/api/v1/memories?'.http_build_query([
                    'category_id' => $outsideCategory->public_id,
                    'include_descendants' => 1,
                ]))
                ->assertOk()
                ->assertJsonCount(0, 'data');

            $this
                ->withApiToken($user)
                ->getJson('/api/v1/memory-space?'.http_build_query([
                    'category_id' => $outsideCategory->public_id,
                ]))
                ->assertOk()
                ->assertJsonCount(0, 'data.memories')
                ->assertJsonPath('data.secret.locked_count', 0);
        }

        $spaceResponse = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memory-space?'.http_build_query([
                'category_id' => $school->public_id,
            ]))
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$schoolMemory->public_id, $clubMemory->public_id],
            collect($spaceResponse->json('data.memories'))->pluck('public_id')->all(),
        );
        $this->assertNotContains($familyMemory->public_id, collect($spaceResponse->json('data.memories'))->pluck('public_id')->all());

        foreach (['mem_01HX0000000000000000000000', 'cat_01hx0000000000000000000000'] as $badCategoryId) {
            $this
                ->withApiToken($user)
                ->getJson('/api/v1/memories?'.http_build_query([
                    'category_id' => $badCategoryId,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['category_id']);

            $this
                ->withApiToken($user)
                ->getJson('/api/v1/memory-space?'.http_build_query([
                    'category_id' => $badCategoryId,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['category_id']);
        }
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createCategory(
        Tenant $tenant,
        User $owner,
        string $name,
        string $slug,
        ?Category $parent = null
    ): Category {
        return Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMemory(Tenant $tenant, User $owner, array $attributes): Memory
    {
        return Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'body' => 'テスト用の記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
            ...$attributes,
        ]);
    }
}
