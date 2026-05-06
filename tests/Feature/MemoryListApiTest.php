<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_default_memories_inside_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $category = $this->createCategory($tenant, $user, '学校', 'school');
        $friend = $this->createTag($tenant, '友達', '友達');

        $shared = $this->createMemory($tenant, $user, [
            'body' => '共有できる大学の記憶。',
            'visibility' => Memory::VISIBILITY_SHARED,
        ]);
        $private = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'period_key' => 'high_school',
            'occurred_on' => '2010-07-15',
            'title' => '放課後の教室',
            'body' => '放課後の教室で友達と話した。',
            'emotion_label' => '普通',
            'emotion_intensity' => 3,
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $private->tags()->attach($friend);
        $secret = $this->createMemory($tenant, $user, [
            'body' => '通常 list には出さない秘匿記憶。',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);
        $sameTenantOtherOwnerMemory = $this->createMemory($tenant, $sameTenantOtherUser, [
            'body' => '同一 tenant の別 owner 記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $otherTenantMemory = $this->createMemory($otherTenant, $otherTenantUser, [
            'body' => '別 tenant の記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $private->id)
            ->assertJsonPath('data.0.period_key', 'high_school')
            ->assertJsonPath('data.0.occurred_on', '2010-07-15')
            ->assertJsonPath('data.0.title', '放課後の教室')
            ->assertJsonPath('data.0.body', '放課後の教室で友達と話した。')
            ->assertJsonPath('data.0.emotion_label', '普通')
            ->assertJsonPath('data.0.emotion_intensity', 3)
            ->assertJsonPath('data.0.visibility', Memory::VISIBILITY_PRIVATE)
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.category.name', '学校')
            ->assertJsonPath('data.0.tags', ['友達'])
            ->assertJsonPath('data.1.id', $shared->id)
            ->assertJsonMissing(['id' => $secret->id])
            ->assertJsonMissing(['id' => $sameTenantOtherOwnerMemory->id])
            ->assertJsonMissing(['id' => $otherTenantMemory->id]);
    }

    public function test_memory_list_can_explicitly_request_secret_visibility_inside_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $private = $this->createMemory($tenant, $user, [
            'body' => '通常の記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $secret = $this->createMemory($tenant, $user, [
            'body' => '明示要求でだけ返す秘匿記憶。',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?visibility=secret')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $secret->id)
            ->assertJsonPath('data.0.visibility', Memory::VISIBILITY_SECRET)
            ->assertJsonMissing(['id' => $private->id]);
    }

    public function test_memory_list_supports_mockup_filters(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $school = $this->createCategory($tenant, $user, '学校', 'school');
        $family = $this->createCategory($tenant, $user, '家族', 'family');
        $club = $this->createTag($tenant, '部活', '部活');

        $matched = $this->createMemory($tenant, $user, [
            'category_id' => $school->id,
            'period_key' => 'high_school',
            'title' => '文化祭の準備',
            'body' => 'クラスメートと遅くまで準備をした。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $matched->tags()->attach($club);
        $this->createMemory($tenant, $user, [
            'category_id' => $family->id,
            'period_key' => 'high_school',
            'title' => '家族旅行',
            'body' => '夏の旅行。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $this->createMemory($tenant, $user, [
            'category_id' => $school->id,
            'period_key' => 'university',
            'title' => '入学式',
            'body' => '大学の入学式。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?'.http_build_query([
                'period_key' => 'high_school',
                'category_id' => $school->id,
                'q' => '部活',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->id);
    }

    public function test_memory_list_can_include_descendant_categories_inside_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $school = $this->createCategory($tenant, $user, '学校', 'school');
        $club = $this->createCategory($tenant, $user, '部活', 'club', $school);
        $classroom = $this->createCategory($tenant, $user, '教室', 'classroom', $school);
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
        $classroomMemory = $this->createMemory($tenant, $user, [
            'category_id' => $classroom->id,
            'title' => '教室',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $secretClubMemory = $this->createMemory($tenant, $user, [
            'category_id' => $club->id,
            'title' => '秘匿部活',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);
        $familyMemory = $this->createMemory($tenant, $user, [
            'category_id' => $family->id,
            'title' => '家族',
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

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?'.http_build_query([
                'category_id' => $school->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $schoolMemory->id);

        $response = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?'.http_build_query([
                'category_id' => $school->id,
                'include_descendants' => 'true',
            ]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['id' => $secretClubMemory->id])
            ->assertJsonMissing(['id' => $familyMemory->id])
            ->assertJsonMissing(['id' => $sameTenantOtherOwnerMemory->id])
            ->assertJsonMissing(['id' => $otherTenantMemory->id]);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([
            $schoolMemory->id,
            $clubMemory->id,
            $classroomMemory->id,
        ], $ids);

        $secretResponse = $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?'.http_build_query([
                'category_id' => $school->id,
                'include_descendants' => 1,
                'visibility' => Memory::VISIBILITY_SECRET,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($secretClubMemory->id, $secretResponse->json('data.0.id'));
    }

    public function test_memory_list_descendant_filter_does_not_cross_request_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherUser, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantUser, '旅行', 'travel');
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
            $this
                ->withApiToken($user)
                ->getJson('/api/v1/memories?'.http_build_query([
                    'category_id' => $outsideCategory->id,
                    'include_descendants' => 1,
                ]))
                ->assertOk()
                ->assertJsonCount(0, 'data')
                ->assertJsonMissing(['id' => $sameTenantOtherOwnerMemory->id])
                ->assertJsonMissing(['id' => $otherTenantMemory->id]);
        }
    }

    public function test_memory_list_validates_filter_shape(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?period_key=future&visibility=public&category_id=0&include_descendants=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_key', 'visibility', 'category_id', 'include_descendants']);
    }

    public function test_memory_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/memories')->assertUnauthorized();
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
