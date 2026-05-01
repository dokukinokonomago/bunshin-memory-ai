<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryBubblesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
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

    public function test_grave_mode_bubble_is_hidden_by_default(): void
    {
        Memory::query()->create([
            'period' => '高校生',
            'content' => '通常の記憶',
            'emotion' => '普通',
        ]);

        $response = $this->get(route('memories.bubbles'));

        $response->assertOk();
        $response->assertDontSee('"label":"墓場まで"', false);
    }

    public function test_reveal_all_bubbles_shows_grave_mode_bubble(): void
    {
        Memory::query()->create([
            'period' => '高校生',
            'content' => '通常の記憶',
            'emotion' => '普通',
        ]);

        $response = $this->followingRedirects()->post(route('memories.bubbles.reveal-all'), [
            'period' => 'すべて',
        ]);

        $response->assertOk();
        $response->assertSee('grave-mode', false);
        $this->assertTrue(session()->get('memories.grave.visible'));
    }

    public function test_correct_passcode_unlocks_grave_mode(): void
    {
        Memory::query()->create([
            'period' => '高校生',
            'content' => '通常の記憶',
            'emotion' => '普通',
        ]);

        $response = $this->from(route('memories.bubbles'))
            ->post(route('memories.bubbles.unlock-grave'), [
                'period' => 'すべて',
                'passcode' => '1234',
            ]);

        $response->assertRedirect(route('memories.bubbles'));
        $response->assertSessionHas('memories.grave.visible', true);
        $response->assertSessionHas('memories.grave.unlocked', true);
    }

    public function test_logout_clears_grave_mode_session(): void
    {
        $response = $this->withSession([
            'memories.grave.visible' => true,
            'memories.grave.unlocked' => true,
        ])->post(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('memories.grave.visible');
        $response->assertSessionMissing('memories.grave.unlocked');
    }

    public function test_grave_memory_can_be_saved_and_stays_out_of_normal_bubbles(): void
    {
        Memory::query()->create([
            'period' => '高校生',
            'content' => '通常の記憶',
            'emotion' => '普通',
        ]);

        $response = $this->withSession([
            'memories.grave.visible' => true,
            'memories.grave.unlocked' => true,
        ])->post(route('memories.bubbles.store-grave'), [
            'period' => '不明',
            'period_context' => 'すべて',
            'content' => '誰にも見せない記憶',
            'emotion' => '不安',
            'tags' => '秘密, 夜',
            'grave_form' => '1',
        ]);

        $response->assertRedirect(route('memories.bubbles'));
        $response->assertSessionHas('grave_create_success');

        $graveMemory = Memory::query()->where('content', '誰にも見せない記憶')->first();
        $this->assertNotNull($graveMemory);
        $this->assertContains('__grave_hidden__', $graveMemory->tags ?? []);

        $page = $this->withSession([
            'memories.grave.visible' => true,
            'memories.grave.unlocked' => true,
        ])->get(route('memories.bubbles'));

        $page->assertOk();
        $page->assertSee('1件の秘匿記憶');

        $indexPage = $this->get(route('memories.index'));
        $indexPage->assertOk();
        $indexPage->assertDontSee('誰にも見せない記憶');
    }

    public function test_hide_grave_mode_removes_bubble_visibility(): void
    {
        $response = $this->withSession([
            'memories.grave.visible' => true,
            'memories.grave.unlocked' => true,
        ])->post(route('memories.bubbles.hide-grave'), [
            'period' => 'すべて',
        ]);

        $response->assertRedirect(route('memories.bubbles'));
        $response->assertSessionMissing('memories.grave.visible');
        $response->assertSessionMissing('memories.grave.unlocked');
    }
}
