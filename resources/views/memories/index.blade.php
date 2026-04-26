@extends('layouts.app')

@section('title', '全記憶一覧 | 分身AI MVP')
@section('page_class', 'page-memory-index-wide')
@section('hide_auth_dock', '1')

@php
    $dashboardNow = now('Asia/Tokyo');
    $periodShortLabels = [
        '幼少期' => '幼少',
        '小学生' => '小学',
        '中学生' => '中学',
        '高校生' => '高校',
        '大学生' => '大学',
        '成人期' => '成人',
        '不明' => '不明',
    ];
    $distributionLabels = [
        '幼少期' => '幼少期',
        '小学生' => '小学生',
        '中学生' => '中学生',
        '高校生' => '高校生',
        '大学生' => '大学生',
        '成人期' => '成人期',
        '不明' => '不 明',
    ];
    $periodColors = [
        '幼少期' => ['#ff6b6b', '#ff4757'],
        '小学生' => ['#ffa94d', '#ff7c1f'],
        '中学生' => ['#ffe066', '#ffbc00'],
        '高校生' => ['#69db7c', '#2dbe4e'],
        '大学生' => ['#4dabf7', '#1c7ed6'],
        '成人期' => ['#9775fa', '#6741d9'],
        '不明' => ['#f06595', '#c2255c'],
    ];
    $distribution = collect($periods)
        ->map(function ($period) use ($memories, $distributionLabels) {
            return [
                'label' => $distributionLabels[$period] ?? $period,
                'count' => $memories->where('period', $period)->count(),
            ];
        })
        ->values();
    $distributionMax = max(1, (int) $distribution->max('count'));
    $detailRows = $memories->take(8)->values();
    $selectedPeriodShort = $periodShortLabels[$selectedPeriod] ?? $selectedPeriod;
@endphp

