<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_tags_inside_request_tenant_context(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $otherTenant = $this->createTenant('別テナント', 'other-tenant');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sameTenantOtherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $friend = $this->createTag($tenant, '友達', '友達');
        $summer = $this->createTag($tenant, '夏', '夏');
        $work = $this->createTag($tenant, '仕事', '仕事');
        $otherTenantTag = $this->createTag($otherTenant, '旅行', '旅行');

        $this->createMemory($tenant, $user, '放課後の教室')->tags()->attach($friend);
        $this->createMemory($tenant, $user, '卒業式')->tags()->attach($friend);
        $this->createMemory($tenant, $user, '夏休み')->tags()->attach($summer);
        $this->createMemory($tenant, $sameTenantOtherUser, '転職初日')->tags()->attach($work);
        $this->createMemory($otherTenant, $otherTenantUser, '旅行の記憶')->tags()->attach($otherTenantTag);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $friend->id)
            ->assertJsonPath('data.0.name', '友達')
            ->assertJsonPath('data.0.normalized_name', '友達')
            ->assertJsonPath('data.0.usage_count', 2)
            ->assertJsonFragment([
                'id' => $summer->id,
                'name' => '夏',
                'normalized_name' => '夏',
                'usage_count' => 1,
            ])
            ->assertJsonFragment([
                'id' => $work->id,
                'name' => '仕事',
                'normalized_name' => '仕事',
                'usage_count' => 1,
            ])
            ->assertJsonMissing([
                'id' => $otherTenantTag->id,
                'name' => '旅行',
            ]);
    }

    public function test_tags_require_authentication(): void
    {
        $this->getJson('/api/v1/tags')->assertUnauthorized();
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
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

    private function createMemory(Tenant $tenant, User $owner, string $body): Memory
    {
        return Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'body' => $body,
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
    }
}
