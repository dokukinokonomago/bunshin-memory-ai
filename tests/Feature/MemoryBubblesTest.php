<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryBubblesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_bubbles_page_shows_up_to_10_memories_per_period_in_one_layer(): void
    {
        foreach (range(1, 10) as $index) {
            Memory::query()->create([
                'period' => '高校生',
                'content' => "記憶 {$index}",
                'emotion' => '普通',
            ]);
        }

        $response = $this->get(route('memories.bubbles'));

        $response->assertOk();
        $response->assertSee('MEMORIES');
        $response->assertDontSee('1/2層');
    }

    public function test_bubbles_page_moves_to_second_layer_after_10_memories_in_one_period(): void
    {
        foreach (range(1, 11) as $index) {
            Memory::query()->create([
                'period' => '高校生',
                'content' => "記憶 {$index}",
                'emotion' => '普通',
            ]);
        }

        $response = $this->get(route('memories.bubbles'));

        $response->assertOk();
        $response->assertSee('1/2層');
    }
}
