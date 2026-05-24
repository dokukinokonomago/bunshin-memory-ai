<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalDevSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_dev_seed_creates_token_and_sample_memory_space_data(): void
    {
        $this->seed();

        $user = User::query()
            ->where('email', 'admin@example.test')
            ->firstOrFail();

        $this->assertSame('default', $user->tenant?->slug);
        $this->assertSame(User::ROLE_OWNER, $user->role);
        $this->assertTrue($user->checkSecretUnlockPassword('secret-password'));
        $this->assertTrue(
            PersonalAccessToken::findToken('local-dev-token')?->tokenable->is($user) ?? false
        );

        $this
            ->withHeader('Authorization', 'Bearer local-dev-token')
            ->getJson('/api/v1/categories?tree=1')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', '音楽')
            ->assertJsonCount(2, 'data.0.children');

        $this
            ->withHeader('Authorization', 'Bearer local-dev-token')
            ->getJson('/api/v1/memories')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['visibility' => Memory::VISIBILITY_SECRET]);

        $this
            ->withHeader('Authorization', 'Bearer local-dev-token')
            ->getJson('/api/v1/memory-space')
            ->assertOk()
            ->assertJsonCount(3, 'data.memories')
            ->assertJsonPath('data.secret.locked', true)
            ->assertJsonPath('data.secret.locked_count', 1);
    }
}