@section('content')
    <div class="memory-index-command">
        <div class="memory-index-decor" aria-hidden="true">
            <span class="memory-index-glow glow-a"></span>
            <span class="memory-index-glow glow-b"></span>
            <span class="memory-index-glow glow-c"></span>
            <span class="memory-index-grid"></span>
        </div>

        <div class="memory-index-layout">
            <section class="memory-index-main">
                <section class="memory-index-headerbar">
                    <div class="memory-index-header-main">
                        <div class="memory-index-title-row">
                            <h1>全記憶一覧</h1>
                            <span class="memory-index-count">保存数 <strong>{{ $allCount }}</strong></span>
                            @auth
                            <div class="memory-index-authline">
                                <span class="memory-index-auth-user">{{ auth()->user()->email }}</span>
                                <a class="memory-index-auth-link" href="{{ route('memories.bubbles') }}">記憶の玉</a>
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="memory-index-auth-link" type="submit">ログアウト</button>
                                </form>
                            </div>
                            @endauth
                        </div>
                    </div>
                </section>

                @if (session('status') === 'created')
                    <div class="flash">記憶を保存しました。</div>
                @endif

                @if (session('status') === 'updated')
                    <div class="flash">記憶を更新しました。</div>
                @endif

                @if (session('status') === 'deleted')
                    <div class="flash">記憶を削除しました。</div>
                @endif

                <div class="memory-index-toolbar">
                    <div class="memory-index-toolbar-top">
                        <div class="memory-index-toolbar-copy">
                            <span class="memory-index-toolbar-label">ARCHIVE FILTER</span>
                            <strong>{{ $selectedPeriod === 'すべて' ? '全記憶を横断表示' : $selectedPeriodShort . 'の記憶を表示中' }}</strong>
                        </div>
                    </div>

                    <div class="memory-index-toolbar-main">
                        <form method="get" action="{{ route('memories.index') }}" class="memory-index-search-form">
                            @if ($selectedPeriod !== 'すべて')
                                <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                            @endif
                            <input id="q" type="search" name="q" value="{{ $searchQuery }}" placeholder="キーワードで記憶を探す">
                            <button class="btn btn-secondary memory-index-mini-btn" type="submit">検索</button>
                            @if ($searchQuery !== '' || $selectedPeriod !== 'すべて')
                                <a class="btn btn-secondary memory-index-mini-btn" href="{{ route('memories.index') }}">解除</a>
                            @endif
                        </form>

                        <div class="memory-index-period-filter" aria-label="年代で検索">
                            @php
                                $periodBaseParams = $searchQuery !== '' ? ['q' => $searchQuery] : [];
                            @endphp
                            <a class="memory-index-period-btn {{ $selectedPeriod === 'すべて' ? 'is-active' : '' }}" href="{{ route('memories.index', $periodBaseParams) }}">すべて</a>
                            @foreach ($periods as $period)
                                <a
                                    class="memory-index-period-btn {{ $selectedPeriod === $period ? 'is-active' : '' }}"
                                    href="{{ route('memories.index', array_merge($periodBaseParams, ['period' => $period])) }}"
                                >{{ $period }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($memories->isEmpty())
                    <div class="empty-state memory-index-empty">
                        該当する記憶がありません。<br>
                        キーワードか年代を変えるか、新しい記憶を追加してください。
                    </div>
                @else
                    <div class="memory-index-scroll">
                        <div class="memory-index-timeline">
                            @foreach ($memories as $memory)
                                @php
                                    $tone = $emotionToneMap[$memory->emotion] ?? 'ニュートラル';
                                    $badgeClass = str_contains($tone, 'ポジティブ') ? 'badge-positive' : (str_contains($tone, 'ニュートラル') ? 'badge-neutral' : 'badge-negative');
                                    $orbColors = $periodColors[$memory->period] ?? ['#dce9ff', '#63a6ff'];
                                @endphp
                                <article class="memory-entry">
                                    <div class="memory-entry-shell">
                                        <div class="memory-entry-orb-wrap" style="--orb-a: {{ $orbColors[0] }}; --orb-b: {{ $orbColors[1] }};">
                                            <span class="memory-entry-orb-satellite sat-a"></span>
                                            <span class="memory-entry-orb-satellite sat-b"></span>
                                            <span class="memory-entry-orb-satellite sat-c"></span>
                                            <span class="memory-entry-orb-ring"></span>
                                            <span class="memory-entry-orb"></span>
                                        </div>

                                        <div class="memory-entry-body">
                                            <div class="memory-entry-meta">
                                                <span class="memory-entry-kicker-chip">ARCHIVE {{ str_pad((string) $memory->id, 3, '0', STR_PAD_LEFT) }}</span>
                                                <div class="memory-entry-meta-right">
                                                    <span class="memory-entry-time">{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d H:i') }}</span>
                                                    <div class="memory-entry-actions">
                                                        <a class="memory-entry-action" href="{{ route('memories.edit', $memory) }}">修正</a>
                                                        <form method="post" action="{{ route('memories.destroy', $memory) }}" onsubmit="return confirm('この記憶を削除しますか？');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="memory-entry-action is-danger" type="submit">削除</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="memory-entry-head">
                                                <div class="memory-entry-chips">
                                                    <span class="memory-entry-period">{{ $periodShortLabels[$memory->period] ?? $memory->period }}</span>
                                                    <span class="badge {{ $badgeClass }}">{{ $memory->emotion }}</span>
                                                </div>
                                            </div>

                                            <p class="memory-entry-content">{{ $memory->content }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <aside class="memory-index-sidebar">
                <section class="memory-side-monitor">
                    <div class="memory-side-block">
                        <div class="memory-side-heading">
                            <span class="memory-side-icon">◔</span>
                            <div>
                                <h2>記憶分布</h2>
                                <p>Period distribution</p>
                            </div>
                        </div>

                        <div class="memory-side-summary">
                            <span>表示中の記憶</span>
                            <strong>{{ $memories->count() }}</strong>
                        </div>

                        <div class="memory-side-chart">
                            @foreach ($distribution as $item)
                                @php
                                    $width = max(8, (int) round(($item['count'] / $distributionMax) * 100));
                                    $barClass = $loop->iteration % 3 === 1 ? 'is-cyan' : ($loop->iteration % 3 === 2 ? 'is-orange' : 'is-blue');
                                @endphp
                                <div class="memory-side-bar-row">
                                    <span class="memory-side-bar-label">{{ $item['label'] }}</span>
                                    <div class="memory-side-bar-track">
                                        <span class="memory-side-bar {{ $barClass }}" style="width: {{ $width }}%;"></span>
                                    </div>
                                    <span class="memory-side-bar-count">{{ $item['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="memory-side-block">
                        <div class="memory-side-heading">
                            <span class="memory-side-icon">◎</span>
                            <div>
                                <h2>詳細記憶情報</h2>
                                <p>Detailed memory information</p>
                            </div>
                        </div>

                        <div class="memory-side-table">
                            <div class="memory-side-table-head">
                                <span>保存日</span>
                                <span>年代</span>
                                <span>内容</span>
                                <span>感情</span>
                            </div>

                            <div class="memory-side-table-body">
                                @foreach ($detailRows as $memory)
                                    <div class="memory-side-row">
                                        <span>{{ $memory->created_at->timezone('Asia/Tokyo')->format('m.d') }}</span>
                                        <span>{{ $periodShortLabels[$memory->period] ?? $memory->period }}</span>
                                        <span>{{ \Illuminate\Support\Str::limit($memory->content, 16, '…') }}</span>
                                        <span>{{ $memory->emotion }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <style>
        .page.page-memory-index-wide {
            width: calc(100vw - 24px);
            max-width: none;
            padding: 10px 0;
        }

        .memory-index-command {
            position: relative;
            padding: 24px;
            border-radius: 36px;
            overflow: hidden;
            color: rgba(238, 245, 255, 0.94);
            background:
                radial-gradient(circle at 10% 12%, rgba(110, 161, 255, 0.22), transparent 20%),
                radial-gradient(circle at 82% 16%, rgba(77, 230, 255, 0.14), transparent 18%),
                radial-gradient(circle at 70% 84%, rgba(255, 160, 118, 0.10), transparent 20%),
                linear-gradient(160deg, rgba(5, 10, 24, 0.98) 0%, rgba(8, 16, 38, 0.98) 46%, rgba(5, 11, 26, 0.98) 100%);
            box-shadow:
                0 34px 88px rgba(5, 10, 24, 0.44),
                inset 0 1px 0 rgba(255, 255, 255, 0.04);
            isolation: isolate;
        }

        .memory-index-command::before,
        .memory-index-command::after {
            content: "";
            position: absolute;
            inset: 14px;
            border-radius: 26px;
            border: 1px solid rgba(142, 190, 255, 0.03);
            pointer-events: none;
        }

        .memory-index-command::after {
            inset: 0;
            border-radius: inherit;
            border-color: rgba(255, 255, 255, 0.015);
        }

        .memory-index-decor {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        .memory-index-glow,
        .memory-index-grid {
            position: absolute;
        }

        .memory-index-glow {
            border-radius: 50%;
            filter: blur(8px);
        }

        .memory-index-glow.glow-a {
            width: 240px;
            height: 240px;
            left: -80px;
            top: 120px;
            background: radial-gradient(circle, rgba(88, 164, 255, 0.22), transparent 70%);
        }

        .memory-index-glow.glow-b {
            width: 300px;
            height: 300px;
            right: -90px;
            top: -40px;
            background: radial-gradient(circle, rgba(70, 140, 255, 0.16), transparent 72%);
        }

        .memory-index-glow.glow-c {
            width: 220px;
            height: 220px;
            right: 18%;
            bottom: -120px;
            background: radial-gradient(circle, rgba(255, 142, 94, 0.12), transparent 72%);
        }

        .memory-index-grid {
            inset: 0;
            background-image:
                linear-gradient(rgba(110, 149, 214, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(110, 149, 214, 0.045) 1px, transparent 1px);
            background-size: 82px 82px;
            opacity: 0.22;
            mask-image: radial-gradient(circle at center, rgba(0, 0, 0, 0.84), transparent 88%);
        }

        .memory-index-headerbar,
        .memory-index-layout {
            position: relative;
            z-index: 1;
        }

        .memory-index-headerbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 0;
            margin-bottom: 8px;
        }

        .memory-index-header-main {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            flex-wrap: wrap;
            gap: 0;
            width: 100%;
        }

        .memory-index-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .memory-index-title-row > h1,
        .memory-index-title-row > .memory-index-count,
        .memory-index-title-row > .memory-index-authline {
            flex: 0 0 auto;
        }

        .memory-index-authline {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .memory-index-auth-user {
            color: rgba(227, 236, 255, 0.72);
            font-size: 12px;
            letter-spacing: 0.08em;
        }

        .memory-index-authline form {
            margin: 0;
        }

        .memory-index-auth-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid transparent;
            background: rgba(14, 26, 58, 0.34);
            color: rgba(241, 246, 255, 0.92);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
            cursor: pointer;
        }

        .memory-index-headerbar h1 {
            margin: 0;
            font-size: clamp(32px, 3.4vw, 46px);
            line-height: 1;
            letter-spacing: 0.05em;
            color: rgba(247, 250, 255, 0.98);
            text-shadow: 0 0 24px rgba(120, 170, 255, 0.16);
        }

        .memory-index-count {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 46px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.01)),
                rgba(10, 19, 40, 0.64);
            color: rgba(171, 197, 236, 0.8);
            font-size: 14px;
            font-weight: 600;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.04),
                0 12px 28px rgba(6, 12, 28, 0.12);
        }

        .memory-index-count strong {
            color: rgba(246, 249, 255, 0.98);
            font-size: 26px;
        }

        .memory-index-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 18px;
            align-items: start;
        }

        .memory-index-main {
            min-width: 0;
            display: grid;
            gap: 14px;
        }

        .memory-index-main .flash {
            background: rgba(87, 171, 255, 0.12);
            border: 1px solid transparent;
            color: rgba(225, 239, 255, 0.92);
        }

        .memory-index-toolbar {
            display: grid;
            gap: 10px;
            padding: 0 0 8px;
            border-radius: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
        }

        .memory-index-toolbar-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .memory-index-toolbar-copy {
            display: grid;
            gap: 4px;
        }

        .memory-index-toolbar-label {
            color: rgba(143, 206, 255, 0.78);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.24em;
        }

        .memory-index-toolbar-copy strong {
            color: rgba(246, 249, 255, 0.98);
            font-size: 18px;
            line-height: 1.2;
        }

        .memory-index-toolbar-main {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .memory-index-search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: 0;
        }

        .memory-index-search-form input {
            width: clamp(260px, 24vw, 380px);
            min-height: 46px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.01)),
                rgba(12, 20, 40, 0.72);
            color: rgba(239, 245, 255, 0.94);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.04),
                0 10px 22px rgba(5, 10, 24, 0.10);
        }

        .memory-index-mini-btn,
        .memory-index-period-btn {
            min-height: 34px;
            padding: 0 12px;
            font-size: 12px;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.04),
                0 8px 18px rgba(6, 10, 24, 0.10);
        }

        .memory-index-period-filter {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 0;
        }

        .memory-index-period-filter::-webkit-scrollbar,
        .memory-index-scroll::-webkit-scrollbar,
        .memory-side-table-body::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        .memory-index-period-filter::-webkit-scrollbar-thumb,
        .memory-index-scroll::-webkit-scrollbar-thumb,
        .memory-side-table-body::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 194, 255, 0.24);
        }

        .memory-index-period-btn,
        .memory-index-command .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
                rgba(12, 20, 40, 0.62);
            color: rgba(232, 241, 255, 0.92);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 12px 28px rgba(6, 10, 24, 0.14);
            transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease;
            white-space: nowrap;
        }

        .memory-index-command .btn-primary {
            background:
                linear-gradient(135deg, rgba(129, 214, 255, 0.28), rgba(89, 132, 255, 0.86)),
                rgba(12, 20, 40, 0.7);
            color: rgba(248, 251, 255, 0.98);
        }

        .memory-index-command .btn:hover,
        .memory-index-period-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(196, 224, 255, 0.28);
            background:
                linear-gradient(135deg, rgba(88, 150, 255, 0.3), rgba(34, 73, 171, 0.82)),
                rgba(12, 20, 40, 0.7);
        }

        .memory-index-period-btn.is-active {
            background:
                linear-gradient(135deg, rgba(111, 224, 255, 0.3), rgba(76, 126, 255, 0.9)),
                rgba(12, 20, 40, 0.7);
            color: rgba(250, 252, 255, 0.98);
        }

        .memory-index-command .memory-index-mini-btn,
        .memory-index-command .memory-index-period-btn {
            min-height: 34px;
            padding: 0 12px;
            font-size: 12px;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.04),
                0 8px 18px rgba(6, 10, 24, 0.10);
        }

        .memory-index-scroll {
            max-height: min(76vh, 980px);
            overflow-y: auto;
            padding-right: 6px;
        }

        .memory-index-timeline {
            display: grid;
            gap: 2px;
        }

        .memory-entry { display: block; }

        .memory-entry-shell {
            display: grid;
            grid-template-columns: 82px minmax(0, 1fr);
            gap: 16px;
            align-items: center;
            padding: 16px 0 16px 0;
            border-radius: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
            position: relative;
            overflow: hidden;
        }

        .memory-entry-shell::before {
            content: "";
            position: absolute;
            left: 84px;
            right: 0;
            bottom: 0;
            height: 1px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(255,255,255,0.02), rgba(124, 191, 255, 0.28), rgba(255,255,255,0.02));
            pointer-events: none;
        }

        .memory-entry-shell::after {
            content: "";
            position: absolute;
            left: 94px;
            right: 32px;
            top: 10px;
            bottom: 10px;
            border-radius: 20px;
            background: linear-gradient(90deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01) 18%, rgba(255,255,255,0.01));
            pointer-events: none;
        }

        .memory-entry-orb-wrap {
            display: grid;
            place-items: center;
            position: relative;
            z-index: 1;
            width: 104px;
            height: 104px;
        }

        .memory-entry-orb-ring {
            position: absolute;
            inset: 8px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.01) 58%, transparent 72%);
            box-shadow:
                0 0 0 1px rgba(146, 196, 255, 0.06),
                0 0 44px color-mix(in srgb, var(--orb-a) 18%, transparent);
            opacity: 0.55;
        }

        .memory-entry-orb {
            position: relative;
            width: 68px;
            height: 68px;
            border-radius: 50%;
            box-shadow:
                inset -16px -18px 30px rgba(5, 12, 24, 0.28),
                inset 12px 12px 28px rgba(255, 255, 255, 0.18),
                0 0 22px color-mix(in srgb, var(--orb-b) 42%, transparent),
                0 0 54px color-mix(in srgb, var(--orb-a) 28%, transparent),
                0 0 92px color-mix(in srgb, var(--orb-a) 14%, transparent);
            background:
                radial-gradient(circle at 28% 24%, rgba(255, 255, 255, 0.98), transparent 16%),
                radial-gradient(circle at 42% 38%, rgba(255,255,255,0.20), transparent 24%),
                radial-gradient(circle at 56% 58%, color-mix(in srgb, var(--orb-a) 94%, white 6%), color-mix(in srgb, var(--orb-b) 52%, transparent) 72%, transparent 100%);
        }

        .memory-entry-orb::before {
            content: "";
            position: absolute;
            width: 26px;
            height: 16px;
            left: 14px;
            top: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.48);
            filter: blur(5px);
            transform: rotate(-18deg);
        }

        .memory-entry-orb::after {
            content: "";
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            background: radial-gradient(circle, color-mix(in srgb, var(--orb-a) 28%, transparent) 0%, color-mix(in srgb, var(--orb-b) 16%, transparent) 44%, transparent 72%);
            filter: blur(8px);
            z-index: -1;
        }

        .memory-entry-orb-satellite {
            position: absolute;
            border-radius: 50%;
            background:
                radial-gradient(circle at 30% 26%, rgba(255,255,255,0.82), transparent 18%),
                radial-gradient(circle at 56% 58%, color-mix(in srgb, var(--orb-a) 90%, white 10%), color-mix(in srgb, var(--orb-b) 46%, transparent) 72%, transparent 100%);
            box-shadow:
                inset -8px -10px 18px rgba(5, 12, 24, 0.24),
                0 0 14px color-mix(in srgb, var(--orb-a) 26%, transparent),
                0 0 28px color-mix(in srgb, var(--orb-b) 18%, transparent);
            opacity: 0.92;
        }

        .memory-entry-orb-satellite.sat-a {
            width: 24px;
            height: 24px;
            left: 10px;
            bottom: 20px;
        }

        .memory-entry-orb-satellite.sat-b {
            width: 14px;
            height: 14px;
            right: 16px;
            top: 22px;
        }

        .memory-entry-orb-satellite.sat-c {
            width: 10px;
            height: 10px;
            right: 10px;
            bottom: 16px;
            opacity: 0.78;
        }

        .memory-entry-body {
            display: grid;
            gap: 12px;
            min-width: 0;
            position: relative;
            z-index: 1;
        }

        .memory-entry-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .memory-entry-meta-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .memory-entry-kicker-chip,
        .memory-entry-time {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid transparent;
            background: rgba(10, 20, 44, 0.30);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
        }

        .memory-entry-kicker-chip {
            color: rgba(147, 210, 255, 0.86);
        }

        .memory-entry-time {
            color: rgba(216, 230, 255, 0.82);
        }

        .memory-entry-actions,
        .memory-entry-actions form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .memory-entry-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid transparent;
            background: rgba(16, 28, 64, 0.42);
            color: rgba(242, 247, 255, 0.92);
            font-size: 12px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
            cursor: pointer;
        }

        .memory-entry-action.is-danger {
            background: rgba(104, 28, 52, 0.34);
            color: rgba(255, 224, 232, 0.94);
        }

        .memory-entry-head {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }

        .memory-entry-chips {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .memory-entry-period {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(118, 173, 255, 0.10);
            border: 1px solid transparent;
            color: rgba(238, 245, 255, 0.9);
            font-size: 13px;
            font-weight: 700;
        }

        .memory-entry-content {
            margin: 0;
            color: rgba(232, 240, 255, 0.94);
            font-size: 18px;
            line-height: 1.72;
        }

        .memory-index-empty {
            background: rgba(10, 18, 35, 0.44);
            border: 1px dashed rgba(152, 197, 255, 0.16);
            color: rgba(214, 231, 252, 0.8);
        }

        .memory-index-sidebar {
            min-width: 0;
            padding-top: 0;
        }

        .memory-side-monitor {
            display: grid;
            gap: 14px;
            padding: 14px;
            border-radius: 28px;
            border: 1px solid transparent;
            background:
                radial-gradient(circle at 0% 50%, rgba(255, 78, 176, 0.20), transparent 32%),
                radial-gradient(circle at 100% 0%, rgba(86, 226, 255, 0.26), transparent 36%),
                radial-gradient(circle at 100% 100%, rgba(255, 220, 96, 0.10), transparent 28%),
                linear-gradient(150deg, rgba(56, 24, 86, 0.84), rgba(18, 30, 88, 0.86) 38%, rgba(7, 13, 28, 0.94) 78%);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.08),
                0 24px 60px rgba(2, 6, 18, 0.30),
                0 0 36px rgba(90, 196, 255, 0.12);
            position: relative;
            overflow: hidden;
        }

        .memory-side-monitor::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.10), transparent 28%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), transparent 32%);
            pointer-events: none;
        }

        .memory-side-block {
            display: grid;
            gap: 12px;
            padding: 16px;
            border-radius: 22px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.01)),
                rgba(4, 8, 24, 0.50);
            border: 1px solid transparent;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 16px 32px rgba(4, 10, 22, 0.16);
            position: relative;
            overflow: hidden;
        }

        .memory-side-block::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.06), transparent 30%);
            pointer-events: none;
        }

        .memory-side-heading {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px;
            align-items: center;
        }

        .memory-side-icon {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            color: rgba(137, 212, 255, 0.9);
            border: 1px solid rgba(122, 196, 255, 0.18);
        }

        .memory-side-heading h2 {
            margin: 0;
            color: rgba(246, 249, 255, 0.96);
            font-size: 17px;
        }

        .memory-side-heading p {
            margin: 2px 0 0;
            color: rgba(193, 202, 235, 0.56);
            font-size: 11px;
            letter-spacing: 0.08em;
        }

        .memory-side-summary {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            color: rgba(173, 198, 231, 0.78);
        }

        .memory-side-summary strong {
            color: rgba(197, 241, 255, 0.98);
            font-size: 28px;
            text-shadow:
                0 0 18px rgba(86, 226, 255, 0.28),
                0 0 28px rgba(255, 120, 202, 0.10);
        }

        .memory-side-chart {
            display: grid;
            gap: 12px;
        }

        .memory-side-bar-row {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr) 24px;
            gap: 12px;
            align-items: center;
        }

        .memory-side-bar-label {
            color: rgba(228, 238, 255, 0.90);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .memory-side-bar-count {
            color: rgba(210, 224, 245, 0.78);
            font-size: 11px;
            text-align: right;
        }

        .memory-side-bar-track {
            width: 100%;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            border-radius: 999px;
            background:
                linear-gradient(90deg, rgba(8, 12, 32, 0.84), rgba(18, 26, 58, 0.58));
            overflow: hidden;
            box-shadow:
                inset 0 1px 2px rgba(255, 255, 255, 0.03),
                inset 0 0 0 1px rgba(130, 190, 255, 0.08),
                0 0 18px rgba(82, 228, 255, 0.08);
        }

        .memory-side-bar {
            display: block;
            height: 100%;
            border-radius: 999px;
            box-shadow:
                0 0 18px currentColor,
                0 0 28px color-mix(in srgb, currentColor 38%, transparent);
        }

        .memory-side-bar.is-cyan {
            color: rgba(84, 229, 255, 0.90);
            background: linear-gradient(90deg, rgba(126, 255, 222, 0.98), rgba(87, 223, 255, 0.98) 52%, rgba(107, 156, 255, 0.94));
        }

        .memory-side-bar.is-orange {
            color: rgba(255, 179, 97, 0.94);
            background: linear-gradient(90deg, rgba(255, 233, 110, 0.98), rgba(255, 171, 98, 0.98) 44%, rgba(255, 104, 178, 0.92));
        }

        .memory-side-bar.is-blue {
            color: rgba(138, 173, 255, 0.92);
            background: linear-gradient(90deg, rgba(255, 121, 204, 0.92), rgba(170, 123, 255, 0.96) 36%, rgba(103, 229, 255, 0.98));
        }

        .memory-side-table {
            display: grid;
            gap: 8px;
        }

        .memory-side-table-head,
        .memory-side-row {
            display: grid;
            grid-template-columns: 48px 56px 1fr 48px;
            gap: 8px;
            align-items: center;
        }

        .memory-side-table-head {
            padding: 0 8px;
            color: rgba(160, 182, 217, 0.56);
            font-size: 10px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .memory-side-table-body {
            display: grid;
            gap: 6px;
            max-height: 320px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .memory-side-row {
            padding: 10px 8px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.025);
            color: rgba(232, 240, 255, 0.9);
            font-size: 12px;
            line-height: 1.35;
        }

        @media (max-width: 1180px) {
            .memory-index-layout {
                grid-template-columns: 1fr;
            }

            .memory-index-sidebar {
                order: -1;
            }
        }

        @media (max-width: 820px) {
            .page.page-memory-index-wide {
                width: calc(100vw - 18px);
                padding: 8px 0;
            }

            .memory-index-command {
                padding: 16px;
                border-radius: 24px;
            }

            .memory-index-headerbar h1 {
                font-size: 28px;
            }

            .memory-index-title-row {
                align-items: flex-start;
            }

            .memory-index-authline {
                margin-left: 0;
            }

            .memory-index-toolbar-main {
                align-items: center;
                flex-wrap: wrap;
            }

            .memory-index-search-form {
                width: 100%;
                flex-wrap: wrap;
            }

            .memory-index-search-form input {
                width: 100%;
            }

            .memory-entry-meta-right {
                margin-left: 0;
                justify-content: flex-start;
            }

            .memory-entry-shell {
                grid-template-columns: 1fr;
                padding-left: 0;
            }

            .memory-entry-orb-wrap {
                justify-items: start;
                width: 104px;
                height: 104px;
            }

            .memory-entry-shell::before,
            .memory-entry-shell::after {
                left: 0;
            }

            .memory-entry-content {
                font-size: 16px;
            }

            .memory-side-bar-row {
                grid-template-columns: 52px minmax(0, 1fr) 20px;
            }

        }
    </style>

@endsection
