<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryCreatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_create_route_uses_the_new_ui(): void
    {
        $response = $this->get(route('memories.create'));

        $response->assertOk();
        $response->assertSee('記憶を保存する');
        $response->assertSee('action="' . route('memories.store') . '"', false);
    }

    public function test_create_preview_route_redirects_to_create(): void
    {
        $response = $this->get(route('memories.create.preview'));

        $response->assertRedirect(route('memories.create'));
    }

    public function test_preview_form_values_can_be_posted_to_store(): void
    {
        $token = 'test-token';
        $payload = [
            '_token' => $token,
            'period' => '高校生',
            'content' => '放課後の教室で友達と話したことを思い出した。',
            'emotion' => '普通',
            'tags' => '放課後, 友達',
        ];

        $response = $this->withSession(['_token' => $token])->post(route('memories.store'), $payload);

        $response->assertRedirect(route('memories.index'));
        $this->assertDatabaseHas('memories', [
            'period' => '高校生',
            'content' => '放課後の教室で友達と話したことを思い出した。',
            'emotion' => '普通',
        ]);
    }

    public function test_store_rejects_whitespace_only_content(): void
    {
        $token = 'test-token';

        $response = $this
            ->from(route('memories.create'))
            ->withSession(['_token' => $token])
            ->post(route('memories.store'), [
                '_token' => $token,
                'period' => '高校生',
                'content' => " \n\t ",
                'emotion' => '普通',
            ]);

        $response->assertRedirect(route('memories.create'));
        $response->assertSessionHasErrors('content');
        $this->assertDatabaseCount('memories', 0);
    }

    public function test_store_trims_content_before_saving(): void
    {
        $token = 'test-token';

        $response = $this->withSession(['_token' => $token])->post(route('memories.store'), [
            '_token' => $token,
            'period' => '高校生',
            'content' => "  放課後の教室で話した記憶。 \n",
            'emotion' => '普通',
        ]);

        $response->assertRedirect(route('memories.index'));
        $this->assertDatabaseHas('memories', [
            'period' => '高校生',
            'content' => '放課後の教室で話した記憶。',
            'emotion' => '普通',
        ]);
    }

    public function test_update_rejects_whitespace_only_content(): void
    {
        $memory = Memory::query()->create([
            'period' => '高校生',
            'content' => '保存済みの記憶',
            'emotion' => '普通',
        ]);
        $token = 'test-token';

        $response = $this
            ->from(route('memories.edit', $memory))
            ->withSession(['_token' => $token])
            ->put(route('memories.update', $memory), [
                '_token' => $token,
                'period' => '高校生',
                'content' => " \n\t ",
                'emotion' => '普通',
            ]);

        $response->assertRedirect(route('memories.edit', $memory));
        $response->assertSessionHasErrors('content');
        $this->assertDatabaseHas('memories', [
            'id' => $memory->id,
            'content' => '保存済みの記憶',
        ]);
    }

    public function test_preview_form_accepts_custom_emotion_values(): void
    {
        $token = 'test-token';
        $payload = [
            '_token' => $token,
            'period' => '高校生',
            'content' => '夕方の帰り道で少し胸があたたかくなった。',
            'emotion' => 'じんわり嬉しい',
        ];

        $response = $this->withSession(['_token' => $token])->post(route('memories.store'), $payload);

        $response->assertRedirect(route('memories.index'));
        $this->assertDatabaseHas('memories', [
            'period' => '高校生',
            'content' => '夕方の帰り道で少し胸があたたかくなった。',
            'emotion' => 'じんわり嬉しい',
        ]);
    }
}
