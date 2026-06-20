<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tenant;
use App\Models\TenantMemberInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicIdBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_models_generate_prefixed_public_ids(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'name' => '学校',
            'slug' => 'school',
        ]);
        $memory = Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'category_id' => $category->id,
            'body' => '放課後の教室で友達と話した。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this->assertPublicId('ten', $tenant->public_id);
        $this->assertPublicId('usr', $user->public_id);
        $this->assertPublicId('cat', $category->public_id);
        $this->assertPublicId('mem', $memory->public_id);

        $this->assertSame(1, Tenant::query()->where('public_id', $tenant->public_id)->count());
        $this->assertSame(1, User::query()->where('public_id', $user->public_id)->count());
        $this->assertSame(1, Category::query()->where('public_id', $category->public_id)->count());
        $this->assertSame(1, Memory::query()->where('public_id', $memory->public_id)->count());
    }

    public function test_tenant_member_invitations_generate_prefixed_public_ids(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $invitation = TenantMemberInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'invited_by_user_id' => $user->id,
            'email' => 'invitee@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'invite-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertPublicId('inv', $invitation->public_id);
        $this->assertSame(1, TenantMemberInvitation::query()->where('public_id', $invitation->public_id)->count());
    }

    public function test_public_ids_are_exposed_on_core_api_payloads(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $parent = Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'name' => '学校',
            'slug' => 'school',
            'sort_order' => 10,
        ]);
        $child = Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'parent_id' => $parent->id,
            'name' => '部活',
            'slug' => 'club',
            'sort_order' => 20,
        ]);
        $memory = Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'category_id' => $child->id,
            'title' => '最後の大会前夜',
            'body' => '部室で遅くまで道具を片付けた。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.public_id', $user->public_id)
            ->assertJsonPath('data.tenant.id', $tenant->id)
            ->assertJsonPath('data.tenant.public_id', $tenant->public_id);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/tenant/members')
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.public_id', $user->public_id);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/categories?tree=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $parent->id)
            ->assertJsonPath('data.0.public_id', $parent->public_id)
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonPath('data.0.children.0.public_id', $child->public_id)
            ->assertJsonPath('data.0.children.0.parent_public_id', $parent->public_id);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $memory->id)
            ->assertJsonPath('data.0.public_id', $memory->public_id)
            ->assertJsonPath('data.0.category.id', $child->id)
            ->assertJsonPath('data.0.category.public_id', $child->public_id);

        $this
            ->withApiToken($user)
            ->getJson('/api/v1/memory-space')
            ->assertOk()
            ->assertJsonPath('data.categories.0.public_id', $parent->public_id)
            ->assertJsonPath('data.categories.0.children.0.public_id', $child->public_id)
            ->assertJsonPath('data.categories.0.children.0.parent_public_id', $parent->public_id)
            ->assertJsonPath('data.memories.0.public_id', $memory->public_id)
            ->assertJsonPath('data.memories.0.category_public_id', $child->public_id);
    }

    public function test_public_id_migration_backfills_existing_core_rows(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'name' => '学校',
            'slug' => 'school',
        ]);
        Memory::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'category_id' => $category->id,
            'body' => '移行前からある記憶。',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);

        $migration = require database_path('migrations/2026_05_14_130500_add_public_ids_to_core_tables.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('memories', 'public_id'));
        $this->assertSame(1, DB::table('memories')->count());

        $migration->up();

        $this->assertPublicId('ten', DB::table('tenants')->value('public_id'));
        $this->assertPublicId('usr', DB::table('users')->value('public_id'));
        $this->assertPublicId('cat', DB::table('categories')->value('public_id'));
        $this->assertPublicId('mem', DB::table('memories')->value('public_id'));
    }

    public function test_public_id_migration_backfills_existing_tenant_member_invitation_rows(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        TenantMemberInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'invited_by_user_id' => $user->id,
            'email' => 'invitee@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'invite-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $migration = require database_path('migrations/2026_05_14_210300_add_public_id_to_tenant_member_invitations_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('tenant_member_invitations', 'public_id'));
        $this->assertSame(1, DB::table('tenant_member_invitations')->count());

        $migration->up();

        $this->assertPublicId('inv', DB::table('tenant_member_invitations')->value('public_id'));
    }

    private function assertPublicId(string $prefix, mixed $publicId): void
    {
        $this->assertIsString($publicId);
        $this->assertMatchesRegularExpression('/^'.$prefix.'_[0-9A-HJKMNP-TV-Z]{26}$/', $publicId);
    }
}
