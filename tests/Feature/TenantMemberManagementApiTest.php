<?php

namespace Tests\Feature;

use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\TenantMemberInvitation;
use App\Models\User;
use App\Notifications\TenantMemberInvitationNotification;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantMemberManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_member_and_invited_user_can_accept(): void
    {
        Notification::fake();

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');

        $response = $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => ' New.Member@Example.Test ',
                'role' => User::ROLE_MEMBER,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.member@example.test')
            ->assertJsonPath('data.role', User::ROLE_MEMBER)
            ->assertJsonPath('data.status', TenantMemberInvitation::STATUS_PENDING)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'public_id',
                    'email',
                    'role',
                    'status',
                    'invited_by_user_id',
                    'invited_by_user_public_id',
                    'accepted_user_id',
                    'accepted_user_public_id',
                    'expires_at',
                    'created_at',
                    'invite_token',
                ],
            ]);

        $inviteToken = $response->json('data.invite_token');
        [$invitationLookup, $plainToken] = explode('|', $inviteToken, 2);
        $this->assertPublicId('inv', $invitationLookup);

        $invitation = TenantMemberInvitation::query()->where('public_id', $invitationLookup)->firstOrFail();

        $this->assertSame($tenant->id, $invitation->tenant_id);
        $this->assertSame($owner->id, $invitation->invited_by_user_id);
        $this->assertSame('new.member@example.test', $invitation->email);
        $this->assertSame($invitation->public_id, $response->json('data.public_id'));
        $this->assertSame($owner->public_id, $response->json('data.invited_by_user_public_id'));
        $this->assertNull($response->json('data.accepted_user_public_id'));
        $this->assertNotSame($inviteToken, $invitation->token_hash);
        $this->assertTrue(hash_equals($invitation->token_hash, hash('sha256', $plainToken)));
        $this->assertNull($response->json('data.token_hash'));

        Notification::assertSentOnDemand(
            TenantMemberInvitationNotification::class,
            function (
                TenantMemberInvitationNotification $notification,
                array $channels,
                object $notifiable,
            ) use ($inviteToken, $tenant, $owner, $invitation): bool {
                $this->assertSame(['mail'], $channels);
                $this->assertSame('new.member@example.test', $notifiable->routes['mail']);
                $this->assertSame($tenant->name, $notification->tenantName());
                $this->assertSame($owner->name, $notification->inviterName());
                $this->assertSame(User::ROLE_MEMBER, $notification->role());
                $this->assertSame($inviteToken, $notification->inviteToken());
                $this->assertTrue($invitation->expires_at->equalTo($notification->expiresAt()));

                return true;
            },
        );

        $acceptResponse = $this
            ->postJson('/api/v1/tenant/invitations/accept', [
                'token' => $inviteToken,
                'name' => 'Invited Member',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'new.member@example.test')
            ->assertJsonPath('data.user.role', User::ROLE_MEMBER)
            ->assertJsonPath('data.user.account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->assertJsonPath('data.user.is_email_verified', false)
            ->assertJsonPath('data.user.email_verified_at', null)
            ->assertJsonPath('data.tenant.id', $tenant->id)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'user' => ['id'],
                    'tenant' => ['slug'],
                ],
            ]);

        $acceptedUser = User::query()->where('email', 'new.member@example.test')->firstOrFail();
        $invitation->refresh();

        $this->assertSame($tenant->id, $acceptedUser->tenant_id);
        $this->assertSame(User::ROLE_MEMBER, $acceptedUser->role);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $acceptedUser->account_status);
        $this->assertSame($acceptedUser->id, $invitation->accepted_user_id);
        $this->assertNotNull($invitation->accepted_at);
        Notification::assertSentTo($acceptedUser, VerifyEmailNotification::class);

        $this
            ->withHeader('Authorization', 'Bearer '.$acceptResponse->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'new.member@example.test')
            ->assertJsonPath('data.user.account_status', User::ACCOUNT_STATUS_ACTIVE);

        $this
            ->postJson('/api/v1/tenant/invitations/accept', [
                'token' => $inviteToken,
                'name' => 'Second Attempt',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_invitation_accept_keeps_legacy_numeric_tokens(): void
    {
        Notification::fake();

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $plainToken = 'legacy-plain-token';
        $invitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'legacy-invite@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        $this
            ->postJson('/api/v1/tenant/invitations/accept', [
                'token' => $invitation->id.'|'.$plainToken,
                'name' => 'Legacy Invite',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'legacy-invite@example.test')
            ->assertJsonPath('data.user.role', User::ROLE_MEMBER);

        $acceptedUser = User::query()->where('email', 'legacy-invite@example.test')->firstOrFail();
        $invitation->refresh();

        $this->assertSame($acceptedUser->id, $invitation->accepted_user_id);
        $this->assertNotNull($invitation->accepted_at);
        Notification::assertSentTo($acceptedUser, VerifyEmailNotification::class);
    }

    public function test_member_cannot_manage_members_and_other_tenant_records_are_hidden(): void
    {
        $tenant = $this->tenant();
        $otherTenant = $this->tenant('Other', 'other');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $otherOwner = $this->user($otherTenant, User::ROLE_OWNER, 'other-owner@example.test');

        $invitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'hidden@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'hidden-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this
            ->withApiToken($member)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'blocked@example.test',
                'role' => User::ROLE_MEMBER,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->withApiToken($otherOwner)
            ->deleteJson('/api/v1/tenant/invitations/'.$invitation->id)
            ->assertNotFound();

        $this
            ->withApiToken($otherOwner)
            ->patchJson('/api/v1/tenant/members/'.$member->id.'/role', [
                'role' => User::ROLE_ADMIN,
            ])
            ->assertNotFound();
    }

    public function test_manager_can_list_members_and_revoke_pending_invitations(): void
    {
        Notification::fake();

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $admin = $this->user($tenant, User::ROLE_ADMIN, 'admin@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');

        $inviteResponse = $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'pending@example.test',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertCreated();

        $invitationId = (int) $inviteResponse->json('data.id');
        $invitationPublicId = (string) $inviteResponse->json('data.public_id');
        $inviteToken = $inviteResponse->json('data.invite_token');

        $this
            ->withApiToken($admin)
            ->getJson('/api/v1/tenant/members')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $owner->id)
            ->assertJsonPath('data.1.id', $admin->id)
            ->assertJsonPath('data.2.id', $member->id);

        $this
            ->withApiToken($admin)
            ->getJson('/api/v1/tenant/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invitationId)
            ->assertJsonPath('data.0.public_id', $invitationPublicId)
            ->assertJsonPath('data.0.invited_by_user_public_id', $owner->public_id)
            ->assertJsonPath('data.0.status', TenantMemberInvitation::STATUS_PENDING);

        $this
            ->withApiToken($admin)
            ->deleteJson('/api/v1/tenant/invitations/'.$invitationPublicId)
            ->assertNoContent();

        $this->assertNotNull(TenantMemberInvitation::query()->findOrFail($invitationId)->revoked_at);

        $this
            ->postJson('/api/v1/tenant/invitations/accept', [
                'token' => $inviteToken,
                'name' => 'Revoked Invite',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_tenant_invitation_route_params_accept_public_ids_and_keep_numeric_compatibility(): void
    {
        $tenant = $this->tenant();
        $otherTenant = $this->tenant('Other', 'other');
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $otherOwner = $this->user($otherTenant, User::ROLE_OWNER, 'other-owner@example.test');

        $publicInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'public-revoke@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'public-revoke-token'),
            'expires_at' => now()->addDays(7),
        ]);
        $numericInvitation = $tenant->memberInvitations()->create([
            'invited_by_user_id' => $owner->id,
            'email' => 'numeric-revoke@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'numeric-revoke-token'),
            'expires_at' => now()->addDays(7),
        ]);
        $otherInvitation = $otherTenant->memberInvitations()->create([
            'invited_by_user_id' => $otherOwner->id,
            'email' => 'other-revoke@example.test',
            'role' => User::ROLE_MEMBER,
            'token_hash' => hash('sha256', 'other-revoke-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this
            ->withApiToken($owner)
            ->deleteJson('/api/v1/tenant/invitations/'.$publicInvitation->public_id)
            ->assertNoContent();

        $this->assertNotNull($publicInvitation->refresh()->revoked_at);

        $this
            ->withApiToken($owner)
            ->deleteJson('/api/v1/tenant/invitations/'.$numericInvitation->id)
            ->assertNoContent();

        $this->assertNotNull($numericInvitation->refresh()->revoked_at);

        foreach ([
            $otherInvitation->public_id,
            'usr_01HX0000000000000000000000',
            'inv_01hx0000000000000000000000',
            'inv_01HX0000000000000000000000',
        ] as $missingInvitation) {
            $this
                ->withApiToken($owner)
                ->deleteJson('/api/v1/tenant/invitations/'.$missingInvitation)
                ->assertNotFound();
        }
    }

    public function test_tenant_member_route_params_accept_public_ids_and_keep_numeric_compatibility(): void
    {
        $tenant = $this->tenant();
        $otherTenant = $this->tenant('Other', 'other');
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $numericMember = $this->user($tenant, User::ROLE_MEMBER, 'numeric@example.test');
        $revokedMember = $this->user($tenant, User::ROLE_MEMBER, 'revoked@example.test');
        $rotationMember = $this->user($tenant, User::ROLE_MEMBER, 'rotation@example.test');
        $otherMember = $this->user($otherTenant, User::ROLE_MEMBER, 'other-member@example.test');

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$member->public_id.'/role', [
                'role' => User::ROLE_ADMIN,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.public_id', $member->public_id)
            ->assertJsonPath('data.role', User::ROLE_ADMIN);

        $this->assertSame(User::ROLE_ADMIN, $member->refresh()->role);

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$numericMember->id.'/role', [
                'role' => User::ROLE_ADMIN,
            ])
            ->assertOk()
            ->assertJsonPath('data.public_id', $numericMember->public_id)
            ->assertJsonPath('data.role', User::ROLE_ADMIN);

        $this
            ->withApiToken($owner)
            ->deleteJson('/api/v1/tenant/members/'.$revokedMember->public_id)
            ->assertNoContent();

        $this->assertNull($revokedMember->refresh()->tenant_id);

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/members/'.$rotationMember->public_id.'/secret-unlock-password/force-rotation')
            ->assertOk()
            ->assertJsonPath('data.user_id', $rotationMember->id)
            ->assertJsonPath('data.user_public_id', $rotationMember->public_id)
            ->assertJsonPath('data.has_secret_unlock_password', false);

        $this->assertFalse($rotationMember->refresh()->hasSecretUnlockPassword());

        foreach ([
            $otherMember->public_id,
            'mem_01HX0000000000000000000000',
            'usr_01hx0000000000000000000000',
            'usr_01HX0000000000000000000000',
        ] as $missingMember) {
            $this
                ->withApiToken($owner)
                ->patchJson('/api/v1/tenant/members/'.$missingMember.'/role', [
                    'role' => User::ROLE_ADMIN,
                ])
                ->assertNotFound();
        }
    }

    public function test_owner_and_admin_role_update_rules_are_enforced(): void
    {
        $tenant = $this->tenant();
        $otherTenant = $this->tenant('Other', 'other');
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $admin = $this->user($tenant, User::ROLE_ADMIN, 'admin@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $limitedMember = $this->user($tenant, User::ROLE_MEMBER, 'limited@example.test');
        $otherMember = $this->user($otherTenant, User::ROLE_MEMBER, 'other-member@example.test');

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$member->id.'/role', [
                'role' => ' admin ',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.role', User::ROLE_ADMIN);

        $this->assertSame(User::ROLE_ADMIN, $member->refresh()->role);

        $this
            ->withApiToken($admin)
            ->patchJson('/api/v1/tenant/members/'.$limitedMember->id.'/role', [
                'role' => User::ROLE_OWNER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this
            ->withApiToken($admin)
            ->patchJson('/api/v1/tenant/members/'.$owner->id.'/role', [
                'role' => User::ROLE_MEMBER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);

        $this
            ->withApiToken($limitedMember)
            ->patchJson('/api/v1/tenant/members/'.$admin->id.'/role', [
                'role' => User::ROLE_MEMBER,
            ])
            ->assertForbidden();

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$otherMember->id.'/role', [
                'role' => User::ROLE_ADMIN,
            ])
            ->assertNotFound();

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$owner->id.'/role', [
                'role' => User::ROLE_MEMBER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);
    }

    public function test_owner_can_revoke_member_access_and_member_tokens(): void
    {
        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $admin = $this->user($tenant, User::ROLE_ADMIN, 'admin@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $memberToken = $member->createApiToken('member-session');

        $this
            ->withApiToken($owner)
            ->deleteJson('/api/v1/tenant/members/'.$member->id)
            ->assertNoContent();

        $member->refresh();

        $this->assertNull($member->tenant_id);
        $this->assertSame(User::ROLE_MEMBER, $member->role);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $memberToken->accessToken->id,
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$memberToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this
            ->withApiToken($admin)
            ->deleteJson('/api/v1/tenant/members/'.$owner->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);

        $this
            ->withApiToken($owner)
            ->deleteJson('/api/v1/tenant/members/'.$owner->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);
    }

    public function test_owner_can_force_secret_unlock_password_rotation_for_member(): void
    {
        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $memberToken = $member->createApiToken('member-session')->plainTextToken;

        SecretUnlockToken::query()->create([
            'user_id' => $member->id,
            'token' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertTrue($member->hasSecretUnlockPassword());
        $this->assertDatabaseCount('secret_unlock_tokens', 1);

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/members/'.$member->id.'/secret-unlock-password/force-rotation', [
                'reason' => ' user forgot unlock password ',
            ])
            ->assertOk()
            ->assertJsonPath('data.user_id', $member->id)
            ->assertJsonPath('data.has_secret_unlock_password', false)
            ->assertJsonPath('data.mode', 'forced_rotation');

        $member->refresh();

        $this->assertFalse($member->hasSecretUnlockPassword());
        $this->assertDatabaseCount('secret_unlock_tokens', 0);

        $this
            ->withHeader('Authorization', 'Bearer '.$memberToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $member->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$memberToken)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_FORCED_ROTATION)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($owner->id, $event->user_id);
        $this->assertSame('member@example.test', $event->subject_email);
        $this->assertSame(User::ROLE_OWNER, $event->metadata['manager_role']);
        $this->assertSame($member->id, $event->metadata['target_user_id']);
        $this->assertSame(User::ROLE_MEMBER, $event->metadata['target_role']);
        $this->assertSame('user forgot unlock password', $event->metadata['reason']);
    }

    public function test_owner_can_change_member_account_status_and_revoke_member_tokens(): void
    {
        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $member->forceFill([
            'email_verified_at' => now(),
            'pending_email' => 'pending-member@example.test',
        ])->save();

        $memberToken = $member->createApiToken('member-session');
        SecretUnlockToken::query()->create([
            'user_id' => $member->id,
            'token' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$member->public_id.'/account-status', [
                'account_status' => ' Suspended ',
                'reason' => ' suspected credential compromise ',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.public_id', $member->public_id)
            ->assertJsonPath('data.role', User::ROLE_MEMBER)
            ->assertJsonPath('data.account_status', User::ACCOUNT_STATUS_SUSPENDED)
            ->assertJsonPath('data.is_email_verified', true);

        $member->refresh();

        $this->assertSame(User::ACCOUNT_STATUS_SUSPENDED, $member->account_status);
        $this->assertSame($tenant->id, $member->tenant_id);
        $this->assertSame(User::ROLE_MEMBER, $member->role);
        $this->assertSame('pending-member@example.test', $member->pending_email);
        $this->assertTrue($member->hasSecretUnlockPassword());
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $memberToken->accessToken->id,
        ]);
        $this->assertDatabaseCount('secret_unlock_tokens', 0);

        $this
            ->withHeader('Authorization', 'Bearer '.$memberToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $staleReactivationToken = $member->createApiToken('stale-reactivation-session');
        SecretUnlockToken::query()->create([
            'user_id' => $member->id,
            'token' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$member->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            ])
            ->assertOk()
            ->assertJsonPath('data.account_status', User::ACCOUNT_STATUS_ACTIVE);

        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $member->refresh()->account_status);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $staleReactivationToken->accessToken->id,
        ]);
        $this->assertDatabaseCount('secret_unlock_tokens', 0);

        $this
            ->withHeader('Authorization', 'Bearer '.$staleReactivationToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $events = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_STATUS_CHANGE)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame($tenant->id, $events[0]->tenant_id);
        $this->assertSame($owner->id, $events[0]->user_id);
        $this->assertSame('member@example.test', $events[0]->subject_email);
        $this->assertSame(User::ROLE_OWNER, $events[0]->metadata['manager_role']);
        $this->assertSame($member->id, $events[0]->metadata['target_user_id']);
        $this->assertSame($member->public_id, $events[0]->metadata['target_user_public_id']);
        $this->assertSame(User::ROLE_MEMBER, $events[0]->metadata['target_role']);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $events[0]->metadata['previous_account_status']);
        $this->assertSame(User::ACCOUNT_STATUS_SUSPENDED, $events[0]->metadata['new_account_status']);
        $this->assertSame('suspected credential compromise', $events[0]->metadata['reason']);
        $this->assertSame(User::ACCOUNT_STATUS_SUSPENDED, $events[1]->metadata['previous_account_status']);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $events[1]->metadata['new_account_status']);
        $this->assertArrayNotHasKey('reason', $events[1]->metadata);
    }

    public function test_account_status_change_enforces_tenant_member_boundaries(): void
    {
        $tenant = $this->tenant();
        $otherTenant = $this->tenant('Other', 'other');
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $admin = $this->user($tenant, User::ROLE_ADMIN, 'admin@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $otherMember = $this->user($otherTenant, User::ROLE_MEMBER, 'other-member@example.test');
        $orphan = User::factory()->create(['tenant_id' => null]);

        $this
            ->patchJson('/api/v1/tenant/members/'.$member->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_DISABLED,
            ])
            ->assertUnauthorized();

        $this
            ->withApiToken($orphan)
            ->patchJson('/api/v1/tenant/members/'.$member->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_DISABLED,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $this
            ->withApiToken($member)
            ->patchJson('/api/v1/tenant/members/'.$admin->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_DISABLED,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->withApiToken($admin)
            ->patchJson('/api/v1/tenant/members/'.$owner->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
                'reason' => ' policy hold ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_ACCOUNT_STATUS_CHANGE)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(SecurityEvent::OUTCOME_FAILURE, $event->outcome);
        $this->assertSame('owner_boundary', $event->metadata['reason']);
        $this->assertSame('policy hold', $event->metadata['requested_reason']);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $event->metadata['previous_account_status']);
        $this->assertSame(User::ACCOUNT_STATUS_SUSPENDED, $event->metadata['new_account_status']);

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$owner->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_DISABLED,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);

        $this->assertSame('self_target', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$otherMember->id.'/account-status', [
                'account_status' => User::ACCOUNT_STATUS_DISABLED,
            ])
            ->assertNotFound();

        $this
            ->withApiToken($owner)
            ->patchJson('/api/v1/tenant/members/'.$member->id.'/account-status', [
                'account_status' => 'locked',
                'reason' => ['not a string'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_status', 'reason']);

        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $owner->fresh()->account_status);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $admin->fresh()->account_status);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $member->fresh()->account_status);
        $this->assertSame(User::ACCOUNT_STATUS_ACTIVE, $otherMember->fresh()->account_status);
    }

    public function test_secret_unlock_password_force_rotation_enforces_tenant_member_boundaries(): void
    {
        $tenant = $this->tenant();
        $otherTenant = $this->tenant('Other', 'other');
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $admin = $this->user($tenant, User::ROLE_ADMIN, 'admin@example.test');
        $member = $this->user($tenant, User::ROLE_MEMBER, 'member@example.test');
        $otherMember = $this->user($otherTenant, User::ROLE_MEMBER, 'other-member@example.test');
        $orphan = User::factory()->create(['tenant_id' => null]);

        $this
            ->postJson('/api/v1/tenant/members/'.$member->id.'/secret-unlock-password/force-rotation')
            ->assertUnauthorized();

        $this
            ->withApiToken($orphan)
            ->postJson('/api/v1/tenant/members/'.$member->id.'/secret-unlock-password/force-rotation')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant context is required for authenticated API access.');

        $this
            ->withApiToken($member)
            ->postJson('/api/v1/tenant/members/'.$admin->id.'/secret-unlock-password/force-rotation')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->withApiToken($admin)
            ->postJson('/api/v1/tenant/members/'.$owner->id.'/secret-unlock-password/force-rotation')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);

        $this->assertSame('owner_boundary', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/members/'.$owner->id.'/secret-unlock-password/force-rotation')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);

        $this->assertSame('self_target', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/members/'.$otherMember->id.'/secret-unlock-password/force-rotation')
            ->assertNotFound();

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/members/'.$member->id.'/secret-unlock-password/force-rotation', [
                'reason' => ['not a string'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertTrue($owner->fresh()->hasSecretUnlockPassword());
        $this->assertTrue($admin->fresh()->hasSecretUnlockPassword());
        $this->assertTrue($member->fresh()->hasSecretUnlockPassword());
        $this->assertTrue($otherMember->fresh()->hasSecretUnlockPassword());
    }

    public function test_invite_validation_blocks_existing_users_duplicate_pending_invites_and_admin_owner_invites(): void
    {
        Notification::fake();

        $tenant = $this->tenant();
        $owner = $this->user($tenant, User::ROLE_OWNER, 'owner@example.test');
        $admin = $this->user($tenant, User::ROLE_ADMIN, 'admin@example.test');
        $this->user($tenant, User::ROLE_MEMBER, 'existing@example.test');

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'existing@example.test',
                'role' => User::ROLE_MEMBER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'pending@example.test',
                'role' => User::ROLE_MEMBER,
            ])
            ->assertCreated();

        $this
            ->withApiToken($owner)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'pending@example.test',
                'role' => User::ROLE_MEMBER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this
            ->withApiToken($admin)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'owner-invite@example.test',
                'role' => User::ROLE_OWNER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        Notification::assertSentOnDemandTimes(TenantMemberInvitationNotification::class, 1);
    }

    private function tenant(string $name = 'Bunshin AI', string $slug = 'bunshin-ai'): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function user(Tenant $tenant, string $role, string $email): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
            'email' => $email,
        ]);
    }

    private function assertPublicId(string $prefix, mixed $publicId): void
    {
        $this->assertIsString($publicId);
        $this->assertMatchesRegularExpression('/^'.$prefix.'_[0-9A-HJKMNP-TV-Z]{26}$/', $publicId);
    }
}
