<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemorySpaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_memory_space_read_model(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $music = $this->createCategory($tenant, $user, '音楽', 'music', 1);
        $mrchildren = $this->createCategory($tenant, $user, 'Mr.Children', 'mrchildren', 1, $music);
        $tag = $this->createTag($tenant, '青春', '青春');

        $visible = $this->createMemory($tenant, $user, [
            'category_id' => $mrchildren->id,
            'period_key' => 'high_school',
            'occurred_on' => '2001-05-01',
            'title' => 'Tomorrow never knowsを初めて聴いた日',
            'body' => '高校の帰り道、友達が貸してくれたMDで聴いた。',
            'emotion_label' => '感動',
            'emotion_intensity' => 5,
            'visibility' => Memory::VISIBILITY_PRIVATE,
            'metadata' => [
                'emotion_scores' => [
                    '感動' => 92,
                    '懐かしさ' => '88',
                    'invalid' => 'high',
                ],
                'importance_score' => '0.95',
                'beliefs' => ['音楽は人生を変える', 100],
                'chains' => ['音楽だけが救い'],
            ],
        ]);
        $visible->tags()->attach($tag);

        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $mrchildren->id,
            'title' => '秘匿記憶',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);
        $sameTenantOtherOwnerMemory = $this->createMemory($tenant, $sameTenantOtherUser, [
            'title' => '別 owner 記憶',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $otherTenantMemory = $this->createMemory($otherTenant, $otherTenantUser, [
            'title' => '別 tenant 記憶',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $response = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memory-space')
            ->assertOk()
            ->assertJsonPath('data.categories.0.id', $music->id)
            ->assertJsonPath('data.categories.0.parent_id', null)
            ->assertJsonPath('data.categories.0.name', '音楽')
            ->assertJsonPath('data.categories.0.memory_count', 1)
            ->assertJsonPath('data.categories.0.locked_secret_count', 1)
            ->assertJsonCount(1, 'data.categories.0.children')
            ->assertJsonPath('data.categories.0.children.0.id', $mrchildren->id)
            ->assertJsonPath('data.categories.0.children.0.parent_id', $music->id)
            ->assertJsonPath('data.categories.0.children.0.memory_count', 1)
            ->assertJsonPath('data.categories.0.children.0.locked_secret_count', 1)
            ->assertJsonPath('data.secret.locked', true)
            ->assertJsonPath('data.secret.locked_count', 1)
            ->assertJsonPath('data.secret.unlock_expires_at', null)
            ->assertJsonFragment(['key' => 'high_school', 'label' => '高校']);

        $memories = $response->json('data.memories');

        $this->assertCount(1, $memories);
        $this->assertSame($visible->id, $memories[0]['id']);
        $this->assertSame($mrchildren->id, $memories[0]['category_id']);
        $this->assertSame('high_school', $memories[0]['period_key']);
        $this->assertSame('2001-05-01', $memories[0]['occurred_on']);
        $this->assertSame('Tomorrow never knowsを初めて聴いた日', $memories[0]['title']);
        $this->assertSame('高校の帰り道、友達が貸してくれたMDで聴いた。', $memories[0]['body']);
        $this->assertSame('感動', $memories[0]['emotion_label']);
        $this->assertSame(5, $memories[0]['emotion_intensity']);
        $this->assertSame(['感動' => 92, '懐かしさ' => 88], $memories[0]['emotion_scores']);
        $this->assertSame(0.95, $memories[0]['importance_score']);
        $this->assertSame(['音楽は人生を変える'], $memories[0]['beliefs']);
        $this->assertSame(['音楽だけが救い'], $memories[0]['chains']);
        $this->assertSame(['青春'], $memories[0]['tags']);
        $this->assertSame(Memory::VISIBILITY_PRIVATE, $memories[0]['visibility']);

        $memoryIds = collect($memories)->pluck('id')->all();

        $this->assertNotContains($secret->id, $memoryIds);
        $this->assertNotContains($sameTenantOtherOwnerMemory->id, $memoryIds);
        $this->assertNotContains($otherTenantMemory->id, $memoryIds);
    }

    public function test_memory_space_filters_by_period_and_descendant_categories_by_default(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $school = $this->createCategory($tenant, $user, '学校', 'school');
        $club = $this->createCategory($tenant, $user, '部活', 'club', parent: $school);
        $family = $this->createCategory($tenant, $user, '家族', 'family');

        $schoolMemory = $this->createMemory($tenant, $user, [
            'category_id' => $school->id,
            'period_key' => 'high_school',
            'title' => '学校 root',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $clubMemory = $this->createMemory($tenant, $user, [
            'category_id' => $club->id,
            'period_key' => 'high_school',
            'title' => '部活',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $universityClubMemory = $this->createMemory($tenant, $user, [
            'category_id' => $club->id,
            'period_key' => 'university',
            'title' => '大学の部活',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $familyMemory = $this->createMemory($tenant, $user, [
            'category_id' => $family->id,
            'period_key' => 'high_school',
            'title' => '家族',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $response = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memory-space?'.http_build_query([
                'period_key' => 'high_school',
                'category_id' => $school->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.categories.0.id', $school->id)
            ->assertJsonPath('data.categories.0.memory_count', 2);

        $this->assertEqualsCanonicalizing(
            [$schoolMemory->id, $clubMemory->id],
            collect($response->json('data.memories'))->pluck('id')->all(),
        );

        $this->assertNotContains(
            $universityClubMemory->id,
            collect($response->json('data.memories'))->pluck('id')->all(),
        );
        $this->assertNotContains(
            $familyMemory->id,
            collect($response->json('data.memories'))->pluck('id')->all(),
        );

        $rootOnlyResponse = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memory-space?'.http_build_query([
                'category_id' => $school->id,
                'include_descendants' => 0,
            ]))
            ->assertOk();

        $this->assertSame(
            [$schoolMemory->id],
            collect($rootOnlyResponse->json('data.memories'))->pluck('id')->all(),
        );
    }

    public function test_memory_space_does_not_expose_secret_memories_even_when_requested_before_unlock(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '家族', 'family');

        $visible = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '通常記憶',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => 'secret title must not leak',
            'body' => 'secret body must not leak',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);

        $response = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memory-space?include_secret=1')
            ->assertOk()
            ->assertJsonPath('data.secret.locked', true)
            ->assertJsonPath('data.secret.locked_count', 1);

        $memories = $response->json('data.memories');

        $this->assertSame([$visible->id], collect($memories)->pluck('id')->all());
        $this->assertNotContains($secret->id, collect($memories)->pluck('id')->all());
        $this->assertStringNotContainsString('secret title must not leak', $response->getContent());
        $this->assertStringNotContainsString('secret body must not leak', $response->getContent());
    }

    public function test_memory_space_category_filter_does_not_cross_request_context_and_validates_shape(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $ownCategory = $this->createCategory($tenant, $user, '学校', 'school');
        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');
        $ownMemory = $this->createMemory($tenant, $user, [
            'category_id' => $ownCategory->id,
            'title' => '自分の記憶',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $sameTenantOtherOwnerMemory = $this->createMemory($tenant, $sameTenantOtherUser, [
            'category_id' => $sameTenantOtherOwnerCategory->id,
            'title' => '別 owner',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $otherTenantMemory = $this->createMemory($otherTenant, $otherTenantUser, [
            'category_id' => $otherTenantCategory->id,
            'title' => '別 tenant',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        foreach ([$sameTenantOtherOwnerCategory, $otherTenantCategory] as $outsideCategory) {
            $response = $this
                ->withApiToken($user)
                ->getJson('/api/v1/memory-space?'.http_build_query([
                    'category_id' => $outsideCategory->id,
                    'include_descendants' => 1,
                ]))
                ->assertOk()
                ->assertJsonPath('data.secret.locked_count', 0);

            $memoryIds = collect($response->json('data.memories'))->pluck('id')->all();

            $this->assertNotContains($ownMemory->id, $memoryIds);
            $this->assertNotContains($sameTenantOtherOwnerMemory->id, $memoryIds);
            $this->assertNotContains($otherTenantMemory->id, $memoryIds);
        }

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memory-space?period_key=future&category_id=0&include_descendants=maybe&include_secret=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_key', 'category_id', 'include_descendants', 'include_secret']);
    }

    public function test_memory_space_requires_authentication(): void
    {
        $this->getJson('/api/v1/memory-space')->assertUnauthorized();
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
        int $sortOrder = 0,
        ?Category $parent = null
    ): Category {
        return Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
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
