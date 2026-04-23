<?php

namespace Tests\Feature;

use App\Models\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryBubblesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bubbles_page_shows_up_to_10_memories_in_one_layer(): void
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
        $response->assertSee('全記憶数');
        $response->assertSee('1個目 / 全1個');
        $response->assertSee('1-10件目');
    }

    public function test_bubbles_page_creates_second_depth_after_10_memories(): void
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
        $response->assertSee('1個目 / 全2個');
        $response->assertSee('1-10件目');
        $response->assertSee('2本指でひろげると奥の記憶玉へ');
    }
}
