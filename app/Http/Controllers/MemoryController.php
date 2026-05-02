<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemoryController extends Controller
{
    private const ACTIVE_CREATE_VIEW = 'memories.create_v2';
    private const PERIODS = ['幼少期', '小学生', '中学生', '高校生', '大学生', '成人期', '不明'];
    private const SESSION_GRAVE_VISIBLE = 'memories.grave.visible';
    private const SESSION_GRAVE_UNLOCKED = 'memories.grave.unlocked';
    private const GRAVE_PASSCODE = '1234';
    private const GRAVE_TAG = '__grave_hidden__';
    private const CREATE_COMPOSER_GROUP_META = [
        'warm' => [
            'label' => 'あたたかい',
            'previewLabel' => 'やわらかな光',
            'tone' => 'やさしく、あたたかく残る記憶',
        ],
        'calm' => [
            'label' => '静かな',
            'previewLabel' => '静かな余韻',
            'tone' => '落ち着いた空気をまとった記憶',
        ],
        'sway' => [
            'label' => '揺れている',
            'previewLabel' => 'ゆらぐ光',
            'tone' => '気持ちが少し揺れている記憶',
        ],
        'heavy' => [
            'label' => '重たい',
            'previewLabel' => '深い残響',
            'tone' => '重く深く沈むような記憶',
        ],
    ];
    private const CREATE_COMPOSER_EMOTION_OPTIONS = [
        'warm' => ['嬉しい', '楽しい', 'ホッとした', '幸せ', '満足', '感動', '誇らしい'],
        'calm' => ['普通', 'なんとなく', '落ち着いている', 'ぼーっとした', '考え中'],
        'sway' => ['モヤモヤ', '少し不安', '疲れた', '迷い', '気まずい', '引っかかる'],
        'heavy' => ['悲しい', '不安', '落ち込み', '孤独', '無力感', '自信がない', '怒り'],
    ];
    private const CREATE_COMPOSER_BUBBLE_SIZE_CLASSES = ['lg', 'md', 'sm', 'md', 'lg', 'sm', 'md'];
    private const CREATE_COMPOSER_FILLED_STATE_META = [
        'empty' => [
            'label' => 'EMPTY',
            'summary' => '輪郭待ち',
            'description' => 'まだ輪郭は淡く、書き始めを待っています。',
        ],
        'soft' => [
            'label' => 'SOFT',
            'summary' => 'やわらかい輪郭',
            'description' => '記憶のはじまりが静かに浮かび上がっています。',
        ],
        'medium' => [
            'label' => 'MEDIUM',
            'summary' => '輪郭が整う',
            'description' => '情景と感情が結びつき、記憶の像が見えてきました。',
        ],
        'dense' => [
            'label' => 'DENSE',
            'summary' => '深く定着',
            'description' => '記憶の密度が高まり、保存できる状態に近づいています。',
        ],
    ];

    private const EMOTION_GROUPS = [
        'ポジティブ' => ['嬉しい', '楽しい', '安心', 'ホッとした', '幸せ', '満足', 'ワクワク', '感謝', '誇らしい', '自信がある'],
        'ニュートラル' => ['普通', 'なんとなく', '落ち着いている', 'ぼーっとした', '考え中'],
        'ネガティブ（軽め）' => ['モヤモヤ', '少し不安', '疲れた', '迷い', '気まずい', '引っかかる'],
        'ネガティブ（強め）' => ['不安', '悲しい', 'イライラ', '怒り', '落ち込み', '孤独', '無力感', '自信がない'],
    ];

    public function index(Request $request): View
    {
        $keyword = trim($request->string('q')->toString());
        $selectedPeriod = $request->string('period')->toString();
        $selectedPeriod = in_array($selectedPeriod, array_merge(['すべて'], self::PERIODS), true) ? $selectedPeriod : 'すべて';

        $query = $this->visibleMemoriesQuery()->latest();

        if ($selectedPeriod !== 'すべて') {
            $query->where('period', $selectedPeriod);
        }

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder
                    ->where('content', 'like', '%' . $keyword . '%')
                    ->orWhere('period', 'like', '%' . $keyword . '%')
                    ->orWhere('emotion', 'like', '%' . $keyword . '%')
                    ->orWhere('tags', 'like', '%' . $keyword . '%');
            });
        }

        return view('memories.index', [
            'memories' => $query->get(),
            'emotionToneMap' => $this->emotionToneMap(),
            'allCount' => $this->visibleMemoriesQuery()->count(),
            'searchQuery' => $keyword,
            'periods' => self::PERIODS,
            'selectedPeriod' => $selectedPeriod,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->renderCreateView($request, self::ACTIVE_CREATE_VIEW);
    }

    public function createPreview(): RedirectResponse
    {
        return redirect()->route('memories.create');
    }

    public function edit(Memory $memory): View
    {
        return view('memories.edit', [
            'memory' => $memory,
            'periods' => self::PERIODS,
            'emotionGroups' => self::EMOTION_GROUPS,
        ]);
    }

    public function bubbles(Request $request): View
    {
        $selectedPeriod = $request->string('period')->toString();
        $selectedPeriod = in_array($selectedPeriod, array_merge(['すべて'], self::PERIODS), true) ? $selectedPeriod : 'すべて';
        $showGraveBubble = (bool) $request->session()->get(self::SESSION_GRAVE_VISIBLE, false);
        $graveUnlocked = (bool) $request->session()->get(self::SESSION_GRAVE_UNLOCKED, false);
        $emotionToneMap = $this->emotionToneMap();
        $allMemories = $this->visibleMemoriesQuery()
            ->latest()
            ->get();
        $graveMemories = $graveUnlocked
            ? $this->graveMemoriesQuery()->latest()->get()
            : collect();
        $matchingMemories = $selectedPeriod === 'すべて'
            ? $allMemories
            : $allMemories->where('period', $selectedPeriod)->values();
        $matchingCount = $matchingMemories->count();
        $bubbleMemories = $allMemories
            ->values()
            ->map(fn (Memory $memory): array => $this->bubbleMemoryPayload($memory, $emotionToneMap));

        return view('memories.bubbles', [
            'bubbleMemories' => $bubbleMemories,
            'allCount' => $allMemories->count(),
            'displayCount' => $bubbleMemories->count(),
            'matchingCount' => $matchingCount,
            'periodBubbleCounts' => $allMemories->countBy('period')->all(),
            'periods' => self::PERIODS,
            'selectedPeriod' => $selectedPeriod,
            'currentLayer' => 1,
            'layerCount' => 1,
            'hasPreviousLayer' => false,
            'hasNextLayer' => false,
            'focusMode' => $selectedPeriod !== 'すべて',
            'graveMode' => $showGraveBubble ? $this->graveModePayload($graveUnlocked) : null,
            'showGraveBubble' => $showGraveBubble,
            'graveUnlocked' => $graveUnlocked,
            'graveMemories' => $graveMemories
                ->map(fn (Memory $memory): array => $this->bubbleMemoryPayload($memory, $emotionToneMap))
                ->values(),
            'graveUnlockError' => $request->session()->get('grave_unlock_error'),
            'graveUnlockSuccess' => $request->session()->get('grave_unlock_success'),
            'graveCreateSuccess' => $request->session()->get('grave_create_success'),
            'selectedPeriodStatus' => $selectedPeriod !== 'すべて'
                ? $this->selectedPeriodStatus($matchingMemories, $emotionToneMap, $selectedPeriod, 1, 1)
                : null,
            'emotionGroups' => self::EMOTION_GROUPS,
        ]);
    }

    public function revealAllBubbles(Request $request): RedirectResponse
    {
        $request->session()->put(self::SESSION_GRAVE_VISIBLE, true);

        return redirect()->route('memories.bubbles', $this->bubblesRedirectParams($request));
    }

    public function unlockGraveMode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'passcode' => ['required', 'string', 'max:20'],
        ], [
            'passcode.required' => 'パスコードを入力してください。',
            'passcode.max' => 'パスコードが長すぎます。',
        ]);

        $request->session()->put(self::SESSION_GRAVE_VISIBLE, true);

        if ($validated['passcode'] !== self::GRAVE_PASSCODE) {
            $request->session()->forget(self::SESSION_GRAVE_UNLOCKED);

            return redirect()
                ->route('memories.bubbles', $this->bubblesRedirectParams($request))
                ->with('grave_unlock_error', 'パスコードが違います。');
        }

        $request->session()->put(self::SESSION_GRAVE_UNLOCKED, true);

        return redirect()
            ->route('memories.bubbles', $this->bubblesRedirectParams($request))
            ->with('grave_unlock_success', '墓場までモードを開きました。');
    }

    public function hideGraveMode(Request $request): RedirectResponse
    {
        $request->session()->forget([
            self::SESSION_GRAVE_VISIBLE,
            self::SESSION_GRAVE_UNLOCKED,
        ]);

        return redirect()->route('memories.bubbles', $this->bubblesRedirectParams($request));
    }

    public function storeGraveMemory(Request $request): RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_GRAVE_UNLOCKED, false)) {
            abort(403);
        }

        $validated = $this->validateMemory($request);
        $tags = collect($validated['tags'])
            ->prepend(self::GRAVE_TAG)
            ->unique()
            ->values()
            ->all();

        Memory::query()->create(array_merge($validated, [
            'tags' => $tags,
        ]));

        $request->session()->put(self::SESSION_GRAVE_VISIBLE, true);
        $request->session()->put(self::SESSION_GRAVE_UNLOCKED, true);

        return redirect()
            ->route('memories.bubbles', $this->bubblesRedirectParams($request))
            ->with('grave_create_success', '墓場までの記憶玉を保存しました。');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMemory($request);

        Memory::query()->create($validated);

        return redirect()->route('memories.index')->with('status', 'created');
    }

    public function update(Request $request, Memory $memory): RedirectResponse
    {
        $validated = $this->validateMemory($request);

        $memory->update($validated);

        return redirect()->route('memories.index')->with('status', 'updated');
    }

    public function show(Memory $memory): View
    {
        $emotionToneMap = $this->emotionToneMap();
        $tone = $emotionToneMap[$memory->emotion] ?? 'ニュートラル';

        return view('memories.show', [
            'memory' => $memory,
            'emotionToneMap' => $emotionToneMap,
            'tone' => $tone,
            'colors' => $this->toneColors($tone),
            'theme' => $this->memoryTheme($memory),
        ]);
    }

    public function destroy(Memory $memory): RedirectResponse
    {
        $memory->delete();

        return redirect()->route('memories.index')->with('status', 'deleted');
    }

    private function allEmotions(): Collection
    {
        return collect(self::EMOTION_GROUPS)
            ->flatten()
            ->merge(collect(self::CREATE_COMPOSER_EMOTION_OPTIONS)->flatten())
            ->unique()
            ->values();
    }

    private function emotionToneMap(): array
    {
        $map = [
            '嬉しい' => 'ポジティブ',
            '楽しい' => 'ポジティブ',
            'ホッとした' => 'ポジティブ',
            '幸せ' => 'ポジティブ',
            '満足' => 'ポジティブ',
            '感動' => 'ポジティブ',
            '誇らしい' => 'ポジティブ',
            '普通' => 'ニュートラル',
            'なんとなく' => 'ニュートラル',
            '落ち着いている' => 'ニュートラル',
            'ぼーっとした' => 'ニュートラル',
            '考え中' => 'ニュートラル',
            'モヤモヤ' => 'ネガティブ（軽め）',
            '少し不安' => 'ネガティブ（軽め）',
            '疲れた' => 'ネガティブ（軽め）',
            '迷い' => 'ネガティブ（軽め）',
            '気まずい' => 'ネガティブ（軽め）',
            '引っかかる' => 'ネガティブ（軽め）',
            '悲しい' => 'ネガティブ（強め）',
            '不安' => 'ネガティブ（強め）',
            '落ち込み' => 'ネガティブ（強め）',
            '孤独' => 'ネガティブ（強め）',
            '無力感' => 'ネガティブ（強め）',
            '自信がない' => 'ネガティブ（強め）',
            '怒り' => 'ネガティブ（強め）',
        ];

        foreach (self::EMOTION_GROUPS as $group => $emotions) {
            foreach ($emotions as $emotion) {
                $map[$emotion] = $group;
            }
        }

        return $map;
    }

    private function toneColors(string $tone): array
    {
        if (str_contains($tone, 'ポジティブ')) {
            return ['#ffe2c8', '#f08b4f'];
        }

        if (str_contains($tone, 'ネガティブ')) {
            return ['#eadfff', '#8f7cff'];
        }

        return ['#dce9ff', '#63a6ff'];
    }

    /** 年代ごとに虹色順でカラーを割り当てる */
    private function periodColors(string $period): array
    {
        $map = [
            '幼少期' => ['#ff6b6b', '#ff4757'], // 赤
            '小学生' => ['#ffa94d', '#ff7c1f'], // オレンジ
            '中学生' => ['#ffe066', '#ffbc00'], // 黄
            '高校生' => ['#69db7c', '#2dbe4e'], // 緑
            '大学生' => ['#4dabf7', '#1c7ed6'], // 青
            '成人期' => ['#9775fa', '#6741d9'], // 紫
            '不明'   => ['#f06595', '#c2255c'], // ピンク（虹の外側）
        ];

        return $map[$period] ?? ['#dce9ff', '#63a6ff'];
    }

    private function bubbleKeyword(Memory $memory): string
    {
        $content = trim(preg_replace('/\s+/u', ' ', $memory->content) ?? '');
        $parts = preg_split('/[、。,.!?\s「」『』（）()]+/u', $content) ?: [];

        foreach ($parts as $part) {
            $word = trim($part);

            if (
                $word !== ''
                && !preg_match('/^\d+$/u', $word)
                && !preg_match('/^(demo|test)$/iu', $word)
                && mb_strlen($word) >= 2
            ) {
                return Str::limit($word, 6, '');
            }
        }

        return Str::limit($memory->emotion, 6, '');
    }

    private function memoryTheme(Memory $memory): string
    {
        $content = trim(preg_replace('/\s+/u', ' ', $memory->content) ?? '');

        if ($content === '') {
            return $memory->emotion;
        }

        $segments = preg_split('/[。.!?\n]+/u', $content) ?: [];

        foreach ($segments as $segment) {
            $theme = trim($segment, " \t\n\r\0\x0B、。");

            if ($theme !== '') {
                return Str::limit($theme, 20, '…');
            }
        }

        return Str::limit($content, 20, '…');
    }

    private function selectedPeriodStatus(
        Collection $memories,
        array $emotionToneMap,
        string $selectedPeriod,
        int $currentLayer,
        int $layerCount
    ): array {
        $total = $memories->count();
        $emotionCounts = $memories
            ->countBy('emotion')
            ->sortDesc();
        $maxEmotionCount = max(1, (int) $emotionCounts->first());

        $topEmotionBars = $emotionCounts
            ->take(8)
            ->map(fn (int $count, string $emotion): array => [
                'label' => $emotion,
                'count' => $count,
                'ratio' => round(($count / $maxEmotionCount) * 100, 1),
            ])
            ->values()
            ->all();

        $toneBuckets = [
            'ポジティブ' => 'ポジティブ',
            'ニュートラル' => 'ニュートラル',
            '軽い揺れ' => 'ネガティブ（軽め）',
            '深い揺れ' => 'ネガティブ（強め）',
        ];

        $toneRings = collect($toneBuckets)
            ->map(function (string $toneKey, string $label) use ($memories, $emotionToneMap, $total): array {
                $count = $memories->filter(fn (Memory $memory): bool => ($emotionToneMap[$memory->emotion] ?? 'ニュートラル') === $toneKey)->count();

                return [
                    'label' => $label,
                    'count' => $count,
                    'ratio' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
                ];
            })
            ->values()
            ->all();

        $keywordCounts = $memories
            ->map(fn (Memory $memory): string => $this->bubbleKeyword($memory))
            ->countBy()
            ->sortDesc();

        $latest = $memories->sortByDesc('created_at')->take(3)->values();
        $latestTimeline = $latest->map(fn (Memory $memory): array => [
            'date' => optional($memory->created_at)->format('Y.m.d H:i') ?? '----.--.-- --:--',
            'emotion' => $memory->emotion,
            'excerpt' => Str::limit(trim($memory->content), 46, '…'),
        ])->all();

        $oldestDate = optional($memories->sortBy('created_at')->first()?->created_at)->format('Y.m.d') ?? '--.--.--';
        $latestDate = optional($memories->sortByDesc('created_at')->first()?->created_at)->format('Y.m.d') ?? '--.--.--';
        $avgLength = $total > 0
            ? (int) round($memories->avg(fn (Memory $memory): int => mb_strlen(trim($memory->content))))
            : 0;
        $topEmotion = (string) ($emotionCounts->keys()->first() ?? '未分類');
        $topKeyword = (string) ($keywordCounts->keys()->first() ?? '未設定');

        return [
            'period' => $selectedPeriod,
            'total' => $total,
            'uniqueEmotions' => $emotionCounts->count(),
            'avgLength' => $avgLength,
            'topEmotion' => $topEmotion,
            'topKeyword' => $topKeyword,
            'latestDate' => $latestDate,
            'oldestDate' => $oldestDate,
            'currentLayer' => $currentLayer,
            'layerCount' => $layerCount,
            'topEmotionBars' => $topEmotionBars,
            'toneRings' => $toneRings,
            'timeline' => $latestTimeline,
        ];
    }

    private function validateMemory(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(self::PERIODS)],
            'content' => ['required', 'string'],
            'emotion' => ['required', 'string', 'max:20'],
            'tags' => ['nullable', 'string', 'max:180'],
        ], [
            'period.required' => '年代を選択してください。',
            'period.in' => '年代を正しく選択してください。',
            'content.required' => '内容を入力してください。',
            'emotion.required' => '感情を選択してください。',
            'emotion.max' => '感情は20文字以内で入力してください。',
            'tags.max' => '関連タグは180文字以内で入力してください。',
        ]);

        return [
            'period' => $validated['period'],
            'content' => trim($validated['content']),
            'emotion' => trim($validated['emotion']),
            'tags' => $this->normalizeTags((string) ($validated['tags'] ?? '')),
        ];
    }

    private function bubbleMemoryPayload(Memory $memory, array $emotionToneMap): array
    {
        $tone = $emotionToneMap[$memory->emotion] ?? 'ニュートラル';
        $storedTags = collect($memory->tags ?? [])
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->reject(fn (string $tag): bool => $tag === self::GRAVE_TAG)
            ->filter()
            ->values();
        $cluster = $this->memoryClusterLabel($memory, $storedTags);
        $theme = $this->memoryTheme($memory);

        return [
            'id' => $memory->id,
            'period' => $memory->period,
            'emotion' => $memory->emotion,
            'content' => $memory->content,
            'label' => $this->bubbleKeyword($memory),
            'theme' => $theme,
            'cluster' => $cluster,
            'excerpt' => Str::limit(trim($memory->content), 58, '…'),
            'comment' => $this->memoryCompanionComment($memory, $cluster, $theme),
            'createdAt' => optional($memory->created_at)->timezone('Asia/Tokyo')->format('Y.m.d H:i') ?? '--.--.-- --:--',
            'tone' => $tone,
            'colors' => $this->toneColors($tone),
            'periodColors' => $this->periodColors($memory->period),
            'tags' => $storedTags
                ->prepend($memory->emotion)
                ->prepend($memory->period)
                ->unique()
                ->take(6)
                ->values()
                ->all(),
            'url' => route('memories.show', $memory),
        ];
    }

    private function memoryClusterLabel(Memory $memory, Collection $storedTags): string
    {
        $tag = $storedTags
            ->first(fn (string $value): bool => $value !== $memory->period && $value !== $memory->emotion);

        if (is_string($tag) && $tag !== '') {
            return Str::limit($tag, 12, '');
        }

        return Str::limit($memory->emotion, 12, '');
    }

    private function memoryCompanionComment(Memory $memory, string $cluster, string $theme): string
    {
        $templates = [
            'ポジティブ' => [
                'この記憶はまだやわらかく光っています。',
                '近づくほど、当時の温度が戻ってくる記憶です。',
                '明るさの奥に、{}の輪郭が残っています。',
            ],
            'ニュートラル' => [
                '静かな出来事ほど、あとから意味を帯びます。',
                '何気ない場面の中に、{}の気配が残っています。',
                '派手ではないけれど、輪郭の確かな記憶です。',
            ],
            'ネガティブ' => [
                '少し痛みを含みつつ、今も形を失っていません。',
                '{}の感触が、まだ水面下で揺れています。',
                '見返すには勇気がいるけれど、大事な記録です。',
            ],
        ];

        $toneKey = str_contains($memory->emotion, '不安') || str_contains($memory->emotion, '悲') || str_contains($memory->emotion, '怒') || str_contains($memory->emotion, '落ち')
            ? 'ネガティブ'
            : (str_contains($memory->emotion, '嬉') || str_contains($memory->emotion, '楽') || str_contains($memory->emotion, '幸') || str_contains($memory->emotion, '感動')
                ? 'ポジティブ'
                : 'ニュートラル');
        $variants = $templates[$toneKey];
        $template = $variants[$memory->id % count($variants)];

        return str_replace('{}', $cluster !== '' ? $cluster : $theme, $template);
    }

    private function normalizeTags(string $rawTags): array
    {
        return collect(preg_split('/[\r\n,、]+/u', $rawTags) ?: [])
            ->map(fn (string $tag): string => trim(ltrim($tag, '#＃ ')))
            ->reject(fn (string $tag): bool => $tag === self::GRAVE_TAG)
            ->filter()
            ->map(fn (string $tag): string => mb_substr($tag, 0, 20))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function visibleMemoriesQuery()
    {
        return Memory::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('tags')
                    ->orWhere('tags', 'not like', '%"' . self::GRAVE_TAG . '"%');
            });
    }

    private function graveMemoriesQuery()
    {
        return Memory::query()
            ->where('tags', 'like', '%"' . self::GRAVE_TAG . '"%');
    }

    private function bubblesRedirectParams(Request $request): array
    {
        $selectedPeriod = $request->string('period_context')->toString();

        if ($selectedPeriod === '') {
            $selectedPeriod = $request->string('period')->toString();
        }

        if ($selectedPeriod !== '' && $selectedPeriod !== 'すべて' && in_array($selectedPeriod, self::PERIODS, true)) {
            return ['period' => $selectedPeriod];
        }

        return [];
    }

    private function graveModePayload(bool $graveUnlocked): array
    {
        return [
            'id' => 'grave-mode',
            'label' => '墓場まで',
            'status' => $graveUnlocked ? '解錠済み' : '鍵付き',
            'hint' => $graveUnlocked ? '本人だけが触れられる隠し記憶' : '4桁パスコードで開く隠しシャボン',
            'x' => 450,
            'y' => 150,
            'r' => 92,
            'locked' => ! $graveUnlocked,
        ];
    }

    private function createComposerViewData(Request $request): array
    {
        $emotionToGroup = [];

        foreach (self::CREATE_COMPOSER_EMOTION_OPTIONS as $groupKey => $emotions) {
            foreach ($emotions as $emotion) {
                $emotionToGroup[$emotion] = $groupKey;
            }
        }

        $defaultPeriod = self::PERIODS[2] ?? self::PERIODS[0];
        $defaultEmotion = self::CREATE_COMPOSER_EMOTION_OPTIONS['warm'][2] ?? self::CREATE_COMPOSER_EMOTION_OPTIONS['warm'][0];
        $initialPeriod = $request->old('period', $defaultPeriod);
        $initialContent = (string) $request->old('content', '');
        $initialEmotion = (string) $request->old('emotion', $defaultEmotion);

        if (!in_array($initialPeriod, self::PERIODS, true)) {
            $initialPeriod = $defaultPeriod;
        }

        if (!array_key_exists($initialEmotion, $emotionToGroup)) {
            $initialEmotion = $defaultEmotion;
        }

        $contentLength = mb_strlen(trim($initialContent));
        $filledLevel = $this->createComposerFilledLevel($contentLength);

        return [
            'eras' => self::PERIODS,
            'createComposerGroupMeta' => self::CREATE_COMPOSER_GROUP_META,
            'createComposerEmotionOptions' => self::CREATE_COMPOSER_EMOTION_OPTIONS,
            'createComposerBubbleSizeClasses' => self::CREATE_COMPOSER_BUBBLE_SIZE_CLASSES,
            'createComposerFilledStateMeta' => self::CREATE_COMPOSER_FILLED_STATE_META,
            'createComposerEmotionToGroup' => $emotionToGroup,
            'createComposerInitialState' => [
                'period' => $initialPeriod,
                'content' => $initialContent,
                'emotion' => $initialEmotion,
                'group' => $emotionToGroup[$initialEmotion] ?? 'warm',
                'contentLength' => $contentLength,
                'filledLevel' => $filledLevel,
                'filledState' => self::CREATE_COMPOSER_FILLED_STATE_META[$filledLevel],
            ],
        ];
    }

    private function renderCreateView(Request $request, string $view): View
    {
        return view($view, array_merge([
            'periods' => self::PERIODS,
            'emotionGroups' => self::EMOTION_GROUPS,
        ], $this->createComposerViewData($request)));
    }

    private function createComposerFilledLevel(int $contentLength): string
    {
        if ($contentLength === 0) {
            return 'empty';
        }

        if ($contentLength < 60) {
            return 'soft';
        }

        if ($contentLength < 140) {
            return 'medium';
        }

        return 'dense';
    }
}
