<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TagNameNormalizer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const LOCAL_DEV_TOKEN = 'local-dev-token';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default'],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin User',
                'password' => 'password',
            ],
        );

        $user->personalAccessTokens()->updateOrCreate([
            'name' => 'local-dev',
        ], [
            'token' => hash('sha256', self::LOCAL_DEV_TOKEN),
            'abilities' => ['*'],
            'expires_at' => now()->addYears(10),
        ]);

        $categories = $this->seedCategories($tenant, $user);
        $this->seedMemories($tenant, $user, $categories);
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(Tenant $tenant, User $user): array
    {
        $music = $this->category($tenant, $user, '音楽', 'music', 10);
        $school = $this->category($tenant, $user, '学校', 'school', 20);
        $family = $this->category($tenant, $user, '家族', 'family', 30);

        return [
            'music' => $music,
            'mrchildren' => $this->category($tenant, $user, 'Mr.Children', 'mrchildren', 10, $music),
            'band' => $this->category($tenant, $user, 'バンド', 'band', 20, $music),
            'high-school' => $this->category($tenant, $user, '高校', 'high-school', 10, $school),
            'club' => $this->category($tenant, $user, '部活', 'club', 20, $school),
            'home' => $this->category($tenant, $user, '実家', 'home', 10, $family),
        ];
    }

    private function category(
        Tenant $tenant,
        User $user,
        string $name,
        string $slug,
        int $sortOrder,
        ?Category $parent = null
    ): Category {
        return Category::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'slug' => $slug,
        ], [
            'parent_id' => $parent?->id,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedMemories(Tenant $tenant, User $user, array $categories): void
    {
        $this->memory($tenant, $user, $categories['mrchildren'], [
            'title' => 'Tomorrow never knowsを初めて聴いた日',
            'body' => '高校の帰り道、友達が貸してくれたMDで初めて聴いた。帰り道の空気まで一緒に残っている。',
            'period_key' => 'high_school',
            'occurred_on' => '2004-09-12',
            'emotion_label' => '感動',
            'emotion_intensity' => 5,
            'visibility' => Memory::VISIBILITY_PRIVATE,
            'metadata' => [
                'emotion_scores' => ['感動' => 92, '懐かしさ' => 74],
                'importance_score' => 0.95,
                'beliefs' => ['音楽は人生の場面ごと保存してくれる'],
                'chains' => ['部活帰り', '友達との貸し借り'],
            ],
            'tags' => ['音楽', '青春', '友達'],
        ]);

        $this->memory($tenant, $user, $categories['club'], [
            'title' => '最後の大会前夜',
            'body' => '部室で遅くまで道具を片付けた。勝てるかより、この時間が終わることの方が大きかった。',
            'period_key' => 'high_school',
            'occurred_on' => '2005-07-18',
            'emotion_label' => '緊張',
            'emotion_intensity' => 4,
            'visibility' => Memory::VISIBILITY_SHARED,
            'metadata' => [
                'emotion_scores' => ['緊張' => 81, '達成感' => 68],
                'importance_score' => 0.86,
                'beliefs' => ['一区切りの前夜はよく覚えている'],
                'chains' => ['大会', '仲間', '夏'],
            ],
            'tags' => ['部活', '夏', '仲間'],
        ]);

        $this->memory($tenant, $user, $categories['home'], [
            'title' => '台所のカレーの匂い',
            'body' => '小学校から帰ると玄関までカレーの匂いがして、その日は家に早く入りたくなった。',
            'period_key' => 'elementary_school',
            'occurred_on' => '1998-11-04',
            'emotion_label' => '安心',
            'emotion_intensity' => 5,
            'visibility' => Memory::VISIBILITY_PRIVATE,
            'metadata' => [
                'emotion_scores' => ['安心' => 88, '懐かしさ' => 83],
                'importance_score' => 0.78,
                'beliefs' => ['匂いは記憶の入口になる'],
                'chains' => ['実家', '夕方'],
            ],
            'tags' => ['家族', '食卓'],
        ]);

        $this->memory($tenant, $user, $categories['high-school'], [
            'title' => '言えなかった一言',
            'body' => '卒業式の日に言おうと思っていたことを、結局最後まで言えなかった。',
            'period_key' => 'high_school',
            'occurred_on' => '2006-03-01',
            'emotion_label' => '切なさ',
            'emotion_intensity' => 5,
            'visibility' => Memory::VISIBILITY_SECRET,
            'metadata' => [
                'emotion_scores' => ['切なさ' => 96, '緊張' => 64],
                'importance_score' => 0.9,
                'beliefs' => ['言わなかったことも長く残る'],
                'chains' => ['卒業式', '秘密'],
            ],
            'tags' => ['秘密', '卒業'],
        ]);
    }

    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     period_key: string,
     *     occurred_on: string,
     *     emotion_label: string,
     *     emotion_intensity: int,
     *     visibility: string,
     *     metadata: array<string, mixed>,
     *     tags: list<string>
     * }  $data
     */
    private function memory(Tenant $tenant, User $user, Category $category, array $data): void
    {
        $memory = Memory::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'title' => $data['title'],
        ], [
            'category_id' => $category->id,
            'period_key' => $data['period_key'],
            'occurred_on' => $data['occurred_on'],
            'body' => $data['body'],
            'emotion_label' => $data['emotion_label'],
            'emotion_intensity' => $data['emotion_intensity'],
            'visibility' => $data['visibility'],
            'source' => 'seed',
            'metadata' => $data['metadata'],
        ]);

        $tagIds = collect($data['tags'])
            ->map(fn (string $tagName): int => $this->tag($tenant, $tagName)->id)
            ->all();

        $memory->tags()->sync($tagIds);
    }

    private function tag(Tenant $tenant, string $name): Tag
    {
        $normalized = TagNameNormalizer::normalize($name);

        return Tag::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'normalized_name' => $normalized->normalizedName,
        ], [
            'name' => $normalized->name,
        ]);
    }
}
