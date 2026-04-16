<?php

namespace Database\Seeders;

use App\Models\Memory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MemorySeeder extends Seeder
{
    public function run(): void
    {
        $periods = ['幼少期', '小学生', '中学生', '高校生', '大学生', '成人期', '不明'];

        $emotionPools = [
            'ポジティブ' => ['嬉しい', '楽しい', '安心', 'ホッとした', '幸せ', '満足', 'ワクワク', '感謝', '誇らしい', '自信がある'],
            'ニュートラル' => ['普通', 'なんとなく', '落ち着いている', 'ぼーっとした', '考え中'],
            'ネガティブ（軽め）' => ['モヤモヤ', '少し不安', '疲れた', '迷い', '気まずい', '引っかかる'],
            'ネガティブ（強め）' => ['不安', '悲しい', 'イライラ', '怒り', '落ち込み', '孤独', '無力感', '自信がない'],
        ];

        $moments = [
            '夕方の帰り道で',
            '静かな教室の隅で',
            '雨上がりの公園で',
            '夏の終わりのベランダで',
            '放課後の廊下で',
            'にぎやかな食卓で',
            '知らない街の駅前で',
            '薄暗い図書室で',
            '部屋の明かりを落としたあとで',
            '朝の電車を待ちながら',
            '文化祭の準備中に',
            '旅先のホテルの窓辺で',
        ];

        $actions = [
            'ふと深呼吸した',
            '誰かの言葉を思い出した',
            '胸の奥が少しざわついた',
            '肩の力が抜けた',
            '景色をしばらく見つめていた',
            '自分の本音に気づいた',
            '何気ない一言がずっと残った',
            '足を止めて空を見上げた',
            '心の中でそっと決意した',
            'その場の空気を強く覚えた',
            '言葉にできない感情を抱えた',
            '静かにうれしさを噛みしめた',
        ];

        $details = [
            '冷たい風の匂いまで鮮明だった。',
            '今でもその時の光の色を思い出せる。',
            'あとから考えると大きな分岐点だった気がする。',
            'その瞬間だけ時間がゆっくり流れた。',
            '何でもない出来事なのに妙に心に残っている。',
            'うまく説明できないけれど確かに忘れられない。',
            '自分でも意外なくらい印象に残った。',
            '言葉より先に感情が動いた気がした。',
            'しばらくその余韻から抜けられなかった。',
            '後になって何度も思い返した場面だった。',
        ];

        $unknownOpeners = [
            'いつ頃だったか曖昧だけれど',
            '年齢は思い出せないのに',
            '前後の記憶はぼやけているのに',
            '季節も場所も定かではないが',
            '断片的にしか残っていないのに',
        ];

        mt_srand(20260417);

        Memory::query()->truncate();

        $entries = [];
        $baseTime = Carbon::create(2026, 4, 17, 9, 0, 0, 'Asia/Tokyo')->subDays(400);

        foreach ($periods as $period) {
            $emotion = $this->pickEmotion($emotionPools);
            $entries[] = $this->buildMemoryEntry($period, $emotion, $moments, $actions, $details, $unknownOpeners, $baseTime->copy());
            $baseTime->addHours(13);
        }

        while (count($entries) < 50) {
            $period = $periods[array_rand($periods)];
            $emotion = $this->pickEmotion($emotionPools);
            $entries[] = $this->buildMemoryEntry($period, $emotion, $moments, $actions, $details, $unknownOpeners, $baseTime->copy());
            $baseTime->addHours(mt_rand(7, 19));
        }

        Memory::query()->insert($entries);
    }

    private function pickEmotion(array $emotionPools): string
    {
        $group = array_rand($emotionPools);
        $emotions = $emotionPools[$group];

        return $emotions[array_rand($emotions)];
    }

    private function buildMemoryEntry(
        string $period,
        string $emotion,
        array $moments,
        array $actions,
        array $details,
        array $unknownOpeners,
        Carbon $timestamp
    ): array {
        $prefix = $period === '不明'
            ? $unknownOpeners[array_rand($unknownOpeners)] . '、'
            : $period . 'の頃、';

        $content = $prefix
            . $moments[array_rand($moments)]
            . ' '
            . $actions[array_rand($actions)]
            . '。'
            . $details[array_rand($details)];

        return [
            'period' => $period,
            'emotion' => $emotion,
            'content' => $content,
            'created_at' => $timestamp->copy()->utc(),
            'updated_at' => $timestamp->copy()->utc(),
        ];
    }
}
