<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_memory_inside_request_context_including_secret_visibility(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $oldCategory = $this->createCategory($tenant, $user, '学校', 'school');
        $newCategory = $this->createCategory($tenant, $user, '人間関係', 'relationships');
        $oldTag = $this->createTag($tenant, '古いタグ', '古いタグ');

        $this->createTag($otherTenant, '友達', '友達');

        $memory = $this->createMemory($tenant, $user, [
            'category_id' => $oldCategory->id,
            'period_key' => 'high_school',
            'occurred_on' => '2010-07-15',
            'title' => '更新前',
            'body' => '更新前の本文。',
            'emotion_label' => '普通',
            'emotion_intensity' => 2,
            'visibility' => Memory::VISIBILITY_SECRET,
            'metadata' => ['client' => 'before'],
        ]);
        $memory->tags()->attach($oldTag);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/memories/{$memory->id}", [
                'period_key' => 'university',
                'occurred_on' => '2017-02-14',
                'title' => ' 失恋の日 ',
                'body' => ' 長く付き合っていた人と別れた。 ',
                'emotion_label' => ' 悲しい ',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'category_id' => $newCategory->id,
                'tags' => ['ともだち', '友人', 'なつ'],
                'metadata' => ['client' => 'admin-edit'],
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $memory->id)
            ->assertJsonPath('data.period_key', 'university')
            ->assertJsonPath('data.occurred_on', '2017-02-14')
            ->assertJsonPath('data.title', '失恋の日')
            ->assertJsonPath('data.body', '長く付き合っていた人と別れた。')
            ->assertJsonPath('data.emotion_label', '悲しい')
            ->assertJsonPath('data.emotion_intensity', 5)
            ->assertJsonPath('data.visibility', Memory::VISIBILITY_PRIVATE)
            ->assertJsonPath('data.category.id', $newCategory->id)
            ->assertJsonPath('data.category.name', '人間関係')
            ->assertJsonPath('data.tags', ['友達', '夏']);

        $memory->refresh();

        $this->assertSame($newCategory->id, $memory->category_id);
        $this->assertSame(['client' => 'admin-edit'], $memory->metadata);
        $this->assertSame(
            ['友達', '夏'],
            $memory->tags()->orderBy('normalized_name')->pluck('normalized_name')->all(),
        );
        $this->assertSame(1, Tag::query()->where('tenant_id', $tenant->id)->where('normalized_name', '友達')->count());
        $this->assertSame(1, Tag::query()->where('tenant_id', $otherTenant->id)->where('normalized_name', '友達')->count());
    }

    public function test_update_memory_can_clear_category_and_tags_when_fields_are_explicitly_provided(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '学校', 'school');
        $tag = $this->createTag($tenant, '友達', '友達');
        $memory = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'body' => 'カテゴリとタグを消す記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $memory->tags()->attach($tag);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/memories/{$memory->id}", [
                'category_id' => null,
                'tags' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.tags', []);

        $memory->refresh();

        $this->assertNull($memory->category_id);
        $this->assertSame(0, $memory->tags()->count());
    }

    public function test_update_memory_validates_payload_shape_and_category_boundary(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $outsideCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $memory = $this->createMemory($tenant, $user, [
            'body' => '更新されない本文。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this
            ->withApiToken($user)
            ->patchJson("/api/v1/memories/{$memory->id}", [
                'body' => '   ',
                'period_key' => 'future',
                'occurred_on' => '2010/07/15',
                'emotion_label' => str_repeat('あ', 41),
                'emotion_intensity' => 6,
                'visibility' => 'public',
                'category_id' => $outsideCategory->id,
                'tags' => array_fill(0, 21, 'tag'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'body',
                'period_key',
                'occurred_on',
                'emotion_label',
                'emotion_intensity',
                'visibility',
                'category_id',
                'tags',
            ]);

        $this->assertSame('更新されない本文。', $memory->refresh()->body);
    }

    public function test_update_memory_stays_inside_request_context(): void
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
                ->patchJson("/api/v1/memories/{$outsideMemory->id}", [
                    'body' => '境界外更新',
                ])
                ->assertNotFound();

            $this->assertNotSame('境界外更新', $outsideMemory->refresh()->body);
        }
    }

    public function test_update_memory_requires_authentication(): void
    {
        $this
            ->patchJson('/api/v1/memories/1', [
                'body' => 'ログインなしでは更新できない記憶。',
            ])
            ->assertUnauthorized();
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
