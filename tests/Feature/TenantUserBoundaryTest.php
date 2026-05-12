<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantUserBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_context_requires_user_tenant_id(): void
    {
        $user = User::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tenant_id');

        TenantUserContext::fromUser($user);
    }

    public function test_request_context_exposes_request_user_and_tenant(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $context = TenantUserContext::fromUser($user);

        $this->assertTrue($context->tenant()->is($tenant));
        $this->assertTrue($context->user()->is($user));
        $this->assertSame($tenant->id, $context->tenantId());
        $this->assertSame($user->id, $context->userId());
    }

    public function test_memory_and_category_context_queries_stay_inside_request_tenant_and_owner(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherOwner = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantOwner = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $context = TenantUserContext::fromUser($owner);

        $ownMemory = $this->createMemory($tenant, $owner, '自分の記憶');
        $sameTenantOtherOwnerMemory = $this->createMemory($tenant, $sameTenantOtherOwner, '同一テナントの別 owner 記憶');
        $otherTenantMemory = $this->createMemory($otherTenant, $otherTenantOwner, '別テナントの記憶');

        $ownCategory = $this->createCategory($tenant, $owner, '学校', 'school');
        $sameTenantOtherOwnerCategory = $this->createCategory($tenant, $sameTenantOtherOwner, '仕事', 'work');
        $otherTenantCategory = $this->createCategory($otherTenant, $otherTenantOwner, '旅行', 'travel');

        $memoryIds = Memory::query()
            ->forContext($context)
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $categoryIds = Category::query()
            ->forContext($context)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$ownMemory->id], $memoryIds);
        $this->assertSame([$ownCategory->id], $categoryIds);
        $this->assertSame($ownMemory->id, Memory::findForContext($context, $ownMemory->id)?->id);
        $this->assertNull(Memory::findForContext($context, $sameTenantOtherOwnerMemory->id));
        $this->assertNull(Memory::findForContext($context, $otherTenantMemory->id));
        $this->assertSame($ownCategory->id, Category::findForContext($context, $ownCategory->id)?->id);
        $this->assertNull(Category::findForContext($context, $sameTenantOtherOwnerCategory->id));
        $this->assertNull(Category::findForContext($context, $otherTenantCategory->id));
    }

    public function test_tag_context_queries_stay_inside_request_tenant(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $context = TenantUserContext::fromUser($owner);

        $tenantTag = Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '放課後',
            'normalized_name' => '放課後',
        ]);
        $otherTenantTag = Tag::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => '秘密',
            'normalized_name' => '秘密',
        ]);

        $tagIds = Tag::query()
            ->forContext($context)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$tenantTag->id], $tagIds);
        $this->assertSame($tenantTag->id, Tag::findForContext($context, $tenantTag->id)?->id);
        $this->assertNull(Tag::findForContext($context, $otherTenantTag->id));
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createMemory(Tenant $tenant, User $owner, string $body): Memory
    {
        return Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'body' => $body,
            'visibility' => Memory::VISIBILITY_PRIVATE,
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
