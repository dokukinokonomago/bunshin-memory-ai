<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TagNameNormalizer;
use Illuminate\Database\Seeder;

class MotivationGraphTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default',
                'plan_key' => Tenant::PLAN_FREE,
                'subscription_status' => Tenant::SUBSCRIPTION_STATUS_ACTIVE,
            ],
        );

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'tenant_id' => $tenant->id,
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                'name' => 'Admin User',
                'password' => 'password',
                'secret_unlock_password' => 'secret-password',
            ],
        );

        if ((int) $user->tenant_id !== (int) $tenant->id) {
            $user->forceFill(['tenant_id' => $tenant->id])->save();
        }

        if (! $user->hasActiveAccount()) {
            $user->forceFill(['account_status' => User::ACCOUNT_STATUS_ACTIVE])->save();
        }

        if (! $user->hasSecretUnlockPassword()) {
            $user->forceFill(['secret_unlock_password' => 'secret-password'])->save();
        }

        $user->personalAccessTokens()->updateOrCreate(
            ['name' => 'local-dev'],
            [
                'token' => hash('sha256', 'local-dev-token'),
                'abilities' => ['*'],
                'expires_at' => now()->addYears(10),
            ],
        );

        $root = $this->category($tenant, $user, 'モチベーショングラフ', 'motivation-graph', 5);
        $stages = $this->stageCategories($tenant, $user, $root);

        $this->replaceExistingTestMemories($tenant, $user);

        foreach ($this->memories() as $index => $item) {
            $stage = $stages[$item['stage_slug']];
            $memory = Memory::query()->create([
                'tenant_id' => $tenant->id,
                'owner_user_id' => $user->id,
                'category_id' => $stage->id,
                'period_key' => $item['period'],
                'occurred_on' => null,
                'title' => $item['title'],
                'body' => $this->body($item),
                'emotion_label' => $item['emotion'],
                'emotion_intensity' => max(1, min(5, (int) ceil($item['motivation'] / 2))),
                'visibility' => Memory::VISIBILITY_PRIVATE,
                'source' => 'motivation_graph_test_data',
                'metadata' => $this->scrub([
                    'source_sheet' => '過去から今の振り返り｜モチベーショングラフ',
                    'age_range' => $item['age'],
                    'life_stage' => $stage->name,
                    'motivation_score' => $item['motivation'],
                    'importance_score' => $item['motivation'] / 10,
                    'emotion_scores' => $item['emotion_scores'],
                    'beliefs' => $item['insight'] === '' ? [] : [$item['insight']],
                    'chains' => array_values(array_filter([$item['turning_point'] ?? null])),
                    'item_index' => $index + 1,
                ]),
            ]);

            $tagIds = collect(array_merge(
                ['モチベーショングラフ', '人生振り返り', $item['age'], $stage->name],
                $item['tags'],
            ))
                ->unique()
                ->map(fn (string $tagName): int => $this->tag($tenant, $tagName)->id)
                ->all();

            $memory->tags()->sync($tagIds);
        }
    }

    /**
     * @return array<string, Category>
     */
    private function stageCategories(Tenant $tenant, User $user, Category $root): array
    {
        $definitions = [
            ['幼少期', 'motivation-childhood', 10],
            ['小学校', 'motivation-elementary-school', 20],
            ['中学校', 'motivation-junior-high', 30],
            ['高校', 'motivation-high-school', 40],
            ['大学', 'motivation-university', 50],
            ['社会人 前期', 'motivation-early-career', 60],
            ['今', 'motivation-now', 70],
        ];

        return collect($definitions)
            ->mapWithKeys(fn (array $definition): array => [
                $definition[1] => $this->category(
                    $tenant,
                    $user,
                    $definition[0],
                    $definition[1],
                    $definition[2],
                    $root,
                ),
            ])
            ->all();
    }

    private function replaceExistingTestMemories(Tenant $tenant, User $user): void
    {
        Memory::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('owner_user_id', $user->id)
            ->whereIn('source', ['manual_test_data', 'motivation_graph_test_data'])
            ->get()
            ->each(function (Memory $memory): void {
                $memory->tags()->detach();
                $memory->forceDelete();
            });
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

    /**
     * @param  array<string, mixed>  $item
     */
    private function body(array $item): string
    {
        return collect([
            $this->section('出来事（事実）', $item['fact']),
            $this->section('感情・気持ち', $item['feeling']),
            $this->section('発見・気づき', $item['insight']),
            $this->section('転換点・きっかけ', $item['turning_point'] ?? ''),
        ])
            ->filter()
            ->implode("\n\n");
    }

    private function section(string $label, string $value): string
    {
        $value = trim($value);

        return $value === '' ? '' : "【{$label}】\n{$value}";
    }

    private function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_scrub($value, 'UTF-8');
        }

        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->mapWithKeys(fn (mixed $item, int|string $key): array => [
                is_string($key) ? mb_scrub($key, 'UTF-8') : $key => $this->scrub($item),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memories(): array
    {
        return [
            [
                'age' => '0〜6歳',
                'stage_slug' => 'motivation-childhood',
                'period' => 'childhood',
                'motivation' => 7,
                'title' => '0〜6歳｜受験のための習い事',
                'fact' => '小学校受験に向けて習い事をしていた。',
                'feeling' => '当時の感情はあまり覚えていない。',
                'insight' => '基礎を早くから積み上げる経験になった。',
                'turning_point' => '',
                'emotion' => '成長',
                'emotion_scores' => ['成長' => 70, '基礎づくり' => 68],
                'tags' => ['受験', '習い事', '基礎づくり'],
            ],
            [
                'age' => '0〜6歳',
                'stage_slug' => 'motivation-childhood',
                'period' => 'childhood',
                'motivation' => 7,
                'title' => '0〜6歳｜箸と利き手の練習',
                'fact' => '箸の使い方や右利きに変える練習をした。',
                'feeling' => '母と何度も練習した記憶がある。',
                'insight' => '細かい基礎動作は訓練で伸ばせると知った。',
                'turning_point' => '小学校受験に受かった。',
                'emotion' => '努力',
                'emotion_scores' => ['努力' => 72, '達成感' => 70],
                'tags' => ['箸', '母', '小学校受験'],
            ],
            [
                'age' => '7〜12歳',
                'stage_slug' => 'motivation-elementary-school',
                'period' => 'elementary_school',
                'motivation' => 8,
                'title' => '7〜12歳｜両親の離婚と佐賀への転校',
                'fact' => '両親の離婚をきっかけに、東京から佐賀へ転校した。',
                'feeling' => '辛いことも、人の気持ちがわかるようになる経験だと思えた。',
                'insight' => '大きな環境変化は、他人の痛みに気づく入口になった。',
                'turning_point' => '佐賀に戻ってきた意味も含めて、全て繋がっていると感じた。',
                'emotion' => '強さ',
                'emotion_scores' => ['強さ' => 82, '痛み' => 70],
                'tags' => ['転校', '佐賀', '家族'],
            ],
            [
                'age' => '7〜12歳',
                'stage_slug' => 'motivation-elementary-school',
                'period' => 'elementary_school',
                'motivation' => 8,
                'title' => '7〜12歳｜祖母との生活',
                'fact' => '祖母との生活が始まり、家族に支えられた。',
                'feeling' => '家族が笑ってくれることが嬉しかった。',
                'insight' => '近くで支えてくれる人の存在が安心感になる。',
                'turning_point' => '',
                'emotion' => '安心',
                'emotion_scores' => ['安心' => 80, '感謝' => 76],
                'tags' => ['祖母', '家族', '安心'],
            ],
            [
                'age' => '7〜12歳',
                'stage_slug' => 'motivation-elementary-school',
                'period' => 'elementary_school',
                'motivation' => 8,
                'title' => '7〜12歳｜飲み込めなくなった3ヶ月',
                'fact' => '転校と同時期に3ヶ月ほど飲み込めなくなり、ゼリー生活になった。',
                'feeling' => '身体にも出るほど大きなストレスだった。',
                'insight' => '環境変化の負荷は心だけでなく身体にも出る。',
                'turning_point' => 'さまざまな経験でメンタルが強くなった。',
                'emotion' => '不安',
                'emotion_scores' => ['不安' => 78, '回復' => 62],
                'tags' => ['転校', '体調', 'ゼリー生活'],
            ],
            [
                'age' => '7〜12歳',
                'stage_slug' => 'motivation-elementary-school',
                'period' => 'elementary_school',
                'motivation' => 8,
                'title' => '7〜12歳｜男子生徒からのいじめ',
                'fact' => '男子生徒からいじめを受けた。',
                'feeling' => '痛い経験だったが、人の気持ちを理解する材料にもなった。',
                'insight' => '辛い経験は、他人の気持ちを想像する力に変えられる。',
                'turning_point' => '',
                'emotion' => '痛み',
                'emotion_scores' => ['痛み' => 82, '共感' => 74],
                'tags' => ['いじめ', '共感', 'メンタル'],
            ],
            [
                'age' => '7〜12歳',
                'stage_slug' => 'motivation-elementary-school',
                'period' => 'elementary_school',
                'motivation' => 8,
                'title' => '7〜12歳｜絵画で県の特別賞',
                'fact' => '絵画で県の特別賞をもらった。',
                'feeling' => '楽しい思い出のひとつとして残っている。',
                'insight' => '表現することは自分の強みになり得る。',
                'turning_point' => '',
                'emotion' => '達成感',
                'emotion_scores' => ['達成感' => 80, '楽しさ' => 72],
                'tags' => ['絵画', '特別賞', '表現'],
            ],
            [
                'age' => '13〜15歳',
                'stage_slug' => 'motivation-junior-high',
                'period' => 'junior_high',
                'motivation' => 7,
                'title' => '13〜15歳｜両親の再婚と家族の変化',
                'fact' => '両親が再婚し、妹と弟ができた。',
                'feeling' => '家族関係の変化に戸惑いがあった。',
                'insight' => '家族の形が変わると、自分の居場所も揺れやすい。',
                'turning_point' => '家族との関係が悪くなるきっかけにもなった。',
                'emotion' => '葛藤',
                'emotion_scores' => ['葛藤' => 78, '戸惑い' => 70],
                'tags' => ['家族', '再婚', '妹弟'],
            ],
            [
                'age' => '13〜15歳',
                'stage_slug' => 'motivation-junior-high',
                'period' => 'junior_high',
                'motivation' => 7,
                'title' => '13〜15歳｜大好きな祖父との別れ',
                'fact' => '大好きな祖父が亡くなった。',
                'feeling' => '感謝や思っていることは伝えないと届かないと感じた。',
                'insight' => 'いつ伝えられなくなるかわからないから、後悔のないように生きる。',
                'turning_point' => '後悔する生き方はダメだと、人生の軸が固まった。',
                'emotion' => '喪失',
                'emotion_scores' => ['喪失' => 86, '感謝' => 80],
                'tags' => ['祖父', '別れ', '後悔しない'],
            ],
            [
                'age' => '13〜15歳',
                'stage_slug' => 'motivation-junior-high',
                'period' => 'junior_high',
                'motivation' => 7,
                'title' => '13〜15歳｜家では引きこもりになる',
                'fact' => '色々な問題が起きて家族と対立し、家では引きこもりになった。',
                'feeling' => 'なんで誰も味方してくれないんだろうと感じた。',
                'insight' => '味方がいない感覚は、自立への圧力にもなる。',
                'turning_point' => '大抵はなんとかなると自立していく。',
                'emotion' => '孤独',
                'emotion_scores' => ['孤独' => 82, '自立' => 72],
                'tags' => ['家族', '孤独', '自立'],
            ],
            [
                'age' => '13〜15歳',
                'stage_slug' => 'motivation-junior-high',
                'period' => 'junior_high',
                'motivation' => 7,
                'title' => '13〜15歳｜学業に力を入れる',
                'fact' => '学業に力を入れた。',
                'feeling' => '勉強を頑張っても、1位じゃないと意味がないと思っていた。',
                'insight' => '成果基準が高いほど、自分を追い込みやすい。',
                'turning_point' => '',
                'emotion' => '焦り',
                'emotion_scores' => ['焦り' => 76, '努力' => 78],
                'tags' => ['学業', '努力', '完璧主義'],
            ],
            [
                'age' => '13〜15歳',
                'stage_slug' => 'motivation-junior-high',
                'period' => 'junior_high',
                'motivation' => 7,
                'title' => '13〜15歳｜海外で世界が広がる',
                'fact' => 'ハワイとフロリダに行き、オーストラリアの修学旅行も経験した。',
                'feeling' => '海外が大好きで、英語をたくさん勉強したいと思った。',
                'insight' => '違う世界を見ると、今の悩みが小さく見える。',
                'turning_point' => '大抵のことはなんとかなると感じた。',
                'emotion' => '憧れ',
                'emotion_scores' => ['憧れ' => 86, '好奇心' => 84],
                'tags' => ['海外', '英語', '世界が広がる'],
            ],
            [
                'age' => '13〜15歳',
                'stage_slug' => 'motivation-junior-high',
                'period' => 'junior_high',
                'motivation' => 7,
                'title' => '13〜15歳｜事件に立ち向かう',
                'fact' => '先生も絡んだ事件があり、立ち向かった。',
                'feeling' => '怖いことに遭遇しても、目の前の悩みはちっぽけだと感じた。',
                'insight' => '怖さを越えて動くと、自分の基準が変わる。',
                'turning_point' => '',
                'emotion' => '勇気',
                'emotion_scores' => ['勇気' => 82, '恐怖' => 70],
                'tags' => ['事件', '先生', '勇気'],
            ],
            [
                'age' => '13〜15歳',
                'stage_slug' => 'motivation-junior-high',
                'period' => 'junior_high',
                'motivation' => 7,
                'title' => '13〜15歳｜歯科医師の夢を諦める',
                'fact' => '数学が苦手で、歯科医師になる夢を諦めた。',
                'feeling' => '得意不得意で進路の見え方が変わった。',
                'insight' => '夢は現実の得意不得意と向き合いながら形を変える。',
                'turning_point' => '',
                'emotion' => '受容',
                'emotion_scores' => ['受容' => 70, '悔しさ' => 66],
                'tags' => ['進路', '数学', '夢'],
            ],
            [
                'age' => '16〜18歳',
                'stage_slug' => 'motivation-high-school',
                'period' => 'high_school',
                'motivation' => 6,
                'title' => '16〜18歳｜バイオリンコンクール銀賞',
                'fact' => 'バイオリンのコンクールで銀賞を取った。',
                'feeling' => '挑戦するのはいいことだと感じた。',
                'insight' => '努力を形にする経験は、自分の挑戦心を支える。',
                'turning_point' => '',
                'emotion' => '達成感',
                'emotion_scores' => ['達成感' => 78, '挑戦' => 76],
                'tags' => ['高校', 'バイオリン', 'コンクール'],
            ],
            [
                'age' => '16〜18歳',
                'stage_slug' => 'motivation-high-school',
                'period' => 'high_school',
                'motivation' => 6,
                'title' => '16〜18歳｜応援団に入る',
                'fact' => '応援団に入った。',
                'feeling' => '人がやらないことをやりたい、目立ちたいと思った。',
                'insight' => '目立つ場に出ることが、自分のエネルギーになる。',
                'turning_point' => '',
                'emotion' => '高揚',
                'emotion_scores' => ['高揚' => 78, '表現' => 74],
                'tags' => ['応援団', '目立ちたい', '表現'],
            ],
            [
                'age' => '16〜18歳',
                'stage_slug' => 'motivation-high-school',
                'period' => 'high_school',
                'motivation' => 6,
                'title' => '16〜18歳｜夢を背負ってくれる親友',
                'fact' => '一緒に夢を叶えたい親友に出会った。',
                'feeling' => 'ここまで自分の気持ちを一緒に背負ってくれる人がいるんだと感じた。',
                'insight' => '夢を共有できる人がいると、未来への熱が強くなる。',
                'turning_point' => '起業したい、何かやりたい気持ちが強くなる。',
                'emotion' => '信頼',
                'emotion_scores' => ['信頼' => 82, '起業意欲' => 76],
                'tags' => ['親友', '夢', '起業意欲'],
            ],
            [
                'age' => '16〜18歳',
                'stage_slug' => 'motivation-high-school',
                'period' => 'high_school',
                'motivation' => 6,
                'title' => '16〜18歳｜初恋に告白して振られる',
                'fact' => '初恋の相手に告白して振られた。',
                'feeling' => '好きなら諦めない、好きなものは好きだと思った。',
                'insight' => '感情を出す経験は、自分の本音を確認する機会になる。',
                'turning_point' => '',
                'emotion' => '切なさ',
                'emotion_scores' => ['切なさ' => 76, '本音' => 70],
                'tags' => ['初恋', '告白', '本音'],
            ],
            [
                'age' => '19〜22歳',
                'stage_slug' => 'motivation-university',
                'period' => 'university',
                'motivation' => 10,
                'title' => '19〜22歳｜ダイビングと掛け持ちバイト',
                'fact' => 'ダイビングサークルに入り、お金と経験のために掛け持ちバイトをした。',
                'feeling' => '新しいことを見ること、経験することが大好きだった。',
                'insight' => '経験量を増やすほど、自分の興味が見えやすくなる。',
                'turning_point' => '',
                'emotion' => '好奇心',
                'emotion_scores' => ['好奇心' => 96, '行動力' => 90],
                'tags' => ['大学', 'ダイビング', 'バイト', '経験'],
            ],
            [
                'age' => '19〜22歳',
                'stage_slug' => 'motivation-university',
                'period' => 'university',
                'motivation' => 10,
                'title' => '19〜22歳｜10社ほどのバイト経験',
                'fact' => '結果的にバイトを10社ほど経験した。',
                'feeling' => 'とにかく楽しかった。',
                'insight' => '出会う人と環境で世界が変わる。',
                'turning_point' => '沢山経験してこそ、自己分析ができると気づいた。',
                'emotion' => '楽しさ',
                'emotion_scores' => ['楽しさ' => 96, '自己分析' => 88],
                'tags' => ['バイト', '自己分析', '環境'],
            ],
            [
                'age' => '19〜22歳',
                'stage_slug' => 'motivation-university',
                'period' => 'university',
                'motivation' => 10,
                'title' => '19〜22歳｜脾臓の入院',
                'fact' => '急に脾臓が大きくなり、死ぬかもしれないと言われて入院した。',
                'feeling' => '家族にすごく支えられていると感じた。',
                'insight' => '支えてくれる家族がいるからこそ、恩返ししたいと思った。',
                'turning_point' => '',
                'emotion' => '感謝',
                'emotion_scores' => ['感謝' => 90, '不安' => 80],
                'tags' => ['入院', '家族', '恩返し'],
            ],
            [
                'age' => '19〜22歳',
                'stage_slug' => 'motivation-university',
                'period' => 'university',
                'motivation' => 10,
                'title' => '19〜22歳｜Hands UPと訪問営業',
                'fact' => 'Hands UPに入り、訪問営業を経験した。',
                'feeling' => '営業やインターンを通して世界が広がった。',
                'insight' => '行動することで、人と環境が変わっていく。',
                'turning_point' => '',
                'emotion' => '行動力',
                'emotion_scores' => ['行動力' => 94, '営業' => 86],
                'tags' => ['Hands UP', '訪問営業', 'インターン'],
            ],
            [
                'age' => '19〜22歳',
                'stage_slug' => 'motivation-university',
                'period' => 'university',
                'motivation' => 10,
                'title' => '19〜22歳｜海外に何度も行く',
                'fact' => '3回目のフロリダ、ベトナム、韓国に行った。',
                'feeling' => '海外が好きで、もっと色々な世界を見たいと思った。',
                'insight' => '外の世界を見ることが、自分の視野を広げる。',
                'turning_point' => '人生の幅が広がる。',
                'emotion' => '憧れ',
                'emotion_scores' => ['憧れ' => 92, '好奇心' => 94],
                'tags' => ['海外', 'フロリダ', 'ベトナム', '韓国'],
            ],
            [
                'age' => '19〜22歳',
                'stage_slug' => 'motivation-university',
                'period' => 'university',
                'motivation' => 10,
                'title' => '19〜22歳｜YouTubeとダンスへの挑戦',
                'fact' => 'YouTubeとダンスサークルに挑戦した。',
                'feeling' => '新しいことへの挑戦が楽しかった。',
                'insight' => 'やってみることで、自分に合うものが見えてくる。',
                'turning_point' => '',
                'emotion' => '挑戦',
                'emotion_scores' => ['挑戦' => 88, '楽しさ' => 86],
                'tags' => ['YouTube', 'ダンス', '挑戦'],
            ],
            [
                'age' => '19〜22歳',
                'stage_slug' => 'motivation-university',
                'period' => 'university',
                'motivation' => 10,
                'title' => '19〜22歳｜コロナで起業を考える',
                'fact' => 'コロナでCAではない興味のあるものを探し、起業したいとなった。',
                'feeling' => '自己分析が非常に大事だと感じた。',
                'insight' => '環境が止まった時こそ、自分の本当の興味を見直せる。',
                'turning_point' => '起業したい気持ちが明確になる。',
                'emotion' => '起業意欲',
                'emotion_scores' => ['起業意欲' => 92, '自己分析' => 90],
                'tags' => ['コロナ', '起業', '自己分析'],
            ],
            [
                'age' => '23〜25歳',
                'stage_slug' => 'motivation-early-career',
                'period' => 'adult',
                'motivation' => 10,
                'title' => '23〜25歳｜新卒研修1位',
                'fact' => 'ネオキャリアに入社し、新卒研修で1位を獲得した。',
                'feeling' => '営業がすごく楽しく、仕事が大好きだった。',
                'insight' => '楽しんでいる人には誰も勝てない。',
                'turning_point' => '',
                'emotion' => '達成感',
                'emotion_scores' => ['達成感' => 94, '仕事愛' => 90],
                'tags' => ['ネオキャリア', '新卒研修', '営業'],
            ],
            [
                'age' => '23〜25歳',
                'stage_slug' => 'motivation-early-career',
                'period' => 'adult',
                'motivation' => 10,
                'title' => '23〜25歳｜一生物の同期',
                'fact' => '一生物の同期に出会った。',
                'feeling' => '仲間がすごく支えになった。',
                'insight' => '人に恵まれている環境は、仕事の熱量を上げる。',
                'turning_point' => '',
                'emotion' => '仲間',
                'emotion_scores' => ['仲間' => 92, '感謝' => 86],
                'tags' => ['同期', '仲間', '支え'],
            ],
            [
                'age' => '23〜25歳',
                'stage_slug' => 'motivation-early-career',
                'period' => 'adult',
                'motivation' => 10,
                'title' => '23〜25歳｜佐賀に帰る決断',
                'fact' => '夢を叶えたくて佐賀に帰った。',
                'feeling' => '遠距離恋愛もありながら、自分の夢を選ぼうとした。',
                'insight' => '他人軸ではなく、自分の軸で決めることが大事。',
                'turning_point' => '佐賀へ戻る。',
                'emotion' => '決断',
                'emotion_scores' => ['決断' => 86, '夢' => 88],
                'tags' => ['佐賀', '夢', '決断'],
            ],
            [
                'age' => '23〜25歳',
                'stage_slug' => 'motivation-early-career',
                'period' => 'adult',
                'motivation' => 10,
                'title' => '23〜25歳｜1000万円を売り上げる',
                'fact' => '2社目で1000万円を売り上げた。',
                'feeling' => '仕事を全力で楽しめていた。',
                'insight' => '時間投資と量をこなすことが成果につながる。',
                'turning_point' => '',
                'emotion' => '情熱',
                'emotion_scores' => ['情熱' => 96, '達成感' => 94],
                'tags' => ['売上', '1000万円', '営業'],
            ],
            [
                'age' => '23〜25歳',
                'stage_slug' => 'motivation-early-career',
                'period' => 'adult',
                'motivation' => 10,
                'title' => '23〜25歳｜ミスコンへの挑戦',
                'fact' => '仕事と同時にミスコンにも挑戦した。',
                'feeling' => '挑戦することで自分の可能性を広げたかった。',
                'insight' => '仕事以外の挑戦も、自分の表現力を広げる。',
                'turning_point' => '',
                'emotion' => '挑戦',
                'emotion_scores' => ['挑戦' => 88, '表現' => 82],
                'tags' => ['ミスコン', '挑戦', '表現'],
            ],
            [
                'age' => '23〜25歳',
                'stage_slug' => 'motivation-early-career',
                'period' => 'adult',
                'motivation' => 10,
                'title' => '23〜25歳｜恋愛で会社を辞める',
                'fact' => '上司と付き合い、会社を辞めた。',
                'feeling' => '好きな人との関係に大きく影響された。',
                'insight' => '他人軸で決めた決断は間違いやすい。',
                'turning_point' => '好きな人を切り離すことを学ぶ。',
                'emotion' => '揺らぎ',
                'emotion_scores' => ['揺らぎ' => 82, '学び' => 76],
                'tags' => ['恋愛', '退職', '他人軸'],
            ],
            [
                'age' => '23〜25歳',
                'stage_slug' => 'motivation-early-career',
                'period' => 'adult',
                'motivation' => 10,
                'title' => '23〜25歳｜出戻り後に再び1位',
                'fact' => '別れて出戻り、再度1000万円を作り1位を獲得した。',
                'feeling' => '最初はできなくても、続けていれば実ると感じた。',
                'insight' => '継続と量が、再起後の成果につながる。',
                'turning_point' => '',
                'emotion' => '再起',
                'emotion_scores' => ['再起' => 94, '達成感' => 96],
                'tags' => ['再起', '1位', '継続'],
            ],
            [
                'age' => '現在',
                'stage_slug' => 'motivation-now',
                'period' => 'adult',
                'motivation' => 8,
                'title' => '現在｜自分を取り戻し始める',
                'fact' => '自分を見失っていたが、やっと取り戻し始めた。',
                'feeling' => '自分に自信がなくなっていた。',
                'insight' => '一度見失っても、取り戻す過程は始められる。',
                'turning_point' => '',
                'emotion' => '再起',
                'emotion_scores' => ['再起' => 82, '自信回復' => 70],
                'tags' => ['現在', '再起', '自信'],
            ],
            [
                'age' => '現在',
                'stage_slug' => 'motivation-now',
                'period' => 'adult',
                'motivation' => 8,
                'title' => '現在｜同じ会社で浮気される',
                'fact' => '同じ会社で浮気され、居づらくなった。',
                'feeling' => '一歩踏み出すことが怖くなった。',
                'insight' => 'ダメだとわかったら、その人は切り離すことも必要。',
                'turning_point' => 'たまには諦めも必要だと学ぶ。',
                'emotion' => '痛み',
                'emotion_scores' => ['痛み' => 84, '決断' => 74],
                'tags' => ['恋愛', '会社', '切り離す'],
            ],
            [
                'age' => '現在',
                'stage_slug' => 'motivation-now',
                'period' => 'adult',
                'motivation' => 8,
                'title' => '現在｜友達と同棲',
                'fact' => '友達と同棲した。',
                'feeling' => '辛い時に寄り添ってくれた人の存在を見た。',
                'insight' => '本当に支えてくれる人が誰かをきちんと見る。',
                'turning_point' => '',
                'emotion' => '安心',
                'emotion_scores' => ['安心' => 78, '感謝' => 80],
                'tags' => ['友達', '同棲', '支え'],
            ],
            [
                'age' => '現在',
                'stage_slug' => 'motivation-now',
                'period' => 'adult',
                'motivation' => 8,
                'title' => '現在｜家業が危うくなる',
                'fact' => '家業が危うくなった。',
                'feeling' => '周りの意見もきちんと聞く必要を感じた。',
                'insight' => '思い込みはしてはいけない。',
                'turning_point' => '',
                'emotion' => '危機感',
                'emotion_scores' => ['危機感' => 78, '学び' => 76],
                'tags' => ['家業', '危機', '思い込み'],
            ],
            [
                'age' => '現在',
                'stage_slug' => 'motivation-now',
                'period' => 'adult',
                'motivation' => 8,
                'title' => '現在｜初海外一人旅',
                'fact' => '初めて海外一人旅をした。',
                'feeling' => '一歩踏み出すことの怖さと価値を感じた。',
                'insight' => 'お金は経験に投資し、一歩踏み出すことが大事。',
                'turning_point' => '経験への投資を選ぶ。',
                'emotion' => '挑戦',
                'emotion_scores' => ['挑戦' => 86, '自由' => 80],
                'tags' => ['一人旅', '海外', '経験投資'],
            ],
        ];
    }
}
