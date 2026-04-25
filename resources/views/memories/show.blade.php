@extends('layouts.app')

@section('title', '記憶ステータス | 分身AI MVP')
@section('page_class', 'page-memory-status-wide')

@php
    use Illuminate\Support\Str;

    $badgeClass = str_contains($tone, 'ポジティブ') ? 'badge-positive' : (str_contains($tone, 'ニュートラル') ? 'badge-neutral' : 'badge-negative');
    $savedAt = $memory->created_at->timezone('Asia/Tokyo');
    $normalizedContent = preg_replace('/\s+/u', ' ', trim($memory->content));
    $contentLength = mb_strlen($normalizedContent);
    $sentences = collect(preg_split('/[。！？\n]+/u', $normalizedContent))
        ->map(fn ($sentence) => trim($sentence))
        ->filter()
        ->values();
    $sentenceCount = max(1, $sentences->count());
    $themeLength = mb_strlen($theme);
    $periodWeight = match ($memory->period) {
        '幼少期' => 28,
        '小学生' => 42,
        '中学生' => 56,
        '高校生' => 67,
        '大学生' => 78,
        '成人期' => 90,
        default => 52,
    };
    $toneWeight = str_contains($tone, 'ポジティブ') ? 82 : (str_contains($tone, 'ニュートラル') ? 63 : 49);
    $clarity = min(96, max(44, 38 + (int) round($contentLength * 0.9)));
    $immersion = min(95, max(36, 34 + $sentenceCount * 12 + (int) floor($themeLength * 1.4)));
    $warmth = min(93, max(32, $toneWeight + (str_contains($memory->emotion, '不安') || str_contains($memory->emotion, '悲') ? -12 : 0)));
    $stability = min(94, max(30, 30 + (int) round($periodWeight * 0.65)));
    $replay = min(97, max(38, 42 + (int) round($contentLength * 0.62)));
    $bars = [
        ['label' => '感情共鳴', 'value' => $warmth, 'accent' => 'warm'],
        ['label' => '情景明瞭度', 'value' => $clarity, 'accent' => 'cool'],
        ['label' => '没入深度', 'value' => $immersion, 'accent' => 'bright'],
        ['label' => '保存安定', 'value' => $stability, 'accent' => 'cool'],
        ['label' => '再生精度', 'value' => $replay, 'accent' => 'bright'],
    ];
    $gauges = [
        ['label' => '情景同期', 'value' => min(95, max(28, (int) round(($clarity + $replay) / 2)))],
        ['label' => '感情密度', 'value' => min(92, max(24, (int) round(($warmth + $immersion) / 2)))],
        ['label' => '余韻残量', 'value' => min(96, max(26, (int) round(($replay + $warmth) / 2)))],
        ['label' => '記録純度', 'value' => min(94, max(22, (int) round(($clarity + $stability) / 2)))],
    ];
    $keywords = collect(preg_split('/[、。,\s]+/u', $normalizedContent))
        ->map(fn ($chunk) => trim($chunk))
        ->filter(fn ($chunk) => mb_strlen($chunk) >= 2)
        ->map(fn ($chunk) => Str::limit($chunk, 10, ''))
        ->unique()
        ->take(6)
        ->values();

    if ($keywords->isEmpty()) {
        $keywords = collect([$theme, $memory->period, $memory->emotion])->filter();
    }

    $previewLines = $sentences->take(3)->values();

    if ($previewLines->isEmpty()) {
        $previewLines = collect([Str::limit($normalizedContent, 42, '…')]);
    }

    $logEntries = [
        [
            'time' => $savedAt->format('Y.m.d H:i'),
            'title' => '記憶コアを保存',
            'body' => 'ライフステージ「' . $memory->period . '」としてアーカイブ完了。',
        ],
        [
            'time' => $savedAt->copy()->subMinutes(3)->format('Y.m.d H:i'),
            'title' => '感情トーンを解析',
            'body' => '感情「' . $memory->emotion . '」を ' . $tone . ' トーンとして分類。',
        ],
        [
            'time' => $savedAt->copy()->subMinutes(6)->format('Y.m.d H:i'),
            'title' => '内容を圧縮表示',
            'body' => Str::limit($normalizedContent, 58, '…'),
        ],
    ];
@endphp

