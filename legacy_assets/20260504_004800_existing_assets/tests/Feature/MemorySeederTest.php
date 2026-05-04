<?php

namespace Tests\Feature;

use App\Models\Memory;
use Database\Seeders\MemorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_preserves_non_demo_memories(): void
    {
        Memory::query()->create([
            'period' => '高校生',
            'content' => '実データの記憶',
            'emotion' => '普通',
            'tags' => ['real'],
        ]);

        $this->seed(MemorySeeder::class);

        $this->assertDatabaseHas('memories', [
            'content' => '実データの記憶',
            'emotion' => '普通',
        ]);

        $this->assertSame(71, Memory::query()->count());
        $this->assertSame(70, Memory::query()->whereJsonContains('tags', 'DEMO')->count());
    }

    public function test_seeder_replaces_only_demo_memories_when_re_run(): void
    {
        $this->seed(MemorySeeder::class);
        $this->seed(MemorySeeder::class);

        $this->assertSame(70, Memory::query()->count());
        $this->assertSame(70, Memory::query()->whereJsonContains('tags', 'DEMO')->count());
    }
}
