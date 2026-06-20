<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Memory;
use App\Models\SecretUnlockToken;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SecretUnlockPasswordRecoveryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecretUnlockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_issue_secret_unlock_token_with_dedicated_password(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($user->checkSecretUnlockPassword('secret-password'));
        $this->assertFalse($user->checkSecretUnlockPassword('password'));
        $this->assertArrayNotHasKey('secret_unlock_password', $user->toArray());
        $this->assertNotSame('secret-password', $user->secret_unlock_password);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('secret_unlock_tokens', 0);

        $response = $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'unlock_token',
                    'expires_at',
                ],
            ]);

        $plainTextToken = $response->json('data.unlock_token');
        [$id, $token] = explode('|', $plainTextToken, 2);
        $storedToken = SecretUnlockToken::query()->findOrFail((int) $id);

        $this->assertSame($user->id, $storedToken->user_id);
        $this->assertTrue(hash_equals($storedToken->token, hash('sha256', $token)));
        $this->assertStringNotContainsString($token, $storedToken->token);
        $this->assertTrue($storedToken->expires_at->between(
            now()->addMinutes(14),
            now()->addMinutes(16),
        ));
        $this->assertSame($storedToken->expires_at->toAtomString(), $response->json('data.expires_at'));
    }

    public function test_secret_unlock_rejects_wrong_password_and_requires_tenant_user_authentication(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userWithoutTenant = User::factory()->create(['tenant_id' => null]);

        $this
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->assertUnauthorized();

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('secret_unlock_tokens', 0);

        $this
            ->withApiToken($userWithoutTenant)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->assertForbidden();
    }

    public function test_secret_unlock_rejects_users_without_configured_unlock_password(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'secret_unlock_password' => null,
        ]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('secret_unlock_tokens', 0);
    }

    public function test_authenticated_user_can_setup_secret_unlock_password_with_account_password(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'secret_unlock_password' => null,
        ]);

        $this->assertFalse($user->hasSecretUnlockPassword());

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                'account_password' => 'password',
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_secret_unlock_password', true)
            ->assertJsonPath('data.mode', 'set');

        $user->refresh();

        $this->assertTrue($user->checkSecretUnlockPassword('new-secret-password'));
        $this->assertFalse($user->checkSecretUnlockPassword('password'));
        $this->assertNotSame('new-secret-password', $user->secret_unlock_password);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'new-secret-password',
            ])
            ->assertCreated();
    }

    public function test_secret_unlock_password_setup_rejects_invalid_account_password_and_account_password_reuse(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'secret_unlock_password' => null,
        ]);
        $userWithoutTenant = User::factory()->create([
            'tenant_id' => null,
            'secret_unlock_password' => null,
        ]);

        $payload = [
            'account_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ];

        $this
            ->putJson('/api/v1/secret-unlock-password', $payload)
            ->assertUnauthorized();

        $this
            ->withApiToken($userWithoutTenant)
            ->putJson('/api/v1/secret-unlock-password', $payload)
            ->assertForbidden();

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                ...$payload,
                'account_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_password']);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                'account_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $user->refresh();

        $this->assertFalse($user->hasSecretUnlockPassword());
    }

    public function test_authenticated_user_can_change_secret_unlock_password_with_account_and_current_unlock_password(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('secret_unlock_tokens', 1);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                'account_password' => 'password',
                'current_password' => 'secret-password',
                'password' => 'next-secret-password',
                'password_confirmation' => 'next-secret-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_secret_unlock_password', true)
            ->assertJsonPath('data.mode', 'changed');

        $user->refresh();

        $this->assertDatabaseCount('secret_unlock_tokens', 0);
        $this->assertFalse($user->checkSecretUnlockPassword('secret-password'));
        $this->assertTrue($user->checkSecretUnlockPassword('next-secret-password'));

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'next-secret-password',
            ])
            ->assertCreated();
    }

    public function test_secret_unlock_password_change_requires_valid_account_and_current_unlock_passwords(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $payload = [
            'account_password' => 'password',
            'current_password' => 'secret-password',
            'password' => 'next-secret-password',
            'password_confirmation' => 'next-secret-password',
        ];

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                ...$payload,
                'account_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_password']);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                ...$payload,
                'current_password' => 'wrong-secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                'account_password' => 'password',
                'password' => 'next-secret-password',
                'password_confirmation' => 'next-secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this
            ->withApiToken($user)
            ->putJson('/api/v1/secret-unlock-password', [
                ...$payload,
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $user->refresh();

        $this->assertTrue($user->checkSecretUnlockPassword('secret-password'));
        $this->assertFalse($user->checkSecretUnlockPassword('next-secret-password'));
    }

    public function test_verified_user_can_request_secret_unlock_password_recovery_link(): void
    {
        Notification::fake();

        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);

        SecretUnlockToken::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'password',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Secret unlock password recovery link has been sent.');

        Notification::assertSentTo(
            $user,
            SecretUnlockPasswordRecoveryNotification::class,
            function (SecretUnlockPasswordRecoveryNotification $notification) use ($user): bool {
                $recoveryUrl = $notification->recoveryUrl();

                $this->assertStringContainsString('/api/v1/secret-unlock-password/recovery/'.$user->id.'/', $recoveryUrl);
                $this->assertStringContainsString('expires=', $recoveryUrl);
                $this->assertStringContainsString('signature=', $recoveryUrl);

                return true;
            },
        );

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_REQUEST)
            ->where('outcome', SecurityEvent::OUTCOME_REQUESTED)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('owner@example.test', $event->subject_email);
        $this->assertNull($event->metadata);

        $user->refresh();

        $this->assertTrue($user->checkSecretUnlockPassword('secret-password'));
        $this->assertDatabaseCount('secret_unlock_tokens', 1);
    }

    public function test_secret_unlock_password_recovery_request_requires_verified_email_and_account_password(): void
    {
        Notification::fake();

        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $unverifiedUser = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email' => 'unverified@example.test',
        ]);
        $verifiedUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'verified@example.test',
        ]);

        $this
            ->withApiToken($unverifiedUser)
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Email verification is required to recover the secret unlock password.');

        $this
            ->withApiToken($verifiedUser)
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_password']);

        Notification::assertNothingSent();

        $unverifiedEvent = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_REQUEST)
            ->where('user_id', $unverifiedUser->id)
            ->sole();
        $wrongPasswordEvent = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_REQUEST)
            ->where('user_id', $verifiedUser->id)
            ->sole();

        $this->assertSame(SecurityEvent::OUTCOME_FAILURE, $unverifiedEvent->outcome);
        $this->assertSame('email_not_verified', $unverifiedEvent->metadata['reason']);
        $this->assertSame(SecurityEvent::OUTCOME_FAILURE, $wrongPasswordEvent->outcome);
        $this->assertSame('invalid_account_password', $wrongPasswordEvent->metadata['reason']);
    }

    public function test_secret_unlock_password_recovery_request_requires_authenticated_active_tenant_user(): void
    {
        Notification::fake();

        $this
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'password',
            ])
            ->assertUnauthorized();

        $orphan = User::factory()->create(['tenant_id' => null]);

        $this
            ->withApiToken($orphan)
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'password',
            ])
            ->assertForbidden();

        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $disabledUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'account_status' => User::ACCOUNT_STATUS_DISABLED,
        ]);

        $this
            ->withApiToken($disabledUser)
            ->postJson('/api/v1/secret-unlock-password/recovery/request', [
                'account_password' => 'password',
            ])
            ->assertUnauthorized();

        Notification::assertNothingSent();
    }

    public function test_verified_user_can_complete_secret_unlock_password_recovery_with_signed_link(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);
        $apiToken = $user->createApiToken('recovery-complete')->plainTextToken;

        SecretUnlockToken::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$apiToken)
            ->putJson($this->recoveryUrlFor($user), [
                'account_password' => 'password',
                'password' => 'recovered-secret-password',
                'password_confirmation' => 'recovered-secret-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_secret_unlock_password', true)
            ->assertJsonPath('data.mode', 'recovered');

        $user->refresh();

        $this->assertDatabaseCount('secret_unlock_tokens', 0);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertFalse($user->checkSecretUnlockPassword('secret-password'));
        $this->assertTrue($user->checkSecretUnlockPassword('recovered-secret-password'));

        $this
            ->withHeader('Authorization', 'Bearer '.$apiToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this
            ->withHeader('Authorization', 'Bearer '.$apiToken)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this
            ->withHeader('Authorization', 'Bearer '.$apiToken)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'recovered-secret-password',
            ])
            ->assertCreated();

        $event = SecurityEvent::query()
            ->where('event_type', SecurityEvent::TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_COMPLETE)
            ->where('outcome', SecurityEvent::OUTCOME_SUCCESS)
            ->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('owner@example.test', $event->subject_email);
        $this->assertNull($event->metadata);
    }

    public function test_secret_unlock_password_recovery_completion_rejects_invalid_links_and_wrong_user(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'other@example.test',
        ]);
        $payload = [
            'account_password' => 'password',
            'password' => 'recovered-secret-password',
            'password_confirmation' => 'recovered-secret-password',
        ];

        $this
            ->withApiToken($user)
            ->putJson($this->recoveryUrlFor($user, 'invalid-hash'), $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'The secret unlock password recovery link is invalid or expired.');

        $this->assertSame('invalid_hash', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);

        $tamperedUrl = preg_replace('/signature=[^&]+/', 'signature=invalid', $this->recoveryUrlFor($user));
        $this->assertIsString($tamperedUrl);

        $this
            ->withApiToken($user)
            ->putJson($tamperedUrl, $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'The secret unlock password recovery link is invalid or expired.');

        $this->assertSame('invalid_signature', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);

        $this
            ->withApiToken($otherUser)
            ->putJson($this->recoveryUrlFor($user), $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'The secret unlock password recovery link is invalid or expired.');

        $this->assertSame('user_mismatch', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);
        $this->assertTrue($user->fresh()->checkSecretUnlockPassword('secret-password'));
        $this->assertTrue($otherUser->fresh()->checkSecretUnlockPassword('secret-password'));
    }

    public function test_secret_unlock_password_recovery_completion_requires_verified_email_and_account_password(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $unverifiedUser = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email' => 'unverified@example.test',
        ]);
        $verifiedUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'verified@example.test',
        ]);

        $this
            ->withApiToken($unverifiedUser)
            ->putJson($this->recoveryUrlFor($unverifiedUser), [
                'account_password' => 'password',
                'password' => 'recovered-secret-password',
                'password_confirmation' => 'recovered-secret-password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Email verification is required to recover the secret unlock password.');

        $this->assertSame('email_not_verified', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);

        $this
            ->withApiToken($verifiedUser)
            ->putJson($this->recoveryUrlFor($verifiedUser), [
                'account_password' => 'wrong-password',
                'password' => 'recovered-secret-password',
                'password_confirmation' => 'recovered-secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_password']);

        $this->assertSame('invalid_account_password', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);
        $this->assertTrue($unverifiedUser->fresh()->checkSecretUnlockPassword('secret-password'));
        $this->assertTrue($verifiedUser->fresh()->checkSecretUnlockPassword('secret-password'));
    }

    public function test_secret_unlock_password_recovery_completion_rejects_password_reuse_and_requires_authentication(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);
        $orphan = User::factory()->create(['tenant_id' => null]);

        $this
            ->putJson($this->recoveryUrlFor($user), [
                'account_password' => 'password',
                'password' => 'recovered-secret-password',
                'password_confirmation' => 'recovered-secret-password',
            ])
            ->assertUnauthorized();

        $this
            ->withApiToken($orphan)
            ->putJson($this->recoveryUrlFor($orphan), [
                'account_password' => 'password',
                'password' => 'recovered-secret-password',
                'password_confirmation' => 'recovered-secret-password',
            ])
            ->assertForbidden();

        $this
            ->withApiToken($user)
            ->putJson($this->recoveryUrlFor($user), [
                'account_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertSame('password_reuses_account_password', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);

        $this
            ->withApiToken($user)
            ->putJson($this->recoveryUrlFor($user), [
                'account_password' => 'password',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertSame('password_reuses_current_unlock_password', SecurityEvent::query()->latest('id')->firstOrFail()->metadata['reason']);
        $this->assertTrue($user->fresh()->checkSecretUnlockPassword('secret-password'));
    }

    public function test_valid_secret_unlock_token_allows_memory_space_to_include_secret_memories(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '家族', 'family');
        $tag = $this->createTag($tenant, '秘密', '秘密');

        $visible = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '通常記憶',
            'body' => '通常本文',
            'visibility' => Memory::VISIBILITY_PRIVATE,
        ]);
        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '秘匿記憶',
            'body' => '秘匿本文',
            'visibility' => Memory::VISIBILITY_SECRET,
            'metadata' => [
                'importance_score' => 1,
            ],
        ]);
        $secret->tags()->attach($tag);

        $unlockToken = $this
            ->withApiToken($user)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->json('data.unlock_token');

        $response = $this
            ->withApiToken($user)
            ->withHeader('X-Secret-Unlock', $unlockToken)
            ->getJson('/api/v1/memory-space?include_secret=1')
            ->assertOk()
            ->assertJsonPath('data.categories.0.memory_count', 2)
            ->assertJsonPath('data.categories.0.locked_secret_count', 0)
            ->assertJsonPath('data.secret.locked', false)
            ->assertJsonPath('data.secret.locked_count', 0);

        $this->assertNotNull($response->json('data.secret.unlock_expires_at'));

        $memories = collect($response->json('data.memories'))->keyBy('id');

        $this->assertTrue($memories->has($visible->id));
        $this->assertTrue($memories->has($secret->id));
        $this->assertSame('秘匿記憶', $memories[$secret->id]['title']);
        $this->assertSame('秘匿本文', $memories[$secret->id]['body']);
        $this->assertSame(['秘密'], $memories[$secret->id]['tags']);
        $this->assertSame(Memory::VISIBILITY_SECRET, $memories[$secret->id]['visibility']);

        $storedToken = SecretUnlockToken::findToken($unlockToken);

        $this->assertNotNull($storedToken?->last_used_at);
    }

    public function test_invalid_other_user_and_expired_secret_unlock_tokens_do_not_expose_secret_memories(): void
    {
        $tenant = $this->createTenant('分身AI', 'bunshin-ai');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = $this->createCategory($tenant, $user, '家族', 'family');
        $secret = $this->createMemory($tenant, $user, [
            'category_id' => $category->id,
            'title' => '漏れてはいけない title',
            'body' => '漏れてはいけない body',
            'visibility' => Memory::VISIBILITY_SECRET,
        ]);

        $otherUserUnlockToken = $this
            ->withApiToken($otherUser)
            ->postJson('/api/v1/secret-unlocks', [
                'password' => 'secret-password',
            ])
            ->json('data.unlock_token');

        $this->assertMemorySpaceKeepsSecretLocked($user, $secret, $otherUserUnlockToken);

        $expiredPlainTextToken = Str::random(40);
        $expiredToken = SecretUnlockToken::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', $expiredPlainTextToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertMemorySpaceKeepsSecretLocked(
            $user,
            $secret,
            $expiredToken->getKey().'|'.$expiredPlainTextToken,
        );

        $this->assertMemorySpaceKeepsSecretLocked($user, $secret, 'invalid-token');
    }

    private function assertMemorySpaceKeepsSecretLocked(User $user, Memory $secret, string $unlockToken): void
    {
        $response = $this
            ->withApiToken($user)
            ->withHeader('X-Secret-Unlock', $unlockToken)
            ->getJson('/api/v1/memory-space?include_secret=1')
            ->assertOk()
            ->assertJsonPath('data.secret.locked', true)
            ->assertJsonPath('data.secret.locked_count', 1);

        $this->assertNotContains(
            $secret->id,
            collect($response->json('data.memories'))->pluck('id')->all(),
        );
        $this->assertStringNotContainsString('漏れてはいけない title', $response->getContent());
        $this->assertStringNotContainsString('漏れてはいけない body', $response->getContent());
    }

    private function recoveryUrlFor(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute(
            'api.v1.secret-unlock-password.recovery.complete',
            now()->addMinutes(30),
            [
                'id' => $user->id,
                'hash' => $hash ?? sha1((string) $user->email),
            ],
        );
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
