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
            ['period' => '幼少期', 'emotion' => '幸せ', 'content' => '夜空を見上げながら、父と一緒に流れ星を探した。'],
            ['period' => '幼少期', 'emotion' => 'ホッとした', 'content' => '熱を出した日に、母がりんごをすりおろしてくれた。'],
            ['period' => '小学生', 'emotion' => '楽しい', 'content' => '放課後の校庭で、日が暮れるまで鬼ごっこを続けた。'],
            ['period' => '小学生', 'emotion' => '感謝', 'content' => '忘れ物をした朝、隣の席の友達がノートを見せてくれた。'],
            ['period' => '小学生', 'emotion' => '気まずい', 'content' => '発表で言葉につまり、教室が静かになって顔が熱くなった。'],
            ['period' => '中学生', 'emotion' => '誇らしい', 'content' => '合唱コンクールで伴奏を任され、無事に弾き切れた。'],
            ['period' => '中学生', 'emotion' => 'なんとなく', 'content' => '夕方の部室で、先輩たちの雑談をぼんやり聞いていた。'],
            ['period' => '高校生', 'emotion' => 'ワクワク', 'content' => '修学旅行の前夜、しおりを何度も見返して眠れなかった。'],
            ['period' => '高校生', 'emotion' => '少し不安', 'content' => '進路面談の前、廊下の窓に映る自分を見て深呼吸した。'],
            ['period' => '高校生', 'emotion' => '安心', 'content' => '雨の日の図書室で、好きな作家の新刊を見つけて落ち着いた。'],
            ['period' => '大学生', 'emotion' => '満足', 'content' => '徹夜で仕上げた企画書が通り、研究室で静かに拳を握った。'],
            ['period' => '大学生', 'emotion' => '迷い', 'content' => '就活サイトを閉じたあと、本当にやりたいことを考え込んだ。'],
            ['period' => '大学生', 'emotion' => '嬉しい', 'content' => '初めて一人旅に出て、知らない街の朝日を見た。'],
            ['period' => '成人期', 'emotion' => '自信がある', 'content' => '任された案件をやり切り、会議後に背筋が自然と伸びた。'],
            ['period' => '成人期', 'emotion' => '疲れた', 'content' => '終電近いホームで缶コーヒーを飲みながら一日を反芻した。'],
        ];

        foreach ($memories as $memory) {
            Memory::query()->firstOrCreate($memory);
        }
    }
}
