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

    public function test_bubbles_page_shows_memory_ocean_overview_cta(): void
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
        $response->assertSee('今日は何をする？');
        $response->assertSee('人生全体を俯瞰しています');
    }

    public function test_bubbles_page_accepts_period_deep_link(): void
    {
        foreach (range(1, 11) as $index) {
            Memory::query()->create([
                'period' => '高校生',
                'content' => "記憶 {$index}",
                'emotion' => '普通',
            ]);
        }

        $response = $this->get(route('memories.bubbles', ['period' => '高校生']));

        $response->assertOk();
        $response->assertSee('高校生');
        $response->assertSee('mem-filter-chip is-on', false);
    }
}