@section('content')
    <section class="memory-dashboard">
        <div class="memory-dashboard-decor" aria-hidden="true">
            <span class="memory-dashboard-star star-1"></span>
            <span class="memory-dashboard-star star-2"></span>
            <span class="memory-dashboard-star star-3"></span>
            <span class="memory-dashboard-star star-4"></span>
            <span class="memory-dashboard-star star-5"></span>
            <span class="memory-dashboard-glow glow-left"></span>
            <span class="memory-dashboard-glow glow-right"></span>
            <span class="memory-dashboard-grid"></span>
        </div>

        <header class="memory-dashboard-header">
            <div class="memory-dashboard-brand">
                <span class="memory-dashboard-kicker">PERSONAL MEMORY ARCHIVE</span>
                <h1>YOUの記憶</h1>
                <p>MEMORY STATUS OVERVIEW</p>
            </div>

            <div class="memory-dashboard-meta">
                <span class="memory-dashboard-chip">ID {{ str_pad((string) $memory->id, 4, '0', STR_PAD_LEFT) }}</span>
                <span class="memory-dashboard-chip">{{ $savedAt->format('Y.m.d') }}</span>
                <span class="memory-dashboard-chip">{{ $memory->period }}</span>
            </div>

            <div class="memory-dashboard-actions">
                <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶玉へ戻る</a>
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧を見る</a>
                <a class="btn btn-secondary" href="{{ route('memories.edit', $memory) }}">修正する</a>
                <form method="post" action="{{ route('memories.destroy', $memory) }}" onsubmit="return confirm('この記憶を削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-secondary btn-danger" type="submit">削除する</button>
                </form>
            </div>
        </header>

        <div class="memory-dashboard-shell">
            <aside class="memory-side-panel memory-side-panel-left">
                <section class="memory-side-block">
                    <div class="memory-side-heading">
                        <span class="memory-side-index">01</span>
                        <div>
                            <h2>保存情報</h2>
                            <p>記憶玉の基本ステータス</p>
                        </div>
                    </div>

                    <div class="memory-stat-grid">
                        <article class="memory-stat-tile">
                            <span class="memory-stat-label">ライフステージ</span>
                            <strong>{{ $memory->period }}</strong>
                        </article>
                        <article class="memory-stat-tile">
                            <span class="memory-stat-label">感情タイプ</span>
                            <strong>{{ $memory->emotion }}</strong>
                        </article>
                        <article class="memory-stat-tile">
                            <span class="memory-stat-label">感情トーン</span>
                            <div class="memory-stat-emotion">
                                <span class="badge {{ $badgeClass }}">{{ $tone }}</span>
                            </div>
                        </article>
                        <article class="memory-stat-tile">
                            <span class="memory-stat-label">保存時刻</span>
                            <strong>{{ $savedAt->format('H:i:s') }}</strong>
                        </article>
                    </div>
                </section>

                <section class="memory-side-block">
                    <div class="memory-side-heading">
                        <span class="memory-side-index">02</span>
                        <div>
                            <h2>記憶キーワード</h2>
                            <p>内容から抽出した断片</p>
                        </div>
                    </div>

                    <div class="memory-tag-cloud">
                        @foreach ($keywords as $keyword)
                            <span class="memory-tag">{{ $keyword }}</span>
                        @endforeach
                    </div>
                </section>

                <section class="memory-side-block memory-side-block-log">
                    <div class="memory-side-heading">
                        <span class="memory-side-index">03</span>
                        <div>
                            <h2>アーカイブログ</h2>
                            <p>保存時の処理ログ</p>
                        </div>
                    </div>

                    <div class="memory-log-list">
                        @foreach ($logEntries as $entry)
                            <article class="memory-log-item">
                                <span class="memory-log-time">{{ $entry['time'] }}</span>
                                <strong>{{ $entry['title'] }}</strong>
                                <p>{{ $entry['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            </aside>

            <section class="memory-core-panel">
                <div class="memory-core-panel-head">
                    <span class="memory-core-label">MEMORY CORE</span>
                    <div class="memory-core-chips">
                        <span>{{ $theme }}</span>
                        <span>{{ $memory->emotion }}</span>
                        <span>{{ $savedAt->format('Y.m.d H:i') }}</span>
                    </div>
                </div>

                <div class="memory-core-stage">
                    <span class="memory-orbit orbit-a"></span>
                    <span class="memory-orbit orbit-b"></span>
                    <span class="memory-orbit orbit-c"></span>
                    <span class="memory-core-axis axis-x"></span>
                    <span class="memory-core-axis axis-y"></span>

                    <div class="memory-core-bubble" style="--bubble-start: {{ $colors[0] }}; --bubble-end: {{ $colors[1] }};">
                        <div class="memory-core-aura"></div>
                        <div class="memory-core-inner">
                            <span class="memory-core-type">{{ $memory->period }}</span>
                            <strong>{{ $theme }}</strong>
                            <small>{{ $memory->emotion }}</small>
                        </div>
                    </div>

                    <div class="memory-core-scan">
                        <span class="memory-core-scan-label left">SCAN 01</span>
                        <span class="memory-core-scan-label right">SYNC {{ min(99, $clarity) }}%</span>
                        <span class="memory-core-scan-label bottom">ARCHIVE SIGNAL</span>
                    </div>
                </div>

                <div class="memory-core-content-card">
                    <div class="memory-core-content-head">
                        <span>記憶内容</span>
                        <strong>{{ $contentLength }} letters</strong>
                    </div>
                    <div class="memory-core-content-body">
                        @foreach ($previewLines as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                    <div class="memory-core-content-footer">
                        <span>{{ $sentenceCount }} fragments analyzed</span>
                        <span>Theme: {{ $theme }}</span>
                    </div>
                </div>
            </section>

            <aside class="memory-side-panel memory-side-panel-right">
                <section class="memory-side-block">
                    <div class="memory-side-heading">
                        <span class="memory-side-index">04</span>
                        <div>
                            <h2>感情解析 TOPICS</h2>
                            <p>ステータスパラメーター</p>
                        </div>
                    </div>

                    <div class="memory-bar-list">
                        @foreach ($bars as $bar)
                            <article class="memory-bar-item">
                                <div class="memory-bar-meta">
                                    <span>{{ $bar['label'] }}</span>
                                    <strong>{{ $bar['value'] }}%</strong>
                                </div>
                                <div class="memory-bar-track">
                                    <span class="memory-bar-fill accent-{{ $bar['accent'] }}" style="width: {{ $bar['value'] }}%;"></span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="memory-side-block">
                    <div class="memory-side-heading">
                        <span class="memory-side-index">05</span>
                        <div>
                            <h2>円環メーター</h2>
                            <p>記憶の保存状態</p>
                        </div>
                    </div>

                    <div class="memory-gauge-grid">
                        @foreach ($gauges as $gauge)
                            <article class="memory-gauge-card">
                                <div class="memory-gauge-ring" style="--value: {{ $gauge['value'] }};">
                                    <div class="memory-gauge-inner">
                                        <strong>{{ $gauge['value'] }}%</strong>
                                    </div>
                                </div>
                                <span>{{ $gauge['label'] }}</span>
                            </article>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </section>

    <style>
        .page.page-memory-status-wide {
            width: calc(100vw - 24px);
            max-width: none;
        }

        .memory-dashboard {
            position: relative;
            min-height: max(760px, calc(100vh - 72px));
            padding: 26px;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 18px;
            border-radius: 36px;
            overflow: hidden;
            color: rgba(236, 244, 255, 0.95);
            background:
                radial-gradient(circle at 16% 20%, rgba(79, 126, 255, 0.18), transparent 24%),
                radial-gradient(circle at 84% 24%, rgba(97, 224, 255, 0.16), transparent 20%),
                radial-gradient(circle at 50% 86%, rgba(120, 90, 255, 0.12), transparent 26%),
                linear-gradient(180deg, #02050d 0%, #040914 38%, #081223 100%);
            box-shadow: 0 36px 90px rgba(3, 7, 18, 0.46);
            isolation: isolate;
        }

        .memory-dashboard::before,
        .memory-dashboard::after {
            content: "";
            position: absolute;
            inset: 16px;
            border-radius: 28px;
            border: 1px solid rgba(132, 184, 255, 0.08);
            pointer-events: none;
        }

        .memory-dashboard::after {
            inset: 0;
            border: 1px solid rgba(180, 220, 255, 0.06);
            border-radius: inherit;
        }

        .memory-dashboard-decor {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .memory-dashboard-star,
        .memory-dashboard-glow,
        .memory-dashboard-grid {
            position: absolute;
        }

        .memory-dashboard-star {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.42);
            animation: memoryStarBlink 5.8s ease-in-out infinite;
        }

        .memory-dashboard-star.star-1 { top: 10%; left: 20%; }
        .memory-dashboard-star.star-2 { top: 18%; right: 16%; width: 3px; height: 3px; animation-delay: 1.2s; }
        .memory-dashboard-star.star-3 { top: 42%; left: 12%; width: 3px; height: 3px; animation-delay: 2.4s; }
        .memory-dashboard-star.star-4 { bottom: 18%; right: 22%; animation-delay: 3.1s; }
        .memory-dashboard-star.star-5 { bottom: 12%; left: 50%; width: 3px; height: 3px; animation-delay: 1.8s; }

        .memory-dashboard-glow {
            border-radius: 50%;
            filter: blur(16px);
            opacity: 0.48;
        }

        .memory-dashboard-glow.glow-left {
            width: 220px;
            height: 220px;
            left: -40px;
            top: 180px;
            background: radial-gradient(circle, rgba(100, 170, 255, 0.24), transparent 68%);
        }

        .memory-dashboard-glow.glow-right {
            width: 260px;
            height: 260px;
            right: -40px;
            top: 120px;
            background: radial-gradient(circle, rgba(92, 232, 255, 0.18), transparent 70%);
        }

        .memory-dashboard-grid {
            inset: 0;
            background-image:
                linear-gradient(rgba(110, 149, 214, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(110, 149, 214, 0.05) 1px, transparent 1px);
            background-size: 84px 84px;
            opacity: 0.24;
            mask-image: radial-gradient(circle at center, rgba(0, 0, 0, 0.85), transparent 84%);
        }

        .memory-dashboard-header,
        .memory-dashboard-shell {
            position: relative;
            z-index: 1;
        }

        .memory-dashboard-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            align-items: start;
            gap: 18px;
        }

        .memory-dashboard-kicker {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(125, 208, 255, 0.8);
            font-size: 11px;
            letter-spacing: 0.34em;
            text-transform: uppercase;
        }

        .memory-dashboard-brand h1 {
            margin: 0;
            font-size: clamp(32px, 3.7vw, 46px);
            line-height: 0.96;
            letter-spacing: 0.06em;
            color: rgba(248, 251, 255, 0.98);
            text-shadow: 0 12px 40px rgba(45, 118, 255, 0.16);
        }

        .memory-dashboard-brand p {
            margin: 8px 0 0;
            color: rgba(176, 200, 235, 0.7);
            font-size: 12px;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }

        .memory-dashboard-meta,
        .memory-dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
            align-items: center;
        }

        .memory-dashboard-chip,
        .memory-dashboard .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(153, 208, 255, 0.14);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.11), rgba(255, 255, 255, 0.02)),
                rgba(10, 18, 35, 0.7);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.08),
                0 10px 28px rgba(2, 7, 18, 0.26);
            color: rgba(235, 243, 255, 0.92);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            backdrop-filter: blur(12px);
        }

        .memory-dashboard .btn:hover {
            transform: translateY(-1px);
            border-color: rgba(153, 219, 255, 0.34);
            background:
                linear-gradient(180deg, rgba(118, 184, 255, 0.22), rgba(255, 255, 255, 0.03)),
                rgba(19, 31, 58, 0.86);
            color: rgba(250, 252, 255, 0.98);
        }

        .memory-dashboard .btn-danger:hover {
            background:
                linear-gradient(180deg, rgba(255, 116, 154, 0.24), rgba(255, 255, 255, 0.03)),
                rgba(48, 18, 32, 0.88);
        }

        .memory-dashboard-shell {
            display: grid;
            grid-template-columns: minmax(260px, 0.86fr) minmax(440px, 1.35fr) minmax(260px, 0.88fr);
            gap: 18px;
            align-items: stretch;
            min-height: 0;
            height: 100%;
        }

        .memory-side-panel,
        .memory-core-panel {
            position: relative;
            min-width: 0;
            border-radius: 28px;
            border: 1px solid rgba(147, 188, 255, 0.11);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.02) 12%, transparent 24%),
                rgba(5, 11, 24, 0.76);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 26px 58px rgba(2, 7, 20, 0.32);
            backdrop-filter: blur(16px);
            overflow: hidden;
        }

        .memory-side-panel::before,
        .memory-core-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.03) 12%, transparent 28%),
                linear-gradient(120deg, rgba(255, 255, 255, 0.09), transparent 24%);
            mix-blend-mode: screen;
            pointer-events: none;
        }

        .memory-side-panel {
            padding: 18px;
            display: grid;
            gap: 14px;
            min-height: 0;
        }

        .memory-side-block {
            position: relative;
            z-index: 1;
            padding: 16px;
            border-radius: 22px;
            border: 1px solid rgba(136, 182, 255, 0.09);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.016)),
                rgba(9, 16, 32, 0.76);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
        }

        .memory-side-heading {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px;
            align-items: center;
            margin-bottom: 14px;
        }

        .memory-side-index {
            display: grid;
            place-items: center;
            width: 34px;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            border: 1px solid rgba(121, 208, 255, 0.22);
            color: rgba(146, 217, 255, 0.92);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            background: rgba(68, 133, 255, 0.08);
        }

        .memory-side-heading h2 {
            margin: 0;
            font-size: 17px;
            color: rgba(244, 249, 255, 0.96);
        }

        .memory-side-heading p {
            margin: 4px 0 0;
            color: rgba(162, 187, 224, 0.68);
            font-size: 12px;
            letter-spacing: 0.05em;
        }

        .memory-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .memory-stat-tile {
            display: grid;
            gap: 8px;
            padding: 14px;
            border-radius: 18px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.015)),
                rgba(68, 108, 178, 0.08);
            border: 1px solid rgba(140, 184, 255, 0.08);
        }

        .memory-stat-label {
            color: rgba(162, 194, 235, 0.68);
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .memory-stat-tile strong {
            color: rgba(249, 251, 255, 0.98);
            font-size: 18px;
            line-height: 1.2;
        }

        .memory-stat-emotion .badge {
            border-radius: 999px;
            padding: 8px 12px;
            color: #06121f;
            font-size: 12px;
        }

        .memory-tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .memory-tag {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid rgba(129, 201, 255, 0.12);
            background:
                linear-gradient(180deg, rgba(105, 191, 255, 0.14), rgba(255, 255, 255, 0.02)),
                rgba(8, 15, 30, 0.92);
            color: rgba(233, 242, 255, 0.9);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        .memory-log-list {
            display: grid;
            gap: 12px;
        }

        .memory-log-item {
            position: relative;
            padding-left: 18px;
            border-left: 1px solid rgba(113, 179, 255, 0.18);
        }

        .memory-log-item::before {
            content: "";
            position: absolute;
            left: -5px;
            top: 6px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: rgba(115, 210, 255, 0.9);
            box-shadow: 0 0 14px rgba(115, 210, 255, 0.4);
        }

        .memory-log-time {
            display: inline-block;
            margin-bottom: 6px;
            color: rgba(129, 203, 255, 0.78);
            font-size: 11px;
            letter-spacing: 0.12em;
        }

        .memory-log-item strong {
            display: block;
            margin-bottom: 6px;
            font-size: 15px;
            color: rgba(248, 251, 255, 0.95);
        }

        .memory-log-item p {
            margin: 0;
            color: rgba(197, 214, 238, 0.78);
            line-height: 1.58;
            font-size: 13px;
        }

        .memory-core-panel {
            padding: 20px;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            gap: 16px;
            min-height: 0;
        }

        .memory-core-panel-head,
        .memory-core-content-card {
            position: relative;
            z-index: 1;
        }

        .memory-core-label {
            display: inline-flex;
            color: rgba(130, 209, 255, 0.82);
            font-size: 11px;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }

        .memory-core-chips {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .memory-core-chips span {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(118, 173, 255, 0.08);
            border: 1px solid rgba(140, 198, 255, 0.1);
            color: rgba(232, 242, 255, 0.9);
            font-size: 12px;
            font-weight: 700;
        }

        .memory-core-stage {
            position: relative;
            min-height: 460px;
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .memory-orbit,
        .memory-core-axis {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .memory-orbit {
            border: 1px solid rgba(128, 180, 255, 0.16);
            box-shadow: 0 0 26px rgba(88, 130, 255, 0.08);
        }

        .memory-orbit.orbit-a {
            width: min(82%, 460px);
            aspect-ratio: 1 / 1;
            transform: rotate(12deg);
        }

        .memory-orbit.orbit-b {
            width: min(92%, 540px);
            aspect-ratio: 1 / 1;
            opacity: 0.68;
            transform: rotate(68deg) scaleX(1.06);
        }

        .memory-orbit.orbit-c {
            width: min(72%, 380px);
            aspect-ratio: 1 / 1;
            opacity: 0.5;
            transform: rotate(-34deg) scaleY(0.88);
        }

        .memory-core-axis.axis-x,
        .memory-core-axis.axis-y {
            background: linear-gradient(90deg, transparent, rgba(140, 194, 255, 0.18), transparent);
        }

        .memory-core-axis.axis-x {
            width: 100%;
            height: 1px;
            top: 50%;
            left: 0;
        }

        .memory-core-axis.axis-y {
            width: 1px;
            height: 100%;
            left: 50%;
            top: 0;
            background: linear-gradient(180deg, transparent, rgba(140, 194, 255, 0.18), transparent);
        }

        .memory-core-bubble {
            --bubble-shell: rgba(244, 248, 255, 0.15);
            position: relative;
            width: min(60%, 340px);
            aspect-ratio: 1 / 1;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background:
                radial-gradient(circle at 30% 22%, rgba(255, 255, 255, 0.88), transparent 16%),
                radial-gradient(circle at 24% 20%, rgba(255, 255, 255, 0.28), transparent 26%),
                radial-gradient(circle at 52% 58%, color-mix(in srgb, var(--bubble-start) 54%, white 46%) 0%, color-mix(in srgb, var(--bubble-start) 28%, transparent 72%) 34%, color-mix(in srgb, var(--bubble-end) 62%, transparent 38%) 68%, rgba(255, 255, 255, 0.05) 100%);
            box-shadow:
                inset -26px -34px 78px rgba(6, 16, 34, 0.28),
                inset 20px 28px 58px rgba(255, 255, 255, 0.16),
                0 0 90px color-mix(in srgb, var(--bubble-end) 24%, transparent 76%),
                0 0 42px color-mix(in srgb, var(--bubble-start) 20%, transparent 80%);
            animation: memoryCorePulse 7s ease-in-out infinite;
        }

        .memory-core-bubble::before,
        .memory-core-bubble::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .memory-core-bubble::before {
            inset: 2%;
            border: 1px solid rgba(239, 247, 255, 0.22);
            filter: blur(8px);
        }

        .memory-core-bubble::after {
            width: 36%;
            height: 18%;
            top: 10%;
            left: 14%;
            background: rgba(255, 255, 255, 0.24);
            filter: blur(18px);
            transform: rotate(-18deg);
        }

        .memory-core-aura {
            position: absolute;
            inset: -16%;
            border-radius: 50%;
            background:
                radial-gradient(circle at 48% 54%, color-mix(in srgb, var(--bubble-end) 24%, transparent 76%) 0%, transparent 60%),
                radial-gradient(circle at 42% 46%, color-mix(in srgb, var(--bubble-start) 18%, transparent 82%) 0%, transparent 54%);
            filter: blur(58px);
            opacity: 0.96;
        }

        .memory-core-inner {
            position: relative;
            z-index: 1;
            width: min(72%, 220px);
            display: grid;
            gap: 8px;
            text-align: center;
        }

        .memory-core-type,
        .memory-core-inner small {
            color: rgba(235, 244, 255, 0.82);
            font-size: 12px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .memory-core-inner strong {
            color: rgba(248, 251, 255, 0.98);
            font-size: clamp(24px, 3vw, 34px);
            line-height: 1.12;
            text-shadow: 0 14px 28px rgba(8, 14, 26, 0.34);
        }

        .memory-core-scan {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .memory-core-scan-label {
            position: absolute;
            color: rgba(124, 205, 255, 0.72);
            font-size: 11px;
            letter-spacing: 0.24em;
            text-transform: uppercase;
        }

        .memory-core-scan-label.left {
            left: 10%;
            top: 22%;
        }

        .memory-core-scan-label.right {
            right: 10%;
            top: 34%;
        }

        .memory-core-scan-label.bottom {
            left: 50%;
            bottom: 9%;
            transform: translateX(-50%);
        }

        .memory-core-content-card {
            padding: 16px 18px;
            border-radius: 22px;
            border: 1px solid rgba(138, 190, 255, 0.1);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
                rgba(8, 14, 27, 0.82);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .memory-core-content-head,
        .memory-core-content-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .memory-core-content-head span,
        .memory-core-content-footer span {
            color: rgba(165, 193, 232, 0.72);
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .memory-core-content-head strong {
            color: rgba(240, 245, 255, 0.94);
            font-size: 13px;
            letter-spacing: 0.08em;
        }

        .memory-core-content-body {
            display: grid;
            gap: 10px;
            margin: 16px 0;
        }

        .memory-core-content-body p {
            margin: 0;
            color: rgba(226, 236, 251, 0.9);
            line-height: 1.74;
            font-size: 15px;
        }

        .memory-bar-list {
            display: grid;
            gap: 12px;
        }

        .memory-bar-item {
            display: grid;
            gap: 8px;
        }

        .memory-bar-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            color: rgba(225, 235, 250, 0.92);
            font-size: 13px;
        }

        .memory-bar-meta span {
            color: rgba(197, 214, 238, 0.76);
            letter-spacing: 0.08em;
        }

        .memory-bar-track {
            position: relative;
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(77, 101, 146, 0.24);
            border: 1px solid rgba(139, 184, 255, 0.06);
        }

        .memory-bar-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            box-shadow: 0 0 16px currentColor;
        }

        .memory-bar-fill.accent-cool {
            color: rgba(90, 203, 255, 0.84);
            background: linear-gradient(90deg, rgba(90, 203, 255, 0.88), rgba(145, 238, 255, 0.9));
        }

        .memory-bar-fill.accent-warm {
            color: rgba(255, 184, 110, 0.88);
            background: linear-gradient(90deg, rgba(255, 162, 87, 0.88), rgba(255, 220, 134, 0.92));
        }

        .memory-bar-fill.accent-bright {
            color: rgba(120, 179, 255, 0.88);
            background: linear-gradient(90deg, rgba(115, 170, 255, 0.88), rgba(183, 208, 255, 0.94));
        }

        .memory-gauge-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .memory-gauge-card {
            display: grid;
            justify-items: center;
            gap: 10px;
            padding: 10px 0 4px;
        }

        .memory-gauge-ring {
            --ring-bg: rgba(84, 101, 140, 0.28);
            width: 104px;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at center, rgba(255, 255, 255, 0.08) 0 42%, transparent 43%),
                conic-gradient(from -90deg, rgba(92, 210, 255, 0.96) calc(var(--value) * 1%), rgba(112, 145, 214, 0.18) 0);
            box-shadow:
                inset 0 0 0 1px rgba(147, 191, 255, 0.12),
                0 0 24px rgba(68, 156, 255, 0.14);
        }

        .memory-gauge-inner {
            width: 74px;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.01)),
                rgba(7, 14, 28, 0.92);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        .memory-gauge-inner strong {
            color: rgba(245, 249, 255, 0.96);
            font-size: 17px;
        }

        .memory-gauge-card span {
            color: rgba(197, 213, 236, 0.8);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-align: center;
        }

        @keyframes memoryCorePulse {
            0% { transform: scale(0.985); }
            50% { transform: scale(1.015); }
            100% { transform: scale(0.985); }
        }

        @keyframes memoryStarBlink {
            0%, 100% { opacity: 0.36; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.18); }
        }

        @media (max-width: 1240px) {
            .memory-dashboard-shell {
                grid-template-columns: minmax(240px, 0.8fr) minmax(380px, 1.2fr) minmax(240px, 0.84fr);
            }

            .memory-core-stage {
                min-height: 400px;
            }
        }

        @media (max-width: 980px) {
            .memory-dashboard {
                min-height: auto;
                padding: 20px;
            }

            .memory-dashboard-header {
                grid-template-columns: 1fr;
            }

            .memory-dashboard-meta,
            .memory-dashboard-actions {
                justify-content: flex-start;
            }

            .memory-dashboard-shell {
                grid-template-columns: 1fr;
            }

            .memory-core-panel {
                order: -1;
            }
        }

        @media (max-width: 640px) {
            .page.page-memory-status-wide {
                width: calc(100vw - 18px);
            }

            .memory-dashboard {
                padding: 16px;
                border-radius: 24px;
            }

            .memory-dashboard-brand h1 {
                font-size: 34px;
            }

            .memory-side-panel,
            .memory-core-panel {
                border-radius: 22px;
            }

            .memory-stat-grid,
            .memory-gauge-grid {
                grid-template-columns: 1fr;
            }

            .memory-core-stage {
                min-height: 320px;
            }

            .memory-core-bubble {
                width: min(72%, 280px);
            }
        }
    </style>
@endsection
