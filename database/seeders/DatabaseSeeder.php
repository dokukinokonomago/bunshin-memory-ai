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
     * @var array<string, array{name: string, slug: string, sort_order: int}>
     */
    private const ROOT_CATEGORY_DEFINITIONS = [
        'intro' => ['name' => '自己紹介', 'slug' => 'intro', 'sort_order' => 10],
        'past' => ['name' => '過去の自分', 'slug' => 'past-self', 'sort_order' => 20],
        'why' => ['name' => 'なぜ記憶管理？', 'slug' => 'why-memory', 'sort_order' => 30],
        'referral' => ['name' => '紹介してほしい業種', 'slug' => 'referral-industries', 'sort_order' => 40],
    ];

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
            [
                'name' => 'Default',
                'plan_key' => Tenant::PLAN_FREE,
                'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            ],
        );
        $tenant->ensurePublicId();

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'tenant_id' => $tenant->id,
                'role' => User::ROLE_OWNER,
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                'name' => 'メモリル デモユーザー',
                'password' => 'password',
                'secret_unlock_password' => 'secret-password',
            ],
        );
        $user->ensurePublicId();

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
        $categories = [];

        foreach (self::ROOT_CATEGORY_DEFINITIONS as $key => $definition) {
            $categories[$key] = $this->category(
                $tenant,
                $user,
                $definition['name'],
                $definition['slug'],
                $definition['sort_order'],
            );
        }

        $categories['current-self'] = $this->category($tenant, $user, '今の自分', 'current-self', 10, $categories['intro']);
        $categories['personality'] = $this->category($tenant, $user, '性格', 'personality', 20, $categories['intro']);
        $categories['black-history'] = $this->category($tenant, $user, '黒歴史', 'black-history', 10, $categories['past']);
        $categories['searching'] = $this->category($tenant, $user, '答え探し', 'searching', 20, $categories['past']);
        $categories['turning-point'] = $this->category($tenant, $user, '気づき', 'turning-point', 10, $categories['why']);
        $categories['memoriru-birth'] = $this->category($tenant, $user, 'メモリル誕生', 'memoriru-birth', 20, $categories['why']);
        $categories['business'] = $this->category($tenant, $user, '法人・メンタルヘルス', 'business-mental-health', 10, $categories['referral']);
        $categories['life-events'] = $this->category($tenant, $user, '人生記録・家族', 'life-events-family', 20, $categories['referral']);

        return $categories;
    }

    private function category(
        Tenant $tenant,
        User $user,
        string $name,
        string $slug,
        int $sortOrder,
        ?Category $parent = null
    ): Category {
        $category = Category::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $user->id,
            'slug' => $slug,
        ], [
            'parent_id' => $parent?->id,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
        $category->ensurePublicId();

        return $category;
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedMemories(Tenant $tenant, User $user, array $categories): void
    {
        foreach ($this->memoryDefinitions() as $memory) {
            $categoryKey = $memory['category_key'];
            unset($memory['category_key']);

            $this->memory($tenant, $user, $categories[$categoryKey], $memory);
        }
    }

    /**
     * @return list<array{
     *     category_key: string,
     *     title: string,
     *     body: string,
     *     period_key: string,
     *     occurred_on: string,
     *     emotion_label: string,
     *     emotion_intensity: int,
     *     visibility: string,
     *     metadata: array<string, mixed>,
     *     tags: list<string>
     * }>
     */
    private function memoryDefinitions(): array
    {
        return [
            [
                'category_key' => 'current-self',
                'title' => '今は毎日が楽しい',
                'body' => '現実が特別に変わったわけではない。でも見える景色が段違いに美しくなり、何をしていても楽しいと感じるようになった。',
                'period_key' => 'adult',
                'occurred_on' => '2026-06-25',
                'emotion_label' => '幸せ',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_SHARED,
                'metadata' => [
                    'emotion_scores' => ['幸せ' => 95, '安心' => 88],
                    'importance_score' => 0.94,
                    'beliefs' => ['現実は同じでも見え方は変わる'],
                    'chains' => ['現在地', '自己紹介', '4分プレゼン'],
                ],
                'tags' => ['現在', '自己紹介', '幸せ'],
            ],
            [
                'category_key' => 'personality',
                'title' => 'ご陽気者で、少しビビり',
                'body' => '今の自分はご陽気者で、慎重なところもある。少し前までは瞬間湯沸かし器のように怒ってしまう時期もあった。',
                'period_key' => 'adult',
                'occurred_on' => '2026-06-20',
                'emotion_label' => '安心',
                'emotion_intensity' => 4,
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'metadata' => [
                    'emotion_scores' => ['安心' => 82, '懐かしさ' => 55],
                    'importance_score' => 0.78,
                    'beliefs' => ['性格は固定ではなく、記憶の見方で変わる'],
                    'chains' => ['性格', '変化', '自己理解'],
                ],
                'tags' => ['性格', '変化', '自己理解'],
            ],
            [
                'category_key' => 'black-history',
                'title' => 'いじめ、離婚、パワハラ',
                'body' => '過去には、いじめ、策略的な離婚、職場でのパワハラなど、できれば見たくない記憶がいくつもあった。',
                'period_key' => 'adult',
                'occurred_on' => '2020-01-10',
                'emotion_label' => '切なさ',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_SECRET,
                'metadata' => [
                    'emotion_scores' => ['切なさ' => 96, '緊張' => 84],
                    'importance_score' => 0.98,
                    'beliefs' => ['見たくない記憶ほど人生の鍵を持っている'],
                    'chains' => ['黒歴史', '痛み', '転機'],
                ],
                'tags' => ['黒歴史', '秘密', '転機'],
            ],
            [
                'category_key' => 'black-history',
                'title' => '追い詰められてから向き合う大変さ',
                'body' => '問題が大きくなってから自分の記憶と感情に向き合うのは本当に大変だった。だから普段から残しておく意味がある。',
                'period_key' => 'adult',
                'occurred_on' => '2021-03-15',
                'emotion_label' => '緊張',
                'emotion_intensity' => 4,
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'metadata' => [
                    'emotion_scores' => ['緊張' => 86, '切なさ' => 74],
                    'importance_score' => 0.89,
                    'beliefs' => ['記憶管理は追い詰められる前の備えになる'],
                    'chains' => ['予防', '自己理解', '記録'],
                ],
                'tags' => ['予防', '記憶管理', '自己理解'],
            ],
            [
                'category_key' => 'searching',
                'title' => '自己啓発に5年励んだ',
                'body' => '自己啓発に5年取り組み、脳科学、心理学、量子論、古神道まで答えを探した。それでも本当の答えには届かなかった。',
                'period_key' => 'adult',
                'occurred_on' => '2022-07-01',
                'emotion_label' => '緊張',
                'emotion_intensity' => 4,
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'metadata' => [
                    'emotion_scores' => ['緊張' => 77, 'ワクワク' => 62],
                    'importance_score' => 0.82,
                    'beliefs' => ['外の知識だけでは自分の核心に届かないことがある'],
                    'chains' => ['自己啓発', '探求', '迷走'],
                ],
                'tags' => ['自己啓発', '心理学', '探求'],
            ],
            [
                'category_key' => 'turning-point',
                'title' => '答えは自分の記憶と感情にあった',
                'body' => '意外な答えは、自分の記憶の中にあった。当時の感情を認めたら、嘘のように心も身体も脳も軽くなった。',
                'period_key' => 'adult',
                'occurred_on' => '2023-11-18',
                'emotion_label' => '感動',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_SHARED,
                'metadata' => [
                    'emotion_scores' => ['感動' => 97, '安心' => 91],
                    'importance_score' => 1.0,
                    'beliefs' => ['黒歴史は幸せの鍵になる'],
                    'chains' => ['気づき', '感情', '解放'],
                ],
                'tags' => ['気づき', '感情', '解放'],
            ],
            [
                'category_key' => 'turning-point',
                'title' => '黒歴史は幸せの鍵だった',
                'body' => '消したい記憶を否定するのではなく、そこにあった感情を見つけることで、自分を責める時間が少しずつ終わっていった。',
                'period_key' => 'adult',
                'occurred_on' => '2024-02-12',
                'emotion_label' => '安心',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'metadata' => [
                    'emotion_scores' => ['安心' => 90, '感動' => 83],
                    'importance_score' => 0.92,
                    'beliefs' => ['否定していた記憶ほど自分を自由にする入口になる'],
                    'chains' => ['黒歴史', '幸せ', '自己受容'],
                ],
                'tags' => ['黒歴史', '自己受容', '幸せ'],
            ],
            [
                'category_key' => 'memoriru-birth',
                'title' => 'メモリルの誕生',
                'body' => '人が忘れていく膨大な大切な記憶を、当時の気持ちや画像と一緒に記録・管理できたら、もっと生きやすくなると考えた。',
                'period_key' => 'adult',
                'occurred_on' => '2025-05-01',
                'emotion_label' => 'ワクワク',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_SHARED,
                'metadata' => [
                    'emotion_scores' => ['ワクワク' => 94, '希望' => 88],
                    'importance_score' => 0.96,
                    'beliefs' => ['記憶が増えるほど分身体は自分を理解していく'],
                    'chains' => ['プロダクト', '記憶管理', '分身AI'],
                ],
                'tags' => ['メモリル', '分身AI', '記憶管理'],
            ],
            [
                'category_key' => 'memoriru-birth',
                'title' => '自分より自分を知る分身体へ',
                'body' => '記憶が増えるほど、メモリルは自分よりも自分を知っている優秀な分身体のような存在に育っていく。',
                'period_key' => 'adult',
                'occurred_on' => '2025-12-01',
                'emotion_label' => '希望',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'metadata' => [
                    'emotion_scores' => ['希望' => 93, 'ワクワク' => 86],
                    'importance_score' => 0.9,
                    'beliefs' => ['蓄積された記憶は未来の対話相手になる'],
                    'chains' => ['分身体', 'AI', '未来'],
                ],
                'tags' => ['分身体', 'AI', '未来'],
            ],
            [
                'category_key' => 'business',
                'title' => '企業のメンタルヘルスに',
                'body' => '社員の自己理解、新入社員の自己分析、メンタル不調の予防など、会社の中でも記憶と感情の整理は使える可能性がある。',
                'period_key' => 'adult',
                'occurred_on' => '2026-06-25',
                'emotion_label' => '達成感',
                'emotion_intensity' => 4,
                'visibility' => Memory::VISIBILITY_SHARED,
                'metadata' => [
                    'emotion_scores' => ['達成感' => 82, '希望' => 80],
                    'importance_score' => 0.84,
                    'beliefs' => ['人事やメンタルヘルス領域は最初の紹介先になりやすい'],
                    'chains' => ['法人', '人事', 'メンタルヘルス'],
                ],
                'tags' => ['人事', 'メンタルヘルス', '新入社員'],
            ],
            [
                'category_key' => 'life-events',
                'title' => '人生記録・家族の記憶に',
                'body' => '結婚、出産、子どもの成長記録、終活、家族史など、人生の節目を残すサービスとも相性がいい。',
                'period_key' => 'adult',
                'occurred_on' => '2026-06-25',
                'emotion_label' => '愛',
                'emotion_intensity' => 4,
                'visibility' => Memory::VISIBILITY_SHARED,
                'metadata' => [
                    'emotion_scores' => ['愛' => 86, '幸せ' => 78],
                    'importance_score' => 0.82,
                    'beliefs' => ['家族に渡したい記憶は人生の価値を映す'],
                    'chains' => ['終活', '子育て', '家族'],
                ],
                'tags' => ['終活', '子育て', '家族'],
            ],
            [
                'category_key' => 'life-events',
                'title' => '紹介してほしい人',
                'body' => '人事・メンタルヘルス関係、終活や人生記録に関わる方、家族や子育ての記録に関わる方を紹介してほしい。',
                'period_key' => 'adult',
                'occurred_on' => '2026-06-25',
                'emotion_label' => '希望',
                'emotion_intensity' => 5,
                'visibility' => Memory::VISIBILITY_SHARED,
                'metadata' => [
                    'emotion_scores' => ['希望' => 90, 'ワクワク' => 76],
                    'importance_score' => 0.88,
                    'beliefs' => ['最後は紹介依頼を一つに絞ると伝わりやすい'],
                    'chains' => ['紹介依頼', 'BNI', '次の一歩'],
                ],
                'tags' => ['紹介依頼', 'BNI', 'プレゼン'],
            ],
        ];
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
        $memory->ensurePublicId();

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
