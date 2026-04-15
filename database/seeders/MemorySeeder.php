<?php

namespace Database\Seeders;

use App\Models\Memory;
use Illuminate\Database\Seeder;

class MemorySeeder extends Seeder
{
    public function run(): void
    {
        $memories = [
            ['period' => '幼少期', 'emotion' => '安心', 'content' => '祖母の家の縁側で、風鈴の音を聞きながら昼寝した。'],
            ['period' => '幼少期', 'emotion' => '楽しい', 'content' => '雨上がりの公園で長靴のまま水たまりを跳ね回った。'],
            ['period' => '小学生', 'emotion' => '誇らしい', 'content' => '学芸会で大きな声を出せて、家族に褒められた。'],
            ['period' => '小学生', 'emotion' => 'モヤモヤ', 'content' => '仲の良い友達と少しすれ違って、帰り道に考え込んだ。'],
            ['period' => '中学生', 'emotion' => 'ワクワク', 'content' => '文化祭の準備で遅くまで残り、教室の空気が特別に感じた。'],
            ['period' => '中学生', 'emotion' => '少し不安', 'content' => '初めての定期テスト前夜、机に向かいながら落ち着かなかった。'],
            ['period' => '高校生', 'emotion' => '嬉しい', 'content' => '部活の大会で自己ベストが出て、仲間とハイタッチした。'],
            ['period' => '高校生', 'emotion' => '悲しい', 'content' => '卒業が近づいて、いつもの教室が急に遠く感じた。'],
            ['period' => '大学生', 'emotion' => '感謝', 'content' => 'ゼミ発表のあと、友人が遅くまでフィードバックをくれた。'],
            ['period' => '成人期', 'emotion' => '落ち着いている', 'content' => '仕事終わりに静かなカフェでノートを開き、次の目標を整理した。'],
        ];

        foreach ($memories as $memory) {
            Memory::query()->firstOrCreate($memory);
        }
    }
}
