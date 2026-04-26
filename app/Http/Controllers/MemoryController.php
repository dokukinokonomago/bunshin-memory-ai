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
    private const BUBBLE_LAYER_SIZE = 100;
    private const ACTIVE_CREATE_VIEW = 'memories.create_v2';
    private const PERIODS = ['幼少期', '小学生', '中学生', '高校生', '大学生', '成人期', '不明'];
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

        $query = Memory::query()->latest();

        if ($selectedPeriod !== 'すべて') {
            $query->where('period', $selectedPeriod);
        }

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder
                    ->where('content', 'like', '%' . $keyword . '%')
                    ->orWhere('period', 'like', '%' . $keyword . '%')
                    ->orWhere('emotion', 'like', '%' . $keyword . '%');
            });
        }

        return view('memories.index', [
            'memories' => $query->get(),
            'emotionToneMap' => $this->emotionToneMap(),
            'allCount' => Memory::query()->count(),
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
        $requestedLayer = max(1, $request->integer('layer', 1));

        $query = Memory::query()->latest();

        if ($selectedPeriod !== 'すべて') {
            $query->where('period', $selectedPeriod);
        }

        $emotionToneMap = $this->emotionToneMap();
        $matchingCount = (clone $query)->count();
        $matchingMemories = $selectedPeriod !== 'すべて' ? (clone $query)->get() : collect();
        $layerCount = max(1, (int) ceil($matchingCount / self::BUBBLE_LAYER_SIZE));
        $currentLayer = min($requestedLayer, $layerCount);
        $offset = ($currentLayer - 1) * self::BUBBLE_LAYER_SIZE;
        $memories = (clone $query)
            ->skip($offset)
            ->take(self::BUBBLE_LAYER_SIZE)
            ->get();

        $bubbleMemories = $memories->values()->map(function (Memory $memory) use ($emotionToneMap): array {
            $tone = $emotionToneMap[$memory->emotion] ?? 'ニュートラル';

            return [
                'id' => $memory->id,
                'period' => $memory->period,
                'emotion' => $memory->emotion,
                'content' => $memory->content,
                'label' => $this->bubbleKeyword($memory),
                'tone' => $tone,
                'colors' => $this->periodColors($memory->period),
                'tags' => [$memory->period, $memory->emotion],
            ];
        });

        return view('memories.bubbles', [
            'bubbleMemories' => $bubbleMemories,
            'allCount' => Memory::query()->count(),
            'displayCount' => $bubbleMemories->count(),
            'matchingCount' => $matchingCount,
            'periods' => self::PERIODS,
            'selectedPeriod' => $selectedPeriod,
            'currentLayer' => $currentLayer,
            'layerCount' => $layerCount,
            'hasPreviousLayer' => $currentLayer > 1,
            'hasNextLayer' => $currentLayer < $layerCount,
            'selectedPeriodStatus' => $selectedPeriod !== 'すべて'
                ? $this->selectedPeriodStatus($matchingMemories, $emotionToneMap, $selectedPeriod, $currentLayer, $layerCount)
                : null,
        ]);
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

            if ($word !== '') {
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
        ], [
            'period.required' => '年代を選択してください。',
            'period.in' => '年代を正しく選択してください。',
            'content.required' => '内容を入力してください。',
            'emotion.required' => '感情を選択してください。',
            'emotion.max' => '感情は20文字以内で入力してください。',
        ]);

        return [
            'period' => $validated['period'],
            'content' => trim($validated['content']),
            'emotion' => trim($validated['emotion']),
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
