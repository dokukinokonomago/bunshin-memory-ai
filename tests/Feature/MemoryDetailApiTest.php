<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryDetailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_show_memory_inside_request_context_including_secret_visibility(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '人間関係', 'relationships');
        $tag = $this->createTag($tenant, '恋愛', '恋愛');

        $memory = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'period_key' => 'university',
            'occurred_on' => '2017-02-14',
            'title' => '失恋の日',
            'body' => '長く付き合っていた人と別れた。',
            'emotion_label' => '悲しい',
            'emotion_intensity' => 5,
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);
        $memory->tags()->attach($tag);

        $this
            ->withApiToken($user)
            ->getJson("/api/v1/memories/{$memory->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $memory->id)
            ->assertJsonPath('data.period_key', 'university')
            ->assertJsonPath('data.occurred_on', '2017-02-14')
            ->assertJsonPath('data.title', '失恋の日')
            ->assertJsonPath('data.body', '長く付き合っていた人と別れた。')
            ->assertJsonPath('data.emotion_label', '悲しい')
            ->assertJsonPath('data.emotion_intensity', 5)
            ->assertJsonPath('data.visibility', Memory::VISIBILITY_SECRET)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', '人間関係')
            ->assertJsonPath('data.tags', ['恋愛']);
    }

    public function test_memory_detail_stays_inside_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $sameTenantOtherOwnerMemory = $this->createMemory($tenant, $sameTenantOtherUser, [
            'body' => '同一 tenant の別 owner 記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $otherTenantMemory = $this->createMemory($otherTenant, $otherTenantUser, [
            'body' => '別 tenant の記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        foreach ([$sameTenantOtherOwnerMemory, $otherTenantMemory] as $outsideMemory) {
            $this
                ->withApiToken($user)
                ->getJson("/api/v1/memories/{$outsideMemory->id}")
                ->assertNotFound();
        }
    }

    public function test_memory_detail_requires_authentication(): void
    {
        $this->getJson('/api/v1/memories/1')->assertUnauthorized();
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createCategory(Tenant $tenant, User $owner, string $name, string $slug): Category
    {
        return Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createTag(Tenant $tenant, string $name, string $normalizedName): Tag
    {
        return Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'normalized_name' => $normalizedName,
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
