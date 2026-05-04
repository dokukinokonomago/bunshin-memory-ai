<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryDeleteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_memory_inside_request_context_including_secret_visibility(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $tag = $this->createTag($tenant, '恋愛', '恋愛');
        $memory = $this->createMemory($tenant, $user, [
            'body' => '削除対象の秘匿記憶。',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);
        $memory->tags()->attach($tag);

        $this
            ->withApiToken($user)
            ->deleteJson("/api/v1/memories/{$memory->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('memories', [
            'id' => $memory->id,
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('memory_tag', [
            'memory_id' => $memory->id,
            'tag_id' => $tag->id,
        ]);

        $this
            ->withApiToken($user)
            ->getJson("/api/v1/memories/{$memory->id}")
            ->assertNotFound();

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories?visibility=secret')
            ->assertOk()
            ->assertJsonMissing(['id' => $memory->id]);
    }

    public function test_delete_memory_stays_inside_request_context(): void
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
                ->deleteJson("/api/v1/memories/{$outsideMemory->id}")
                ->assertNotFound();

            $this->assertNull($outsideMemory->refresh()->deleted_at);
        }
    }

    public function test_delete_memory_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/memories/1')->assertUnauthorized();
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
