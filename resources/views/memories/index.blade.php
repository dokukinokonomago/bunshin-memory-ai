@extends('layouts.app')

@section('title', '全記憶一覧 | 分身AI MVP')
@section('page_class', 'page-memory-index-wide')

@php
    $dashboardNow = now('Asia/Tokyo');
    $distribution = collect($periods)
        ->map(function ($period) use ($memories) {
            return [
                'label' => $period,
                'count' => $memories->where('period', $period)->count(),
            ];
        })
        ->values();
    $distributionMax = max(1, (int) $distribution->max('count'));
    $detailRows = $memories->take(8)->values();
@endphp

@section('content')
    <div class="memory-index-command">
        <div class="memory-index-decor" aria-hidden="true">
            <span class="memory-index-glow glow-a"></span>
            <span class="memory-index-glow glow-b"></span>
            <span class="memory-index-glow glow-c"></span>
            <span class="memory-index-grid"></span>
        </div>

        <section class="memory-index-headerbar">
            <div class="memory-index-header-main">
                <h1>全記憶一覧</h1>
                <span class="memory-index-count">保存数 <strong>{{ $allCount }}</strong></span>
            </div>

            <div class="memory-index-header-actions">
                <a class="btn btn-primary" href="{{ route('memories.create') }}">新しい記憶を追加</a>
                <a class="btn btn-secondary" href="{{ route('memories.create.preview') }}">新UI Preview</a>
                <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶の玉へ戻る</a>
            </div>
        </section>

        <div class="memory-index-layout">
            <section class="memory-index-main">
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
                    <div class="memory-index-toolbar-main">
                        <form method="get" action="{{ route('memories.index') }}" class="memory-index-search-form">
                            @if ($selectedPeriod !== 'すべて')
                                <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                            @endif
                            <input id="q" type="search" name="q" value="{{ $searchQuery }}" placeholder="キーワードで記憶を探す">
                            <button class="btn btn-secondary" type="submit">検索</button>
                            @if ($searchQuery !== '' || $selectedPeriod !== 'すべて')
                                <a class="btn btn-secondary" href="{{ route('memories.index') }}">解除</a>
                            @endif
                        </form>

                        <div class="memory-index-toolbar-actions">
                            <a id="editMemoryButton" class="btn btn-secondary is-disabled" href="#" aria-disabled="true">修正</a>
                            <form id="deleteMemoryForm" method="post" action="#" onsubmit="return confirm('この記憶を削除しますか？');">
                                @csrf
                                @method('DELETE')
                                <button id="deleteMemoryButton" class="btn btn-secondary btn-danger" type="submit" disabled>削除</button>
                            </form>
                        </div>
                    </div>

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
                                    $orbClass = str_contains($tone, 'ポジティブ') ? 'is-warm' : (str_contains($tone, 'ニュートラル') ? 'is-cool' : 'is-dream');
                                @endphp
                                <label class="memory-entry">
                                    <input
                                        class="memory-select"
                                        type="radio"
                                        name="selected_memory"
                                        value="{{ $memory->id }}"
                                        data-edit-url="{{ route('memories.edit', $memory) }}"
                                        data-delete-url="{{ route('memories.destroy', $memory) }}"
                                        {{ $loop->first ? 'checked' : '' }}
                                    >

                                    <div class="memory-entry-shell">
                                        <div class="memory-entry-orb-wrap">
                                            <span class="memory-entry-orb {{ $orbClass }}"></span>
                                        </div>

                                        <div class="memory-entry-body">
                                            <div class="memory-entry-head">
                                                <strong>{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d H:i') }}</strong>
                                                <div class="memory-entry-chips">
                                                    <span class="memory-entry-period">{{ $memory->period }}</span>
                                                    <span class="badge {{ $badgeClass }}">{{ $memory->emotion }}</span>
                                                </div>
                                            </div>

                                            <p class="memory-entry-content">{{ $memory->content }}</p>
                                        </div>
                                    </div>
                                </label>
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
                                    $height = max(8, (int) round(($item['count'] / $distributionMax) * 140));
                                    $barClass = $loop->iteration % 3 === 1 ? 'is-cyan' : ($loop->iteration % 3 === 2 ? 'is-orange' : 'is-blue');
                                @endphp
                                <div class="memory-side-bar-col">
                                    <span class="memory-side-bar-count">{{ $item['count'] }}</span>
                                    <div class="memory-side-bar-track">
                                        <span class="memory-side-bar {{ $barClass }}" style="height: {{ $height }}px;"></span>
                                    </div>
                                    <span class="memory-side-bar-label">{{ $item['label'] }}</span>
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
                                        <span>{{ $memory->period }}</span>
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
            padding: 22px;
            border-radius: 34px;
            overflow: hidden;
            color: rgba(238, 245, 255, 0.94);
            background:
                radial-gradient(circle at 16% 16%, rgba(89, 143, 255, 0.16), transparent 22%),
                radial-gradient(circle at 84% 20%, rgba(102, 228, 255, 0.12), transparent 18%),
                radial-gradient(circle at 72% 84%, rgba(255, 139, 181, 0.08), transparent 20%),
                linear-gradient(160deg, #01040c 0%, #060b17 42%, #0a1124 100%);
            box-shadow: 0 28px 72px rgba(6, 10, 24, 0.42);
            isolation: isolate;
        }

        .memory-index-command::before,
        .memory-index-command::after {
            content: "";
            position: absolute;
            inset: 14px;
            border-radius: 26px;
            border: 1px solid rgba(142, 190, 255, 0.08);
            pointer-events: none;
        }

        .memory-index-command::after {
            inset: 0;
            border-radius: inherit;
            border-color: rgba(255, 255, 255, 0.03);
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
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 14px 16px;
            margin-bottom: 16px;
            border-radius: 24px;
            border: 1px solid rgba(139, 188, 255, 0.12);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
                rgba(8, 15, 31, 0.7);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.06),
                0 16px 36px rgba(2, 7, 18, 0.24);
        }

        .memory-index-header-main,
        .memory-index-header-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .memory-index-headerbar h1 {
            margin: 0;
            font-size: clamp(30px, 3.2vw, 42px);
            line-height: 1;
            letter-spacing: 0.03em;
            color: rgba(247, 250, 255, 0.98);
        }

        .memory-index-count {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid rgba(139, 188, 255, 0.14);
            background: rgba(13, 22, 44, 0.84);
            color: rgba(171, 197, 236, 0.8);
            font-size: 14px;
            font-weight: 600;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .memory-index-count strong {
            color: rgba(246, 249, 255, 0.98);
            font-size: 24px;
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
            border: 1px solid rgba(135, 201, 255, 0.16);
            color: rgba(225, 239, 255, 0.92);
        }

        .memory-index-toolbar {
            display: grid;
            gap: 12px;
            padding: 16px;
            border-radius: 24px;
            border: 1px solid rgba(139, 188, 255, 0.1);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.015)),
                rgba(8, 14, 28, 0.68);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 16px 36px rgba(2, 7, 18, 0.2);
        }

        .memory-index-toolbar-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .memory-index-search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 0;
        }

        .memory-index-search-form input {
            width: clamp(240px, 22vw, 360px);
            min-height: 44px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid rgba(160, 203, 255, 0.14);
            background: rgba(12, 20, 40, 0.92);
            color: rgba(239, 245, 255, 0.94);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .memory-index-toolbar-actions,
        .memory-index-toolbar-actions form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .memory-index-period-filter {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 4px;
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
            min-height: 42px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid rgba(166, 204, 255, 0.14);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
                rgba(12, 20, 40, 0.7);
            color: rgba(232, 241, 255, 0.92);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 10px 24px rgba(6, 10, 24, 0.18);
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

        .memory-index-toolbar-actions .is-disabled {
            pointer-events: none;
            opacity: 0.4;
        }

        .memory-index-scroll {
            max-height: min(76vh, 980px);
            overflow-y: auto;
            padding-right: 6px;
        }

        .memory-index-timeline {
            display: grid;
            gap: 14px;
        }

        .memory-entry {
            display: block;
            cursor: pointer;
        }

        .memory-select {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .memory-entry-shell {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 18px;
            align-items: center;
            padding: 18px 20px;
            border-radius: 28px;
            border: 1px solid rgba(150, 193, 255, 0.09);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.015)),
                rgba(9, 15, 30, 0.72);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 18px 40px rgba(4, 10, 22, 0.18);
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .memory-entry:hover .memory-entry-shell {
            transform: translateY(-2px);
            border-color: rgba(174, 214, 255, 0.24);
            box-shadow: 0 22px 46px rgba(4, 10, 22, 0.24);
        }

        .memory-select:checked + .memory-entry-shell {
            border-color: rgba(157, 214, 255, 0.28);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.06),
                0 24px 58px rgba(20, 54, 120, 0.26);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.02)),
                rgba(10, 18, 35, 0.82);
        }

        .memory-entry-orb-wrap {
            display: grid;
            place-items: center;
        }

        .memory-entry-orb {
            position: relative;
            width: 62px;
            height: 62px;
            border-radius: 50%;
            box-shadow:
                inset -12px -14px 28px rgba(5, 12, 24, 0.24),
                inset 10px 10px 24px rgba(255, 255, 255, 0.16),
                0 0 42px rgba(116, 180, 255, 0.16);
        }

        .memory-entry-orb::before {
            content: "";
            position: absolute;
            width: 20px;
            height: 12px;
            left: 12px;
            top: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            filter: blur(4px);
            transform: rotate(-18deg);
        }

        .memory-entry-orb.is-warm {
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.9), transparent 18%),
                radial-gradient(circle at 56% 58%, rgba(255, 196, 134, 0.88), rgba(255, 153, 110, 0.24) 72%, transparent 100%);
        }

        .memory-entry-orb.is-cool {
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.9), transparent 18%),
                radial-gradient(circle at 56% 58%, rgba(132, 212, 255, 0.88), rgba(103, 147, 255, 0.24) 72%, transparent 100%);
        }

        .memory-entry-orb.is-dream {
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.9), transparent 18%),
                radial-gradient(circle at 56% 58%, rgba(193, 172, 255, 0.88), rgba(124, 110, 255, 0.24) 72%, transparent 100%);
        }

        .memory-entry-body {
            display: grid;
            gap: 12px;
            min-width: 0;
        }

        .memory-entry-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .memory-entry-head strong {
            color: rgba(247, 250, 255, 0.98);
            font-size: 24px;
            line-height: 1.1;
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
            background: rgba(118, 173, 255, 0.08);
            border: 1px solid rgba(140, 198, 255, 0.1);
            color: rgba(232, 242, 255, 0.84);
            font-size: 13px;
            font-weight: 700;
        }

        .memory-entry-content {
            margin: 0;
            color: rgba(228, 236, 251, 0.92);
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
        }

        .memory-side-monitor {
            display: grid;
            gap: 14px;
            padding: 14px;
            border-radius: 24px;
            border: 1px solid rgba(122, 170, 255, 0.12);
            background:
                linear-gradient(180deg, rgba(20, 25, 40, 0.98), rgba(10, 15, 28, 0.98)),
                #0b0f19;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 18px 48px rgba(2, 6, 18, 0.36);
        }

        .memory-side-block {
            display: grid;
            gap: 12px;
            padding: 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(136, 182, 255, 0.08);
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
            color: rgba(162, 181, 214, 0.6);
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
            color: rgba(116, 231, 255, 0.96);
            font-size: 28px;
        }

        .memory-side-chart {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
            align-items: end;
            min-height: 190px;
        }

        .memory-side-bar-col {
            display: grid;
            gap: 8px;
            justify-items: center;
        }

        .memory-side-bar-count,
        .memory-side-bar-label {
            color: rgba(176, 198, 232, 0.72);
            font-size: 10px;
            letter-spacing: 0.04em;
            text-align: center;
        }

        .memory-side-bar-track {
            width: 22px;
            height: 144px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
        }

        .memory-side-bar {
            display: block;
            width: 100%;
            border-radius: 999px;
            box-shadow: 0 0 14px currentColor;
        }

        .memory-side-bar.is-cyan {
            color: rgba(82, 228, 255, 0.88);
            background: linear-gradient(180deg, rgba(82, 228, 255, 0.92), rgba(64, 176, 255, 0.84));
        }

        .memory-side-bar.is-orange {
            color: rgba(255, 153, 92, 0.9);
            background: linear-gradient(180deg, rgba(255, 165, 103, 0.94), rgba(255, 110, 92, 0.84));
        }

        .memory-side-bar.is-blue {
            color: rgba(116, 163, 255, 0.9);
            background: linear-gradient(180deg, rgba(116, 163, 255, 0.94), rgba(81, 122, 255, 0.82));
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
            background: rgba(255, 255, 255, 0.03);
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

            .memory-index-headerbar,
            .memory-index-toolbar,
            .memory-side-monitor {
                border-radius: 20px;
            }

            .memory-index-toolbar-main {
                align-items: stretch;
            }

            .memory-index-search-form {
                width: 100%;
            }

            .memory-index-search-form input {
                width: 100%;
            }

            .memory-index-toolbar-actions,
            .memory-index-toolbar-actions form {
                width: 100%;
            }

            .memory-index-toolbar-actions .btn,
            .memory-index-toolbar-actions form button {
                flex: 1 1 0;
            }

            .memory-entry-shell {
                grid-template-columns: 1fr;
            }

            .memory-entry-orb-wrap {
                justify-items: start;
            }

            .memory-entry-head strong {
                font-size: 20px;
            }

            .memory-entry-content {
                font-size: 16px;
            }

        }
    </style>

    @if ($memories->isNotEmpty())
        <script>
            const memoryRadios = document.querySelectorAll('input[name="selected_memory"]');
            const editButton = document.getElementById('editMemoryButton');
            const deleteForm = document.getElementById('deleteMemoryForm');
            const deleteButton = document.getElementById('deleteMemoryButton');

            function syncMemoryActions(target) {
                if (!target) {
                    return;
                }

                editButton.href = target.dataset.editUrl;
                editButton.classList.remove('is-disabled');
                editButton.removeAttribute('aria-disabled');
                deleteForm.action = target.dataset.deleteUrl;
                deleteButton.disabled = false;
            }

            memoryRadios.forEach((radio) => {
                radio.addEventListener('change', () => syncMemoryActions(radio));
            });

            syncMemoryActions(document.querySelector('input[name="selected_memory"]:checked'));
        </script>
    @endif
@endsection
