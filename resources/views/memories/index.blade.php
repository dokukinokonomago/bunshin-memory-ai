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

        <section class="memory-index-topbar">
            <div class="memory-index-topbar-main">
                <h1>全記憶一覧</h1>
                <span class="memory-index-count-pill">保存数 <strong>{{ $allCount }}</strong></span>
            </div>

            <div class="memory-index-topbar-actions">
                <a class="btn btn-primary" href="{{ route('memories.create') }}">新しい記憶を追加</a>
                <a class="btn btn-secondary" href="{{ route('memories.create.preview') }}">新UI Preview</a>
                <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶の玉へ戻る</a>
            </div>
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
            color: #283142;
            background:
                radial-gradient(circle at 12% 16%, rgba(90, 171, 255, 0.24), transparent 20%),
                radial-gradient(circle at 88% 10%, rgba(255, 182, 102, 0.22), transparent 18%),
                radial-gradient(circle at 72% 80%, rgba(128, 232, 214, 0.2), transparent 22%),
                linear-gradient(180deg, rgba(251, 248, 241, 0.96), rgba(238, 240, 246, 0.94));
            box-shadow: 0 24px 60px rgba(112, 132, 168, 0.22);
            isolation: isolate;
        }

        .memory-index-cosmos::before,
        .memory-index-cosmos::after {
            content: "";
            position: absolute;
            inset: 14px;
            border-radius: 26px;
            border: 1px solid rgba(255, 255, 255, 0.42);
            pointer-events: none;
        }

        .memory-index-cosmos::after {
            inset: 0;
            border-radius: inherit;
            border-color: rgba(255, 255, 255, 0.28);
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
            background: radial-gradient(circle, rgba(105, 172, 255, 0.18), transparent 68%);
        }

        .memory-index-orb.orb-b {
            width: 360px;
            height: 360px;
            right: -100px;
            top: -80px;
            background: radial-gradient(circle, rgba(255, 196, 108, 0.18), transparent 70%);
        }

        .memory-index-orb.orb-c {
            width: 260px;
            height: 260px;
            right: 20%;
            bottom: -140px;
            background: radial-gradient(circle, rgba(104, 222, 193, 0.16), transparent 72%);
        }

        .memory-index-ring {
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.28);
            opacity: 0.52;
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

        .memory-index-topbar,
        .memory-index-panel {
            position: relative;
            z-index: 1;
        }

        .memory-index-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .memory-index-topbar,
        .memory-index-panel {
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.56), rgba(255, 255, 255, 0.24) 18%, rgba(255, 255, 255, 0.16) 100%),
                rgba(255, 255, 255, 0.18);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.76),
                inset 0 -1px 0 rgba(255, 255, 255, 0.2),
                0 18px 40px rgba(131, 146, 175, 0.18);
            backdrop-filter: blur(22px) saturate(1.2);
        }

        .memory-index-topbar {
            padding: 14px 18px;
        }

        .memory-index-topbar-main,
        .memory-index-topbar-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .memory-index-topbar h1 {
            margin: 0;
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1;
            letter-spacing: 0.03em;
            color: #273244;
        }

        .memory-index-count-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.48);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.28)),
                rgba(255, 255, 255, 0.22);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.88),
                inset 0 -1px 0 rgba(146, 178, 220, 0.26),
                0 14px 28px rgba(124, 140, 170, 0.18);
            color: #56627a;
            font-size: 15px;
            font-weight: 600;
        }

        .memory-index-count-pill strong {
            color: #283142;
            font-size: 24px;
        }

        .memory-index-panel {
            padding: 18px;
            display: grid;
            gap: 14px;
        }

        .memory-index-panel .flash {
            background: rgba(255, 255, 255, 0.46);
            border: 1px solid rgba(255, 255, 255, 0.48);
            color: #30405a;
        }

        .memory-index-toolbar {
            display: grid;
            gap: 12px;
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
            border: 1px solid rgba(255, 255, 255, 0.5);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.3)),
                rgba(255, 255, 255, 0.18);
            color: #2d384c;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.92),
                inset 0 -1px 0 rgba(140, 174, 219, 0.18);
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
            border: 1px solid rgba(255, 255, 255, 0.5);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.76), rgba(255, 255, 255, 0.28)),
                rgba(255, 255, 255, 0.18);
            color: #324055;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                inset 0 -1px 0 rgba(140, 174, 219, 0.2),
                0 10px 24px rgba(124, 140, 170, 0.14);
            transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease;
            white-space: nowrap;
        }

        .memory-index-cosmos .btn-primary {
            background:
                linear-gradient(135deg, rgba(120, 203, 255, 0.72), rgba(89, 132, 255, 0.86)),
                rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }

        .memory-index-cosmos .btn:hover,
        .memory-index-period-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.7);
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.38)),
                rgba(255, 255, 255, 0.22);
        }

        .memory-index-period-btn.is-active {
            background:
                linear-gradient(135deg, rgba(120, 203, 255, 0.54), rgba(92, 170, 255, 0.72)),
                rgba(255, 255, 255, 0.28);
            color: #2a3550;
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
            border: 1px solid rgba(255, 255, 255, 0.44);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.64), rgba(255, 255, 255, 0.26)),
                rgba(255, 255, 255, 0.18);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                inset 0 -1px 0 rgba(146, 178, 220, 0.18),
                0 18px 40px rgba(124, 140, 170, 0.14);
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .memory-entry:hover .memory-entry-shell {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.68);
            box-shadow: 0 22px 46px rgba(124, 140, 170, 0.18);
        }

        .memory-select:checked + .memory-entry-shell {
            border-color: rgba(140, 196, 255, 0.62);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.94),
                0 24px 58px rgba(108, 144, 205, 0.24);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.34)),
                rgba(255, 255, 255, 0.22);
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
            color: #2a3550;
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
            background: rgba(255, 255, 255, 0.42);
            border: 1px solid rgba(255, 255, 255, 0.54);
            color: #42526b;
            font-size: 13px;
            font-weight: 700;
        }

        .memory-entry-content {
            margin: 0;
            color: #38465d;
            font-size: 18px;
            line-height: 1.72;
        }

        .memory-index-empty {
            background: rgba(255, 255, 255, 0.36);
            border: 1px dashed rgba(255, 255, 255, 0.48);
            color: #50607a;
        }

        @media (max-width: 1180px) {
            .memory-index-topbar {
                align-items: flex-start;
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
            .memory-index-topbar {
                border-radius: 22px;
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
