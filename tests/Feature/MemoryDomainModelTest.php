<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_memory_domain_schema_and_relationships_are_available(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'name' => '学校',
            'slug' => 'school',
            'sort_order' => 10,
        ]);

        $memory = Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'category_id' => $category->id,
            'period_key' => 'high_school',
            'occurred_on' => '2026-05-04',
            'title' => '放課後の教室',
            'body' => '放課後の教室で友達と話した。',
            'emotion_label' => '普通',
            'emotion_intensity' => 3,
            'visibility' => Memory::VISIBILITY_PRIVATE,
            'source' => 'manual',
            'metadata' => ['imported' => false],
        ]);

        $tag = Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '放課後',
            'normalized_name' => '放課後',
        ]);

        $memory->tags()->attach($tag);
        $memory->refresh();

        $this->assertTrue($memory->tenant->is($tenant));
        $this->assertTrue($memory->owner->is($owner));
        $this->assertTrue($memory->category->is($category));
        $this->assertTrue($memory->tags->first()->is($tag));
        $this->assertSame(['imported' => false], $memory->metadata);
        $this->assertSame(3, $memory->emotion_intensity);
    }

    public function test_memory_scopes_filter_by_tenant_owner_and_default_visibility(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $otherTenant = Tenant::query()->create([
            'name' => '別テナント',
            'slug' => 'other-tenant',
        ]);

        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherOwner = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $visibleMemory = Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'body' => '通常 list に出る記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'body' => '通常 list から除外する秘匿記憶。',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);

        Memory::query()->create([
            'tenant_id' => $otherTenant->id,
            'owner_user_id' => $otherOwner->id,
            'body' => '別テナントの記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $visibleIds = Memory::query()
            ->forTenant($tenant)
            ->forOwner($owner)
            ->visibleByDefault()
            ->pluck('id')
            ->all();

        $this->assertSame([$visibleMemory->id], $visibleIds);
    }
}
