<?php

namespace Tests\Feature;

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
        $payload = [
            'period' => '高校生',
            'content' => '放課後の教室で友達と話したことを思い出した。',
            'emotion' => '普通',
        ];

        $response = $this->post(route('memories.store'), $payload);

        $response->assertRedirect(route('memories.index'));
        $this->assertDatabaseHas('memories', $payload);
    }

    public function test_preview_form_accepts_custom_emotion_values(): void
    {
        $payload = [
            'period' => '高校生',
            'content' => '夕方の帰り道で少し胸があたたかくなった。',
            'emotion' => 'じんわり嬉しい',
        ];

        $response = $this->post(route('memories.store'), $payload);

        $response->assertRedirect(route('memories.index'));
        $this->assertDatabaseHas('memories', $payload);
    }
}
