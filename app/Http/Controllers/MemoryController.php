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
    private const BUBBLE_LAYER_SIZE = 10;
    private const PERIODS = ['幼少期', '小学生', '中学生', '高校生', '大学生', '成人期'];

    private const EMOTION_GROUPS = [
        'ポジティブ' => ['嬉しい', '楽しい', '安心', 'ホッとした', '幸せ', '満足', 'ワクワク', '感謝', '誇らしい', '自信がある'],
        'ニュートラル' => ['普通', 'なんとなく', '落ち着いている', 'ぼーっとした', '考え中'],
        'ネガティブ（軽め）' => ['モヤモヤ', '少し不安', '疲れた', '迷い', '気まずい', '引っかかる'],
        'ネガティブ（強め）' => ['不安', '悲しい', 'イライラ', '怒り', '落ち込み', '孤独', '無力感', '自信がない'],
    ];

    public function index(Request $request): View
    {
        $selectedPeriod = $request->string('period')->toString();
        $selectedPeriod = in_array($selectedPeriod, array_merge(['すべて'], self::PERIODS), true) ? $selectedPeriod : 'すべて';

        $query = Memory::query()->latest();

        if ($selectedPeriod !== 'すべて') {
            $query->where('period', $selectedPeriod);
        }

        return view('memories.index', [
            'memories' => $query->get(),
            'recentMemories' => Memory::query()->latest()->take(3)->get(),
            'selectedPeriod' => $selectedPeriod,
            'periods' => self::PERIODS,
            'emotionToneMap' => $this->emotionToneMap(),
            'allCount' => Memory::query()->count(),
        ]);
    }

    public function create(): View
    {
        return view('memories.create', [
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
                'colors' => $this->toneColors($tone),
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(self::PERIODS)],
            'content' => ['required', 'string'],
            'emotion' => ['required', Rule::in($this->allEmotions()->all())],
        ], [
            'period.required' => '年代を選択してください。',
            'period.in' => '年代を正しく選択してください。',
            'content.required' => '内容を入力してください。',
            'emotion.required' => '感情を選択してください。',
            'emotion.in' => '感情を正しく選択してください。',
        ]);

        Memory::query()->create($validated);

        return redirect()->route('memories.index')->with('status', 'created');
    }

    public function show(Memory $memory): View
    {
        return view('memories.show', [
            'memory' => $memory,
            'emotionToneMap' => $this->emotionToneMap(),
        ]);
    }

    public function destroy(Memory $memory): RedirectResponse
    {
        $memory->delete();

        return redirect()->route('memories.index')->with('status', 'deleted');
    }

    private function allEmotions(): Collection
    {
        return collect(self::EMOTION_GROUPS)->flatten();
    }

    private function emotionToneMap(): array
    {
        $map = [];

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
}
