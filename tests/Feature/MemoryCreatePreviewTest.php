<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryCreatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_create_preview_page_is_available(): void
    {
        $response = $this->get(route('memories.create.preview'));

        $response->assertOk();
        $response->assertSee('記憶を保存する');
        $response->assertSee('action="' . route('memories.store') . '"', false);
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
