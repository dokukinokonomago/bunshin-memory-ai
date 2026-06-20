<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantRoleBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_defaults_to_member_and_member_cannot_manage_tenant_members(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);

        $member = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Member User',
            'email' => 'member@example.test',
            'password' => 'password',
        ]);
        $member->refresh();

        $this->assertSame(User::ROLE_MEMBER, $member->role);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $member->account_status);
        $this->assertFalse($member->isTenantOwner());
        $this->assertFalse($member->isTenantAdmin());
        $this->assertFalse($member->canManageTenantMembers());
        $this->assertFalse(Gate::forUser($member)->allows('manage-tenant-members', $tenant));
    }

    public function test_owner_and_admin_can_manage_members_only_inside_their_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '分身AI',
            'slug' => 'bunshin-ai',
        ]);
        $otherTenant = Tenant::query()->create([
            'name' => 'Other',
            'slug' => 'other',
        ]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertTrue($owner->isTenantOwner());
        $this->assertTrue($owner->canManageTenantMembers());
        $this->assertTrue($admin->isTenantAdmin());
        $this->assertTrue($admin->canManageTenantMembers());

        $this->assertTrue(Gate::forUser($owner)->allows('manage-tenant-members', $tenant));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-tenant-members', $tenant));
        $this->assertFalse(Gate::forUser($owner)->allows('manage-tenant-members', $otherTenant));
        $this->assertFalse(Gate::forUser($admin)->allows('manage-tenant-members', $otherTenant));
    }
}
