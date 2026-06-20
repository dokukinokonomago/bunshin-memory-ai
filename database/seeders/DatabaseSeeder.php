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
        'music' => ['name' => '音楽', 'slug' => 'music', 'sort_order' => 10],
        'school' => ['name' => '学校', 'slug' => 'school', 'sort_order' => 20],
        'family' => ['name' => '家族', 'slug' => 'family', 'sort_order' => 30],
        'travel' => ['name' => '旅行', 'slug' => 'travel', 'sort_order' => 40],
        'work' => ['name' => '仕事', 'slug' => 'work', 'sort_order' => 50],
        'friends' => ['name' => '友達', 'slug' => 'friends', 'sort_order' => 60],
        'hobby' => ['name' => '趣味', 'slug' => 'hobby', 'sort_order' => 70],
        'food' => ['name' => '食', 'slug' => 'food', 'sort_order' => 80],
        'love' => ['name' => '恋愛', 'slug' => 'love', 'sort_order' => 90],
        'future' => ['name' => '未来', 'slug' => 'future', 'sort_order' => 100],
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
                'name' => 'Admin User',
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

        $categories['mrchildren'] = $this->category($tenant, $user, 'Mr.Children', 'mrchildren', 10, $categories['music']);
        $categories['band'] = $this->category($tenant, $user, 'バンド', 'band', 20, $categories['music']);
        $categories['high-school'] = $this->category($tenant, $user, '高校', 'high-school', 10, $categories['school']);
        $categories['club'] = $this->category($tenant, $user, '部活', 'club', 20, $categories['school']);
        $categories['home'] = $this->category($tenant, $user, '実家', 'home', 10, $categories['family']);

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
        foreach ($this->rootMemoryDefinitions() as $rootKey => $memories) {
            foreach ($memories as $memory) {
                $categoryKey = $memory['category_key'] ?? $rootKey;
                unset($memory['category_key']);

                $this->memory($tenant, $user, $categories[$categoryKey], $memory);
            }
        }

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
     * @return array<string, list<array{
     *     title: string,
     *     body: string,
     *     period_key: string,
     *     occurred_on: string,
     *     emotion_label: string,
     *     emotion_intensity: int,
     *     visibility: string,
     *     metadata: array<string, mixed>,
     *     tags: list<string>,
     *     category_key?: string
     * }>>
     */
    private function rootMemoryDefinitions(): array
    {
        return [
            'music' => [
                [
                    'category_key' => 'mrchildren',
                    'title' => '雨の帰り道で聴いた終わりなき旅',
                    'body' => '自転車で帰る途中、雨宿りしたコンビニの軒先でイヤホン越しに聴いた。歌詞が妙に自分に刺さった。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-06-03',
                    'emotion_label' => '感動',
                    'emotion_intensity' => 5,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['感動' => 94, '希望' => 77],
                        'importance_score' => 0.92,
                        'beliefs' => ['音楽は気分だけでなく景色ごと覚えさせる'],
                        'chains' => ['雨', '帰り道', 'イヤホン'],
                    ],
                    'tags' => ['音楽', '雨', '高校'],
                ],
                [
                    'category_key' => 'band',
                    'title' => '初めてアンプを買った日',
                    'body' => '中古楽器店で予算ぎりぎりのアンプを買った。家に持ち帰る途中ずっとにやけていた。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-08-20',
                    'emotion_label' => 'ワクワク',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['ワクワク' => 90, '達成感' => 70],
                        'importance_score' => 0.81,
                        'beliefs' => ['道具を手に入れると覚悟も少し強くなる'],
                        'chains' => ['楽器店', 'アンプ', '放課後'],
                    ],
                    'tags' => ['バンド', '買い物', '青春'],
                ],
                [
                    'title' => '深夜ラジオの録音テープ',
                    'body' => '好きな曲が流れるまで起きて、カセットに録音していた。曲間のトークまで含めて宝物だった。',
                    'period_key' => 'junior_high',
                    'occurred_on' => '2002-11-15',
                    'emotion_label' => '懐かしさ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['懐かしさ' => 88, '楽しさ' => 73],
                        'importance_score' => 0.79,
                        'beliefs' => ['少し手間のかかった記録ほど残る'],
                        'chains' => ['ラジオ', '夜更かし', 'カセット'],
                    ],
                    'tags' => ['音楽', 'ラジオ', '中学'],
                ],
                [
                    'title' => 'ライブハウスの床の振動',
                    'body' => '初めて行ったライブハウスで、音より先に床の振動に圧倒された。帰り道もしばらく耳が熱かった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2012-05-27',
                    'emotion_label' => '興奮',
                    'emotion_intensity' => 5,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['興奮' => 93, '驚き' => 71],
                        'importance_score' => 0.87,
                        'beliefs' => ['体感した音は記録より先に身体へ残る'],
                        'chains' => ['ライブ', '振動', '夜'],
                    ],
                    'tags' => ['音楽', 'ライブ', '体験'],
                ],
                [
                    'title' => '受験前に作った集中プレイリスト',
                    'body' => '歌詞が少なくて気持ちが散らない曲ばかり集めた。再生順まで何度も調整した。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2006-01-18',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 76, '集中' => 81],
                        'importance_score' => 0.72,
                        'beliefs' => ['音の並び方で頭の働き方が変わる'],
                        'chains' => ['受験', 'プレイリスト', '机'],
                    ],
                    'tags' => ['音楽', '受験', '勉強'],
                ],
            ],
            'school' => [
                [
                    'category_key' => 'high-school',
                    'title' => '朝礼前の教室の静けさ',
                    'body' => '誰もまだ本気で話し始めていない教室の空気が好きだった。窓から入る光だけで一日が始まった感じがした。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2004-04-16',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 74, '懐かしさ' => 69],
                        'importance_score' => 0.68,
                        'beliefs' => ['大きな出来事より日常の空気が長く残る'],
                        'chains' => ['教室', '朝', '窓際'],
                    ],
                    'tags' => ['学校', '教室', '高校'],
                ],
                [
                    'category_key' => 'club',
                    'title' => '試合後にみんなで飲んだ紙パックのジュース',
                    'body' => '勝っても負けても、試合後に飲むジュースの甘さだけは同じだった。あの時間が一番ほっとした。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-09-10',
                    'emotion_label' => '達成感',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['達成感' => 84, '安心' => 67],
                        'importance_score' => 0.75,
                        'beliefs' => ['頑張ったあとの小さな儀式が記憶を固める'],
                        'chains' => ['部活', '試合', 'ジュース'],
                    ],
                    'tags' => ['学校', '部活', '仲間'],
                ],
                [
                    'title' => '移動教室の廊下のざわつき',
                    'body' => 'チャイムが鳴ったあと、みんなで急ぎながらも少し浮き足立っていた。日常の中の小さなイベントだった。',
                    'period_key' => 'junior_high',
                    'occurred_on' => '2002-06-14',
                    'emotion_label' => '楽しさ',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['楽しさ' => 72, 'ワクワク' => 66],
                        'importance_score' => 0.64,
                        'beliefs' => ['単調な日々も移動だけで少し変わる'],
                        'chains' => ['チャイム', '廊下', '友達'],
                    ],
                    'tags' => ['学校', '中学', '日常'],
                ],
                [
                    'title' => '黒板に書いた進路希望',
                    'body' => 'クラス全員の前で進路希望を書くのが妙に緊張した。言葉にした瞬間に逃げられなくなる感じがした。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-12-07',
                    'emotion_label' => '緊張',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['緊張' => 87, '覚悟' => 70],
                        'importance_score' => 0.78,
                        'beliefs' => ['口に出した目標は少し現実になる'],
                        'chains' => ['進路', '黒板', '教室'],
                    ],
                    'tags' => ['学校', '進路', '高校'],
                ],
                [
                    'title' => '卒業アルバムの寄せ書きタイム',
                    'body' => '何を書くか迷いながら、相手のページを開いては少しだけ真面目になる時間が好きだった。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2006-02-20',
                    'emotion_label' => '切なさ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['切なさ' => 82, '感謝' => 68],
                        'importance_score' => 0.77,
                        'beliefs' => ['別れの前には急に素直になれる'],
                        'chains' => ['卒業', '寄せ書き', 'クラス'],
                    ],
                    'tags' => ['学校', '卒業', '友達'],
                ],
            ],
            'family' => [
                [
                    'category_key' => 'home',
                    'title' => '日曜の朝の掃除機の音',
                    'body' => 'まだ寝ていたい時間に聞こえる掃除機の音で、家族の朝が始まっていたことを思い出す。',
                    'period_key' => 'elementary_school',
                    'occurred_on' => '1999-05-09',
                    'emotion_label' => '懐かしさ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['懐かしさ' => 86, '安心' => 75],
                        'importance_score' => 0.76,
                        'beliefs' => ['生活音は家の記憶そのものになる'],
                        'chains' => ['日曜', '朝', '掃除'],
                    ],
                    'tags' => ['家族', '実家', '朝'],
                ],
                [
                    'title' => '祖母がみかんを剥いてくれた冬',
                    'body' => 'テレビを見ながら、食べやすいように小さく分けてくれた。手元の動きまでよく覚えている。',
                    'period_key' => 'childhood',
                    'occurred_on' => '1997-12-22',
                    'emotion_label' => '愛',
                    'emotion_intensity' => 5,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['愛' => 91, '安心' => 80],
                        'importance_score' => 0.88,
                        'beliefs' => ['小さな世話の積み重ねが家族の記憶になる'],
                        'chains' => ['祖母', '冬', 'みかん'],
                    ],
                    'tags' => ['家族', '祖母', '冬'],
                ],
                [
                    'title' => '父と無言で見た夕方の野球中継',
                    'body' => '会話は少なかったけれど、同じプレーで同時に笑ったことだけは鮮明に覚えている。',
                    'period_key' => 'junior_high',
                    'occurred_on' => '2001-10-03',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 70, '懐かしさ' => 72],
                        'importance_score' => 0.67,
                        'beliefs' => ['言葉が少なくても共有できる時間がある'],
                        'chains' => ['父', 'テレビ', '夕方'],
                    ],
                    'tags' => ['家族', '父', '野球'],
                ],
                [
                    'title' => '帰省の新幹線で見た夜景',
                    'body' => '窓に映る自分と外の明かりをぼんやり見ながら、家に帰る感覚が少しずつ戻ってきた。',
                    'period_key' => 'adult',
                    'occurred_on' => '2014-12-29',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 83, '懐かしさ' => 78],
                        'importance_score' => 0.8,
                        'beliefs' => ['帰る途中からもう家族の時間は始まっている'],
                        'chains' => ['帰省', '新幹線', '夜景'],
                    ],
                    'tags' => ['家族', '帰省', '移動'],
                ],
                [
                    'title' => '正月に並べた湯のみ',
                    'body' => '人数分の湯のみを並べるだけで、年に一度のにぎやかさが始まる感じがした。',
                    'period_key' => 'elementary_school',
                    'occurred_on' => '2000-01-01',
                    'emotion_label' => '楽しさ',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['楽しさ' => 69, '安心' => 74],
                        'importance_score' => 0.63,
                        'beliefs' => ['準備の所作だけで行事の空気は立ち上がる'],
                        'chains' => ['正月', '湯のみ', '親戚'],
                    ],
                    'tags' => ['家族', '正月', '行事'],
                ],
            ],
            'travel' => [
                [
                    'title' => '始発で向かった海辺の駅',
                    'body' => 'まだ眠い頭で降りた駅に潮の匂いがして、一気に旅の実感が湧いた。',
                    'period_key' => 'adult',
                    'occurred_on' => '2016-07-17',
                    'emotion_label' => 'ワクワク',
                    'emotion_intensity' => 5,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['ワクワク' => 92, '驚き' => 66],
                        'importance_score' => 0.84,
                        'beliefs' => ['移動の最初の匂いで旅は始まる'],
                        'chains' => ['海', '始発', '駅'],
                    ],
                    'tags' => ['旅行', '海', '夏'],
                ],
                [
                    'title' => '知らない町で食べた朝定食',
                    'body' => '観光地より先に、町の食堂の湯気でその場所を好きになった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2017-03-12',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 81, '楽しさ' => 68],
                        'importance_score' => 0.74,
                        'beliefs' => ['土地の朝ごはんにはその町のリズムが出る'],
                        'chains' => ['旅行', '食堂', '朝'],
                    ],
                    'tags' => ['旅行', '朝食', '街歩き'],
                ],
                [
                    'title' => '雨で予定が崩れた神社の参道',
                    'body' => '傘を差して歩いた石畳がかえって印象に残った。計画通りじゃない旅の方が長く覚えている。',
                    'period_key' => 'adult',
                    'occurred_on' => '2018-09-23',
                    'emotion_label' => '切なさ',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['切なさ' => 63, '懐かしさ' => 71],
                        'importance_score' => 0.69,
                        'beliefs' => ['予定外の景色ほど物語になる'],
                        'chains' => ['雨', '神社', '参道'],
                    ],
                    'tags' => ['旅行', '雨', '神社'],
                ],
                [
                    'title' => '夜行バスの消灯後の静けさ',
                    'body' => '周りが寝静まったあと、サービスエリアの光だけが窓に流れていた。少しだけ非日常だった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2015-11-08',
                    'emotion_label' => '懐かしさ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['懐かしさ' => 79, '安心' => 61],
                        'importance_score' => 0.7,
                        'beliefs' => ['移動そのものにも旅の核心がある'],
                        'chains' => ['夜行バス', '深夜', '窓'],
                    ],
                    'tags' => ['旅行', '移動', '夜'],
                ],
                [
                    'title' => '地図を見ずに歩いた夕暮れ',
                    'body' => '目的地を決めずに歩いていたら、川沿いのきれいな夕焼けに出会えた。遠回りが正解だった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2019-05-04',
                    'emotion_label' => '感動',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['感動' => 85, '自由' => 73],
                        'importance_score' => 0.78,
                        'beliefs' => ['迷う時間が旅の余白をつくる'],
                        'chains' => ['夕暮れ', '川沿い', '散歩'],
                    ],
                    'tags' => ['旅行', '夕焼け', '散歩'],
                ],
            ],
            'work' => [
                [
                    'title' => '初出社の日に握りしめた社員証',
                    'body' => '首から下げたカード一枚で急に社会人になった気がした。まだ何者でもない緊張があった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2011-04-01',
                    'emotion_label' => '緊張',
                    'emotion_intensity' => 5,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['緊張' => 91, '期待' => 68],
                        'importance_score' => 0.84,
                        'beliefs' => ['肩書きより最初の空気が強く残る'],
                        'chains' => ['初出社', '社員証', '朝'],
                    ],
                    'tags' => ['仕事', '初日', '社会人'],
                ],
                [
                    'title' => '会議室で初めて提案が通った午後',
                    'body' => '終わってから席に戻るまで、足元が少し軽かった。小さな承認でも十分うれしかった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2012-10-16',
                    'emotion_label' => '達成感',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['達成感' => 88, '安心' => 64],
                        'importance_score' => 0.79,
                        'beliefs' => ['言葉が通る経験は次の挑戦を軽くする'],
                        'chains' => ['提案', '会議', '午後'],
                    ],
                    'tags' => ['仕事', '提案', '会議'],
                ],
                [
                    'title' => '終電前のオフィスの静けさ',
                    'body' => '蛍光灯の音だけが目立つ時間帯に、ようやく考えがまとまることがあった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2013-06-21',
                    'emotion_label' => '緊張',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['緊張' => 62, '集中' => 83],
                        'importance_score' => 0.73,
                        'beliefs' => ['静けさが必要な仕事もある'],
                        'chains' => ['残業', 'オフィス', '夜'],
                    ],
                    'tags' => ['仕事', '夜', '集中'],
                ],
                [
                    'title' => '後輩に初めて任せた資料',
                    'body' => '自分でやった方が早い気持ちを抑えて渡した。戻ってきた資料にちゃんと個性があった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2016-02-09',
                    'emotion_label' => '感動',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['感動' => 67, '安心' => 70],
                        'importance_score' => 0.71,
                        'beliefs' => ['任せることでしか見えない成長がある'],
                        'chains' => ['後輩', '資料', '育成'],
                    ],
                    'tags' => ['仕事', '後輩', '成長'],
                ],
                [
                    'title' => '退勤後のエレベーターで力が抜けた瞬間',
                    'body' => '扉が閉まった途端、一日分の緊張がまとめて落ちた。無言のまま深呼吸した。',
                    'period_key' => 'adult',
                    'occurred_on' => '2014-01-30',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 84, '疲労' => 72],
                        'importance_score' => 0.75,
                        'beliefs' => ['切り替わる瞬間には身体が先に反応する'],
                        'chains' => ['退勤', 'エレベーター', '深呼吸'],
                    ],
                    'tags' => ['仕事', '退勤', '日常'],
                ],
            ],
            'friends' => [
                [
                    'title' => '駅前で立ち話が長引いた夜',
                    'body' => '解散するつもりが、改札の前でまた話が広がって結局一時間近くいた。あのだらだら感がよかった。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-05-19',
                    'emotion_label' => '楽しさ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['楽しさ' => 86, '安心' => 68],
                        'importance_score' => 0.74,
                        'beliefs' => ['用事のない会話ほど関係を深くする'],
                        'chains' => ['駅前', '夜', '立ち話'],
                    ],
                    'tags' => ['友達', '高校', '会話'],
                ],
                [
                    'title' => '誕生日に届いた短いメッセージ',
                    'body' => '長文じゃないのに、ちゃんと自分のことを見てくれていた感じがしてうれしかった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2018-06-10',
                    'emotion_label' => '愛',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['愛' => 74, '感謝' => 82],
                        'importance_score' => 0.72,
                        'beliefs' => ['気の利いた短さには信頼がある'],
                        'chains' => ['誕生日', 'メッセージ', '夜'],
                    ],
                    'tags' => ['友達', '誕生日', '連絡'],
                ],
                [
                    'title' => 'テスト前に集まった図書館',
                    'body' => '勉強しに集まったのに、最初の三十分は雑談ばかりだった。それでも一緒にいると不思議と頑張れた。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-11-02',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 73, '楽しさ' => 65],
                        'importance_score' => 0.66,
                        'beliefs' => ['集中は孤独より連帯から生まれることもある'],
                        'chains' => ['図書館', 'テスト前', '雑談'],
                    ],
                    'tags' => ['友達', '勉強', '高校'],
                ],
                [
                    'title' => '久しぶりに会っても会話が戻る感じ',
                    'body' => '間が空いていたのに、話し始めたらすぐ元のテンポに戻った。それが一番うれしかった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2020-08-14',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 86, '感謝' => 71],
                        'importance_score' => 0.78,
                        'beliefs' => ['時間をまたいでも崩れない関係がある'],
                        'chains' => ['再会', '会話', '信頼'],
                    ],
                    'tags' => ['友達', '再会', '大人'],
                ],
                [
                    'title' => '帰り道に借りたマンガ',
                    'body' => '面白いから読んでみてと言って渡された一冊が、その友達のことまでよく表していた気がした。',
                    'period_key' => 'junior_high',
                    'occurred_on' => '2001-07-09',
                    'emotion_label' => '楽しさ',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['楽しさ' => 78, '懐かしさ' => 69],
                        'importance_score' => 0.67,
                        'beliefs' => ['貸し借りするものには人柄がにじむ'],
                        'chains' => ['マンガ', '帰り道', '貸し借り'],
                    ],
                    'tags' => ['友達', 'マンガ', '中学'],
                ],
            ],
            'hobby' => [
                [
                    'title' => '初めて一人で映画館に入った日',
                    'body' => '少し勇気が要ったけれど、終わったあと誰にも邪魔されず余韻に浸れたのがよかった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2013-09-14',
                    'emotion_label' => '感動',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['感動' => 80, '安心' => 62],
                        'importance_score' => 0.7,
                        'beliefs' => ['一人の趣味には一人だからこその深さがある'],
                        'chains' => ['映画', '一人', '余韻'],
                    ],
                    'tags' => ['趣味', '映画', '一人時間'],
                ],
                [
                    'title' => '読みかけの本を持って入った喫茶店',
                    'body' => '窓際の席で続きを読む時間が妙に贅沢だった。本の内容より場面ごと残っている。',
                    'period_key' => 'adult',
                    'occurred_on' => '2017-01-22',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 82, '懐かしさ' => 60],
                        'importance_score' => 0.72,
                        'beliefs' => ['趣味は内容だけでなく環境ごと記憶される'],
                        'chains' => ['読書', '喫茶店', '窓際'],
                    ],
                    'tags' => ['趣味', '読書', 'カフェ'],
                ],
                [
                    'title' => '休日に何時間も歩いて撮った写真',
                    'body' => '結局一枚しか満足いく写真は撮れなかったけれど、その一枚がその日の全部だった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2019-10-06',
                    'emotion_label' => '達成感',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['達成感' => 83, '集中' => 72],
                        'importance_score' => 0.76,
                        'beliefs' => ['趣味は結果より探している時間が本体'],
                        'chains' => ['写真', '散歩', '休日'],
                    ],
                    'tags' => ['趣味', '写真', '散歩'],
                ],
                [
                    'title' => '模型を組み立てる無言の午後',
                    'body' => '細かい部品を合わせるだけで時間が溶けた。誰にも急かされない集中が心地よかった。',
                    'period_key' => 'junior_high',
                    'occurred_on' => '2002-02-17',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 69, '集中' => 79],
                        'importance_score' => 0.65,
                        'beliefs' => ['手を動かす趣味は心拍まで整える'],
                        'chains' => ['模型', '午後', '机'],
                    ],
                    'tags' => ['趣味', '模型', '集中'],
                ],
                [
                    'title' => '新しい道具を試した週末',
                    'body' => 'うまく使いこなせてはいなかったけれど、新しい道具を触っているだけで前向きになれた。',
                    'period_key' => 'adult',
                    'occurred_on' => '2021-04-11',
                    'emotion_label' => 'ワクワク',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['ワクワク' => 87, '期待' => 70],
                        'importance_score' => 0.73,
                        'beliefs' => ['道具は新しい視点を連れてくる'],
                        'chains' => ['週末', '道具', '試行錯誤'],
                    ],
                    'tags' => ['趣味', '道具', '週末'],
                ],
            ],
            'food' => [
                [
                    'title' => '深夜に食べたラーメンの湯気',
                    'body' => '空腹もあったけれど、あの時間に食べる背徳感まで含めておいしかった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2015-02-06',
                    'emotion_label' => '楽しさ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['楽しさ' => 82, '満足' => 78],
                        'importance_score' => 0.72,
                        'beliefs' => ['食事は時間帯でも味が変わる'],
                        'chains' => ['ラーメン', '深夜', '湯気'],
                    ],
                    'tags' => ['食', 'ラーメン', '夜'],
                ],
                [
                    'title' => '焼きたてパンの袋を抱えて帰った朝',
                    'body' => '袋越しに伝わるあたたかさだけで少し機嫌が良くなった。家に着く前から幸せだった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2018-04-28',
                    'emotion_label' => '幸せ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['幸せ' => 88, '安心' => 69],
                        'importance_score' => 0.77,
                        'beliefs' => ['香りは期待を先に連れてくる'],
                        'chains' => ['パン', '朝', '帰り道'],
                    ],
                    'tags' => ['食', 'パン', '朝'],
                ],
                [
                    'title' => '文化祭で売り切れた焼きそば',
                    'body' => '味よりも、売り切れた瞬間にみんなで顔を見合わせた達成感の方を覚えている。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-10-29',
                    'emotion_label' => '達成感',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['達成感' => 85, '楽しさ' => 73],
                        'importance_score' => 0.74,
                        'beliefs' => ['食べ物は一緒に作るとイベントになる'],
                        'chains' => ['文化祭', '焼きそば', '完売'],
                    ],
                    'tags' => ['食', '文化祭', '高校'],
                ],
                [
                    'title' => '祖父と食べた蕎麦屋の卵焼き',
                    'body' => '会話は少なかったけれど、必ず卵焼きを一皿頼む習慣だけは変わらなかった。',
                    'period_key' => 'elementary_school',
                    'occurred_on' => '1999-08-18',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 78, '懐かしさ' => 72],
                        'importance_score' => 0.71,
                        'beliefs' => ['繰り返す注文が関係の輪郭をつくる'],
                        'chains' => ['祖父', '蕎麦屋', '卵焼き'],
                    ],
                    'tags' => ['食', '家族', '外食'],
                ],
                [
                    'title' => '旅先で食べた季節外れのかき氷',
                    'body' => '少し寒かったのに、勢いで頼んだかき氷が妙に楽しかった。正しさより場のノリが勝った。',
                    'period_key' => 'adult',
                    'occurred_on' => '2019-11-02',
                    'emotion_label' => '楽しさ',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['楽しさ' => 76, '驚き' => 61],
                        'importance_score' => 0.64,
                        'beliefs' => ['少しばかげた選択の方が思い出になる'],
                        'chains' => ['旅行', 'かき氷', 'ノリ'],
                    ],
                    'tags' => ['食', '旅行', '思い出'],
                ],
            ],
            'love' => [
                [
                    'title' => '告白前に何度も書き直したメッセージ',
                    'body' => '送る前に何度も読み返して、結局シンプルな言葉にした。送信ボタンがやけに遠かった。',
                    'period_key' => 'high_school',
                    'occurred_on' => '2005-12-24',
                    'emotion_label' => 'ドキドキ',
                    'emotion_intensity' => 5,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['ドキドキ' => 95, '緊張' => 88],
                        'importance_score' => 0.9,
                        'beliefs' => ['大事な言葉ほど短くなる'],
                        'chains' => ['メッセージ', '告白', '夜'],
                    ],
                    'tags' => ['恋愛', '告白', '高校'],
                ],
                [
                    'title' => '待ち合わせ前の五分が長かった日',
                    'body' => '時計を見てもまだ五分しか経っていなかった。会う前の時間の方が濃く感じた。',
                    'period_key' => 'adult',
                    'occurred_on' => '2014-07-12',
                    'emotion_label' => 'ワクワク',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['ワクワク' => 84, 'ドキドキ' => 79],
                        'importance_score' => 0.76,
                        'beliefs' => ['会う前の待ち時間に関係の熱量が表れる'],
                        'chains' => ['待ち合わせ', '時計', '駅'],
                    ],
                    'tags' => ['恋愛', '待ち合わせ', '大人'],
                ],
                [
                    'title' => '何気ない相槌で救われた夜',
                    'body' => '特別な言葉じゃなくても、ちゃんと聞いてくれていると分かるだけで気持ちが軽くなった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2017-08-03',
                    'emotion_label' => '愛',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['愛' => 80, '安心' => 77],
                        'importance_score' => 0.75,
                        'beliefs' => ['理解は助言より先に届くことがある'],
                        'chains' => ['会話', '夜', '安心'],
                    ],
                    'tags' => ['恋愛', '会話', '安心'],
                ],
                [
                    'title' => '喧嘩のあとに歩いた川沿い',
                    'body' => '言葉を探しながら歩いた時間の方を覚えている。黙っていても、終わらせたくない気持ちはあった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2018-10-14',
                    'emotion_label' => '切なさ',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['切なさ' => 83, '愛' => 68],
                        'importance_score' => 0.78,
                        'beliefs' => ['関係は言い合いより歩幅に出ることがある'],
                        'chains' => ['喧嘩', '散歩', '川沿い'],
                    ],
                    'tags' => ['恋愛', '喧嘩', '散歩'],
                ],
                [
                    'title' => '手紙をしまった引き出し',
                    'body' => 'もう読まなくなっても捨てられない。しまってある場所まで含めて、その時期の気持ちだと思う。',
                    'period_key' => 'adult',
                    'occurred_on' => '2020-01-19',
                    'emotion_label' => '懐かしさ',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['懐かしさ' => 79, '切なさ' => 65],
                        'importance_score' => 0.69,
                        'beliefs' => ['残してある場所が心の保管方法になる'],
                        'chains' => ['手紙', '引き出し', '記念'],
                    ],
                    'tags' => ['恋愛', '手紙', '記憶'],
                ],
            ],
            'future' => [
                [
                    'title' => '将来やりたいことを書き出した深夜',
                    'body' => '現実味は薄くても、紙に並べてみると少しだけ輪郭が見えた。眠れない夜が前向きに変わった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2019-06-01',
                    'emotion_label' => '希望',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['希望' => 86, 'ワクワク' => 72],
                        'importance_score' => 0.77,
                        'beliefs' => ['未来は書き出した瞬間に少し手前へ来る'],
                        'chains' => ['深夜', 'メモ', '構想'],
                    ],
                    'tags' => ['未来', '目標', 'メモ'],
                ],
                [
                    'title' => '新しい街を歩きながら想像した暮らし',
                    'body' => 'この道を毎日通るのかなと考えるだけで、その土地と少しだけ関係ができた気がした。',
                    'period_key' => 'adult',
                    'occurred_on' => '2021-09-05',
                    'emotion_label' => 'ワクワク',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['ワクワク' => 82, '希望' => 75],
                        'importance_score' => 0.74,
                        'beliefs' => ['まだ住んでいない街にも先に感情は宿る'],
                        'chains' => ['引っ越し', '街歩き', '想像'],
                    ],
                    'tags' => ['未来', '街', '暮らし'],
                ],
                [
                    'title' => 'いつか見返す前提で残したノート',
                    'body' => '今の未熟さも含めて残しておこうと思えた。未来の自分への手紙みたいだった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2022-02-11',
                    'emotion_label' => '安心',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['安心' => 72, '希望' => 70],
                        'importance_score' => 0.68,
                        'beliefs' => ['記録は未来の自分との対話になる'],
                        'chains' => ['ノート', '記録', '未来'],
                    ],
                    'tags' => ['未来', 'ノート', '記録'],
                ],
                [
                    'title' => '子どもに話したい思い出を考えた夕方',
                    'body' => 'まだ先のことなのに、何を残したいかを考えるだけで自分の軸が少し見えた。',
                    'period_key' => 'adult',
                    'occurred_on' => '2023-05-18',
                    'emotion_label' => '愛',
                    'emotion_intensity' => 4,
                    'visibility' => Memory::VISIBILITY_SHARED,
                    'metadata' => [
                        'emotion_scores' => ['愛' => 78, '希望' => 74],
                        'importance_score' => 0.73,
                        'beliefs' => ['誰かに渡したい記憶が自分の核になる'],
                        'chains' => ['家族', '継承', '夕方'],
                    ],
                    'tags' => ['未来', '家族', '継承'],
                ],
                [
                    'title' => '失敗しても続けたいと思えた企画',
                    'body' => 'うまくいく保証はなかったけれど、やめたくないと思えた時点で意味があった。',
                    'period_key' => 'adult',
                    'occurred_on' => '2024-01-07',
                    'emotion_label' => '達成感',
                    'emotion_intensity' => 3,
                    'visibility' => Memory::VISIBILITY_PRIVATE,
                    'metadata' => [
                        'emotion_scores' => ['達成感' => 64, '希望' => 76],
                        'importance_score' => 0.71,
                        'beliefs' => ['続けたい気持ちは成果より早く答えになる'],
                        'chains' => ['企画', '挑戦', '継続'],
                    ],
                    'tags' => ['未来', '挑戦', '企画'],
                ],
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
