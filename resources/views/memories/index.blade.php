@extends('layouts.app')

@section('title', '全記憶一覧 | 分身AI MVP')
@section('page_class', 'page-memory-index-wide')

@section('content')
    <div class="memory-index-cosmos">
        <div class="memory-index-decor" aria-hidden="true">
            <span class="memory-index-orb orb-a"></span>
            <span class="memory-index-orb orb-b"></span>
            <span class="memory-index-orb orb-c"></span>
            <span class="memory-index-ring ring-a"></span>
            <span class="memory-index-ring ring-b"></span>
            <span class="memory-index-grid"></span>
        </div>

        <section class="memory-index-top">
            <div class="memory-index-hero-panel">
                <div class="memory-index-hero-copy">
                    <span class="memory-index-kicker">MEMORY COSMOS INDEX</span>
                    <h1>全記憶一覧</h1>
                    <p>
                        記憶玉のアーカイブを俯瞰しながら、検索、年代切り替え、修正、削除まで一気に行える一覧画面です。
                        記憶ステータス画面と同じトーンで、よりダイナミックに全体を見渡せるように整えています。
                    </p>

                    <div class="memory-index-hero-actions">
                        <a class="btn btn-primary" href="{{ route('memories.create') }}">新しい記憶を追加</a>
                        <a class="btn btn-secondary" href="{{ route('memories.create.preview') }}">新UI Preview</a>
                        <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶の玉へ戻る</a>
                    </div>
                </div>

                <div class="memory-index-hero-orbit" aria-hidden="true">
                    <span class="memory-index-hero-ring ring-outer"></span>
                    <span class="memory-index-hero-ring ring-middle"></span>
                    <span class="memory-index-hero-ring ring-inner"></span>
                    <span class="memory-index-hero-core"></span>
                </div>
            </div>

            <aside class="memory-index-status-panel">
                <article class="memory-index-status-card memory-index-status-total">
                    <span class="memory-index-status-label">TOTAL MEMORIES</span>
                    <strong>{{ $allCount }}</strong>
                    <small>保存済みの記憶玉</small>
                </article>

                <article class="memory-index-status-card memory-index-status-search">
                    <span class="memory-index-status-label">SEARCH MODE</span>
                    <strong>{{ $searchQuery === '' ? 'ALL' : 'FILTERED' }}</strong>
                    <small>{{ $searchQuery === '' ? '全件を表示中' : '絞り込み中' }}</small>
                </article>

                <article class="memory-index-status-note">
                    <p>一覧カードは新しい順です。カードを選ぶと、修正と削除がそのまま使えます。</p>
                </article>
            </aside>
        </section>

        <section class="memory-index-panel">
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
    </div>

    <style>
        .page.page-memory-index-wide {
            width: calc(100vw - 24px);
            max-width: none;
            padding: 10px 0;
        }

        .memory-index-cosmos {
            position: relative;
            padding: 22px;
            border-radius: 34px;
            overflow: hidden;
            color: rgba(238, 245, 255, 0.94);
            background:
                radial-gradient(circle at 16% 16%, rgba(89, 143, 255, 0.18), transparent 22%),
                radial-gradient(circle at 82% 14%, rgba(102, 228, 255, 0.16), transparent 18%),
                radial-gradient(circle at 72% 84%, rgba(255, 139, 181, 0.12), transparent 20%),
                linear-gradient(160deg, #02040b 0%, #040916 42%, #0a1124 100%);
            box-shadow: 0 30px 80px rgba(6, 10, 24, 0.36);
            isolation: isolate;
        }

        .memory-index-cosmos::before,
        .memory-index-cosmos::after {
            content: "";
            position: absolute;
            inset: 14px;
            border-radius: 26px;
            border: 1px solid rgba(138, 190, 255, 0.08);
            pointer-events: none;
        }

        .memory-index-cosmos::after {
            inset: 0;
            border-radius: inherit;
            border-color: rgba(170, 214, 255, 0.05);
        }

        .memory-index-decor {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        .memory-index-orb,
        .memory-index-ring,
        .memory-index-grid {
            position: absolute;
        }

        .memory-index-orb {
            border-radius: 50%;
            filter: blur(0.2px);
        }

        .memory-index-orb.orb-a {
            width: 280px;
            height: 280px;
            left: -90px;
            top: 32%;
            background: radial-gradient(circle, rgba(105, 172, 255, 0.24), transparent 68%);
        }

        .memory-index-orb.orb-b {
            width: 360px;
            height: 360px;
            right: -100px;
            top: -80px;
            background: radial-gradient(circle, rgba(92, 226, 255, 0.18), transparent 70%);
        }

        .memory-index-orb.orb-c {
            width: 260px;
            height: 260px;
            right: 20%;
            bottom: -140px;
            background: radial-gradient(circle, rgba(255, 145, 188, 0.16), transparent 72%);
        }

        .memory-index-ring {
            border-radius: 50%;
            border: 1px solid rgba(131, 186, 255, 0.12);
            opacity: 0.62;
        }

        .memory-index-ring.ring-a {
            width: 460px;
            height: 460px;
            left: 58%;
            top: -120px;
            transform: rotate(22deg);
        }

        .memory-index-ring.ring-b {
            width: 560px;
            height: 560px;
            right: -160px;
            bottom: -260px;
            transform: rotate(-18deg);
        }

        .memory-index-grid {
            inset: 0;
            background-image:
                linear-gradient(rgba(110, 149, 214, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(110, 149, 214, 0.05) 1px, transparent 1px);
            background-size: 82px 82px;
            opacity: 0.18;
            mask-image: radial-gradient(circle at center, rgba(0, 0, 0, 0.84), transparent 88%);
        }

        .memory-index-top,
        .memory-index-panel {
            position: relative;
            z-index: 1;
        }

        .memory-index-top {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(300px, 0.82fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .memory-index-hero-panel,
        .memory-index-status-panel,
        .memory-index-panel {
            border-radius: 30px;
            border: 1px solid rgba(150, 191, 255, 0.1);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.02) 14%, transparent 28%),
                rgba(6, 12, 27, 0.74);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.08),
                0 24px 58px rgba(2, 6, 18, 0.28);
            backdrop-filter: blur(16px);
        }

        .memory-index-hero-panel {
            position: relative;
            min-height: 286px;
            padding: 28px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 240px;
            gap: 18px;
            overflow: hidden;
        }

        .memory-index-kicker {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(131, 209, 255, 0.84);
            font-size: 11px;
            letter-spacing: 0.32em;
            text-transform: uppercase;
        }

        .memory-index-hero-panel h1 {
            margin: 0 0 14px;
            font-size: clamp(42px, 5vw, 72px);
            line-height: 0.94;
            letter-spacing: 0.04em;
            color: rgba(248, 251, 255, 0.98);
        }

        .memory-index-hero-panel p {
            margin: 0;
            max-width: 760px;
            color: rgba(193, 214, 244, 0.8);
            line-height: 1.9;
            font-size: 16px;
        }

        .memory-index-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        .memory-index-hero-orbit {
            position: relative;
            display: grid;
            place-items: center;
            min-height: 220px;
        }

        .memory-index-hero-ring,
        .memory-index-hero-core {
            position: absolute;
            border-radius: 50%;
        }

        .memory-index-hero-ring {
            border: 1px solid rgba(136, 194, 255, 0.18);
        }

        .memory-index-hero-ring.ring-outer {
            width: 220px;
            height: 220px;
            transform: rotate(22deg);
        }

        .memory-index-hero-ring.ring-middle {
            width: 176px;
            height: 176px;
            transform: rotate(64deg);
            opacity: 0.7;
        }

        .memory-index-hero-ring.ring-inner {
            width: 122px;
            height: 122px;
            transform: rotate(-18deg);
            opacity: 0.52;
        }

        .memory-index-hero-core {
            width: 128px;
            height: 128px;
            background:
                radial-gradient(circle at 30% 22%, rgba(255, 255, 255, 0.9), transparent 18%),
                radial-gradient(circle at 56% 58%, rgba(255, 182, 124, 0.84), rgba(118, 170, 255, 0.26) 74%, transparent 100%);
            box-shadow:
                inset -20px -22px 58px rgba(6, 14, 30, 0.26),
                inset 18px 18px 44px rgba(255, 255, 255, 0.16),
                0 0 64px rgba(118, 170, 255, 0.28);
        }

        .memory-index-status-panel {
            padding: 22px;
            display: grid;
            gap: 16px;
            align-content: start;
        }

        .memory-index-status-card,
        .memory-index-status-note {
            border-radius: 24px;
            border: 1px solid rgba(145, 189, 255, 0.08);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.015)),
                rgba(9, 16, 32, 0.54);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .memory-index-status-card {
            min-height: 132px;
            padding: 18px;
            display: grid;
            align-content: center;
            justify-items: start;
        }

        .memory-index-status-label {
            color: rgba(159, 196, 240, 0.7);
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .memory-index-status-card strong {
            margin-top: 8px;
            color: rgba(249, 251, 255, 0.98);
            font-size: 42px;
            line-height: 1;
        }

        .memory-index-status-card small,
        .memory-index-status-note p {
            margin-top: 8px;
            color: rgba(190, 212, 241, 0.72);
            font-size: 14px;
            line-height: 1.7;
        }

        .memory-index-status-note {
            padding: 18px;
        }

        .memory-index-status-note p {
            margin: 0;
        }

        .memory-index-panel {
            padding: 20px;
            display: grid;
            gap: 16px;
        }

        .memory-index-panel .flash {
            background: rgba(102, 195, 255, 0.12);
            border: 1px solid rgba(139, 206, 255, 0.16);
            color: rgba(222, 240, 255, 0.92);
        }

        .memory-index-toolbar {
            display: grid;
            gap: 14px;
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
            width: clamp(240px, 22vw, 340px);
            min-height: 44px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid rgba(153, 203, 255, 0.14);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
                rgba(9, 16, 32, 0.74);
            color: rgba(239, 245, 255, 0.94);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
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
        .memory-index-scroll::-webkit-scrollbar {
            height: 8px;
            width: 10px;
        }

        .memory-index-period-filter::-webkit-scrollbar-thumb,
        .memory-index-scroll::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 194, 255, 0.24);
        }

        .memory-index-period-btn,
        .memory-index-cosmos .btn {
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

        .memory-index-cosmos .btn-primary {
            background:
                linear-gradient(135deg, rgba(129, 214, 255, 0.28), rgba(89, 132, 255, 0.86)),
                rgba(12, 20, 40, 0.7);
            color: rgba(248, 251, 255, 0.98);
        }

        .memory-index-cosmos .btn:hover,
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
            max-height: min(68vh, 920px);
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

        @media (max-width: 1180px) {
            .memory-index-top {
                grid-template-columns: 1fr;
            }

            .memory-index-hero-panel {
                grid-template-columns: 1fr;
            }

            .memory-index-hero-orbit {
                min-height: 180px;
            }
        }

        @media (max-width: 820px) {
            .page.page-memory-index-wide {
                width: calc(100vw - 18px);
                padding: 8px 0;
            }

            .memory-index-cosmos {
                padding: 16px;
                border-radius: 24px;
            }

            .memory-index-panel,
            .memory-index-status-panel,
            .memory-index-hero-panel {
                border-radius: 22px;
            }

            .memory-index-hero-panel h1 {
                font-size: 42px;
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
