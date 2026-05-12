<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateMemoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_memory_inside_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '学校', 'school');

        $response = $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'period_key' => 'high_school',
                'occurred_on' => '2010-07-15',
                'title' => ' 放課後の教室 ',
                'body' => ' 放課後の教室で友達と話した。 ',
                'emotion_label' => '普通',
                'emotion_intensity' => 3,
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'category_id' => $category->id,
                'tags' => [' 放課後 ', '友達'],
                'metadata' => ['client' => 'admin-mock'],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.period_key', 'high_school')
            ->assertJsonPath('data.occurred_on', '2010-07-15')
            ->assertJsonPath('data.title', '放課後の教室')
            ->assertJsonPath('data.body', '放課後の教室で友達と話した。')
            ->assertJsonPath('data.emotion_label', '普通')
            ->assertJsonPath('data.emotion_intensity', 3)
            ->assertJsonPath('data.visibility', Memory::VISIBILITY_PRIVATE)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', '学校')
            ->assertJsonPath('data.tags', ['放課後', '友達']);

        $memory = Memory::query()->firstOrFail();

        $this->assertSame($tenant->id, $memory->tenant_id);
        $this->assertSame($user->id, $memory->owner_user_id);
        $this->assertSame($category->id, $memory->category_id);
        $this->assertSame(['client' => 'admin-mock'], $memory->metadata);
        $this->assertSame(['友達', '放課後'], Tag::query()->orderBy('name')->pluck('name')->all());
        $this->assertCount(2, $memory->tags);
    }

    public function test_create_memory_requires_authentication(): void
    {
        $this
            ->postJson('/api/v1/memories', [
                'body' => 'ログインなしでは作成できない記憶。',
                'visibility' => Memory::VISIBILITY_PRIVATE,
            ])
            ->assertUnauthorized();
    }

    public function test_create_memory_validates_initial_payload_shape(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'body' => '   ',
                'period_key' => 'future',
                'occurred_on' => '2010/07/15',
                'emotion_label' => str_repeat('あ', 41),
                'emotion_intensity' => 6,
                'visibility' => 'public',
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
                'tags',
            ]);
    }

    public function test_create_memory_rejects_categories_outside_request_owner_boundary(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');

        foreach ([$sameTenantOtherOwnerCategory, $otherTenantCategory] as $category) {
            $this
                ->withApiToken($user)
                ->postJson('/api/v1/memories', [
                    'body' => '境界外 category では作成できない記憶。',
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'category_id' => $category->id,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['category_id']);
        }

        $this->assertDatabaseCount('memories', 0);
    }

    public function test_create_memory_deduplicates_tags_after_normalization(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'body' => '表記ゆれを含むタグ付きの記憶。',
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'tags' => ['友達', ' ともだち ', '友人', '夏', 'なつ'],
            ]);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'data.tags');

        $memory = Memory::query()->firstOrFail();

        $this->assertDatabaseCount('tags', 2);
        $this->assertDatabaseHas('tags', [
            'tenant_id' => $tenant->id,
            'name' => '友達',
            'normalized_name' => '友達',
        ]);
        $this->assertDatabaseHas('tags', [
            'tenant_id' => $tenant->id,
            'name' => '夏',
            'normalized_name' => '夏',
        ]);
        $this->assertSame(
            ['友達', '夏'],
            $memory->tags()->orderBy('normalized_name')->pluck('normalized_name')->all(),
        );
    }

    public function test_tag_normalization_does_not_cross_tenant_boundary(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Tag::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => '友達',
            'normalized_name' => '友達',
        ]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/memories', [
                'body' => '別 tenant とは tag を共有しない記憶。',
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'tags' => ['ともだち'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.tags', ['友達']);

        $this->assertDatabaseCount('tags', 2);
        $this->assertSame(1, Tag::query()->where('tenant_id', $tenant->id)->where('normalized_name', '友達')->count());
        $this->assertSame(1, Tag::query()->where('tenant_id', $otherTenant->id)->where('normalized_name', '友達')->count());
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
}
