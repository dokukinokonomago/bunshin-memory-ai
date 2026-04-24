<?php

namespace Tests\Feature;

use App\Models\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryBubblesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bubbles_page_shows_up_to_100_memories_in_one_layer(): void
    {
        foreach (range(1, 100) as $index) {
            Memory::query()->create([
                'period' => '高校生',
                'content' => "記憶 {$index}",
                'emotion' => '普通',
            ]);
        }

        $response = $this->get(route('memories.bubbles'));

        $response->assertOk();
        $response->assertSee('全記憶数');
        $response->assertDontSee('第1層 / 全2層');
    }

    public function test_bubbles_page_moves_to_second_layer_after_100_memories(): void
    {
        foreach (range(1, 101) as $index) {
            Memory::query()->create([
                'period' => '高校生',
                'content' => "記憶 {$index}",
                'emotion' => '普通',
            ]);
        }

        $response = $this->get(route('memories.bubbles'));

        $response->assertOk();
        $response->assertSee('第1層 / 全2層');
    }
}
