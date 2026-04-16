@extends('layouts.app')

@section('title', '全記憶一覧 | 分身AI MVP')

@section('content')
    <div class="memory-index-space">
        <section class="hero memory-index-hero">
            <div class="hero-card">
                <span class="eyebrow">Memory Timeline</span>
                <h1>全記憶一覧</h1>
                <p class="hero-copy">
                    保存されている記憶を時系列で一覧できます。キーワード検索で絞り込みながら、修正と削除もこの画面から行えます。
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="{{ route('memories.create') }}">新しい記憶を追加</a>
                    <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶の玉へ戻る</a>
                </div>
            </div>

            <aside class="side-card">
                <div class="stat-grid">
                    <div class="stat">
                        <span class="stat-label">total memories</span>
                        <div class="stat-value">{{ $allCount }}</div>
                    </div>
                    <div class="stat">
                        <span class="stat-label">search</span>
                        <div class="stat-value">{{ $searchQuery === '' ? 'all' : 'filtered' }}</div>
                    </div>
                </div>

                <div class="note-card">
                    <p>記憶は新しい順で表示しています。上部でキーワード検索し、対象を選ぶと修正と削除が有効になります。</p>
                </div>
            </aside>
        </section>

        <section class="panel memory-list-panel">
            @if (session('status') === 'created')
                <div class="flash">記憶を保存しました。</div>
            @endif

            @if (session('status') === 'updated')
                <div class="flash">記憶を更新しました。</div>
            @endif

            @if (session('status') === 'deleted')
                <div class="flash">記憶を削除しました。</div>
            @endif

            <div class="memory-toolbar">
                <div class="memory-toolbar-main">
                    <form method="get" action="{{ route('memories.index') }}" class="memory-search-form">
                        @if ($selectedPeriod !== 'すべて')
                            <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                        @endif
                        <input id="q" type="search" name="q" value="{{ $searchQuery }}" placeholder="キーワード">
                        <button class="btn btn-secondary" type="submit">検索</button>
                        @if ($searchQuery !== '' || $selectedPeriod !== 'すべて')
                            <a class="btn btn-secondary" href="{{ route('memories.index') }}">解除</a>
                        @endif
                    </form>

                    <div class="memory-toolbar-actions">
                        <a id="editMemoryButton" class="btn btn-secondary is-disabled" href="#" aria-disabled="true">修正</a>
                        <form id="deleteMemoryForm" method="post" action="#" onsubmit="return confirm('この記憶を削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <button id="deleteMemoryButton" class="btn btn-secondary btn-danger" type="submit" disabled>削除</button>
                        </form>
                    </div>
                </div>

                <div class="memory-period-filter" aria-label="年代で検索">
                    @php
                        $periodBaseParams = $searchQuery !== '' ? ['q' => $searchQuery] : [];
                    @endphp
                    <a class="memory-period-btn {{ $selectedPeriod === 'すべて' ? 'is-active' : '' }}" href="{{ route('memories.index', $periodBaseParams) }}">すべて</a>
                    @foreach ($periods as $period)
                        <a
                            class="memory-period-btn {{ $selectedPeriod === $period ? 'is-active' : '' }}"
                            href="{{ route('memories.index', array_merge($periodBaseParams, ['period' => $period])) }}"
                        >{{ $period }}</a>
                    @endforeach
                </div>
            </div>

            @if ($memories->isEmpty())
                <div class="empty-state">
                    該当する記憶がありません。<br>
                    キーワードを変えるか、新しい記憶を追加してください。
                </div>
            @else
                <div class="memory-list-scroll">
                    <div class="memory-timeline">
                        @foreach ($memories as $memory)
                            @php
                                $tone = $emotionToneMap[$memory->emotion] ?? 'ニュートラル';
                                $badgeClass = str_contains($tone, 'ポジティブ') ? 'badge-positive' : (str_contains($tone, 'ニュートラル') ? 'badge-neutral' : 'badge-negative');
                            @endphp
                            <label class="memory-row">
                                <input
                                    class="memory-select"
                                    type="radio"
                                    name="selected_memory"
                                    value="{{ $memory->id }}"
                                    data-edit-url="{{ route('memories.edit', $memory) }}"
                                    data-delete-url="{{ route('memories.destroy', $memory) }}"
                                    {{ $loop->first ? 'checked' : '' }}
                                >
                                <div class="memory-row-body">
                                    <div class="memory-row-meta">
                                        <strong>{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d H:i') }}</strong>
                                        <span>{{ $memory->period }}</span>
                                        <span class="badge {{ $badgeClass }}">{{ $memory->emotion }}</span>
                                    </div>
                                    <p class="memory-row-content">{{ $memory->content }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>

    <style>
        .memory-index-space {
            position: relative;
            padding: 28px;
            border-radius: 30px;
            overflow: hidden;
            background:
                radial-gradient(circle at 18% 18%, rgba(86, 132, 255, 0.18), transparent 20%),
                radial-gradient(circle at 82% 16%, rgba(126, 209, 255, 0.14), transparent 18%),
                radial-gradient(circle at 50% 72%, rgba(88, 108, 255, 0.12), transparent 26%),
                linear-gradient(160deg, #02040b 0%, #050916 48%, #0a1124 100%);
            color: rgba(238, 245, 255, 0.94);
            box-shadow: 0 30px 80px rgba(6, 10, 24, 0.36);
        }

        .memory-index-space::before,
        .memory-index-space::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .memory-index-space::before {
            width: 400px;
            height: 400px;
            left: -120px;
            top: -120px;
            background: radial-gradient(circle, rgba(91, 155, 255, 0.16), transparent 68%);
        }

        .memory-index-space::after {
            width: 320px;
            height: 320px;
            right: 10%;
            bottom: -140px;
            background: radial-gradient(circle, rgba(120, 214, 255, 0.12), transparent 70%);
        }

        .memory-index-space .hero-card,
        .memory-index-space .side-card,
        .memory-index-space .panel,
        .memory-index-space .stat,
        .memory-index-space .note-card {
            background: linear-gradient(180deg, rgba(10, 18, 34, 0.9), rgba(7, 13, 27, 0.92));
            border: 1px solid rgba(155, 198, 255, 0.12);
            box-shadow: 0 24px 60px rgba(2, 4, 12, 0.28);
            backdrop-filter: blur(14px);
        }

        .memory-index-space .eyebrow {
            background: linear-gradient(135deg, rgba(12, 21, 44, 0.92), rgba(27, 47, 88, 0.78));
            border: 1px solid rgba(166, 205, 255, 0.18);
            color: rgba(216, 234, 255, 0.92);
        }

        .memory-index-space h1,
        .memory-index-space .panel-header h2,
        .memory-index-space .panel-header h3,
        .memory-index-space .stat-value,
        .memory-index-space .memory-row-meta strong,
        .memory-index-space .memory-row-content {
            color: rgba(245, 249, 255, 0.96);
        }

        .memory-index-space .hero-copy,
        .memory-index-space .panel-subtitle,
        .memory-index-space .note-card p,
        .memory-index-space .stat-label,
        .memory-index-space .detail-label,
        .memory-index-space .memory-row-meta {
            color: rgba(188, 214, 255, 0.7);
        }

        .memory-index-space .btn-primary {
            background: linear-gradient(135deg, rgba(142, 204, 255, 0.28), rgba(87, 132, 255, 0.78));
            border-color: rgba(180, 218, 255, 0.24);
            color: rgba(245, 249, 255, 0.96);
            box-shadow: 0 14px 28px rgba(40, 82, 168, 0.26);
        }

        .memory-index-space .btn-secondary {
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            border-color: rgba(166, 204, 255, 0.16);
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.28);
        }

        .memory-index-space .btn:hover {
            border-color: rgba(196, 224, 255, 0.34);
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
            color: rgba(250, 252, 255, 0.98);
        }

        .memory-list-panel {
            display: grid;
            gap: 18px;
        }

        .memory-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .memory-toolbar-main {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .memory-search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 1 auto;
            min-width: 0;
        }

        .memory-search-form input {
            width: clamp(150px, 14vw, 190px);
            min-width: 0;
            padding: 8px 11px;
            border-radius: 12px;
            border: 1px solid rgba(171, 205, 255, 0.2);
            background: rgba(14, 22, 43, 0.88);
            color: rgba(239, 245, 255, 0.94);
            font-size: 13px;
            line-height: 1.2;
        }

        .memory-period-filter {
            display: flex;
            flex-wrap: nowrap;
            justify-content: flex-end;
            gap: 8px;
            flex: 0 1 auto;
            min-width: 0;
            overflow-x: auto;
            padding-bottom: 4px;
            margin-left: auto;
        }

        .memory-period-filter::-webkit-scrollbar {
            height: 6px;
        }

        .memory-period-filter::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 194, 255, 0.24);
        }

        .memory-period-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 10px;
            border-radius: 10px;
            border: 1px solid rgba(166, 204, 255, 0.14);
            background: linear-gradient(135deg, rgba(16, 26, 50, 0.9), rgba(9, 17, 34, 0.94));
            color: rgba(224, 237, 255, 0.78);
            font-size: 12px;
            line-height: 1;
            white-space: nowrap;
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.22);
            transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
        }

        .memory-period-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(196, 224, 255, 0.28);
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.3), rgba(34, 73, 171, 0.82));
            color: rgba(248, 251, 255, 0.96);
        }

        .memory-period-btn.is-active {
            border-color: rgba(196, 224, 255, 0.28);
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
            color: rgba(250, 252, 255, 0.98);
            box-shadow: 0 14px 28px rgba(18, 36, 78, 0.3);
        }

        .memory-toolbar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .memory-toolbar-actions form {
            margin: 0;
        }

        .memory-search-form .btn,
        .memory-toolbar-actions .btn,
        .memory-toolbar-actions button {
            min-height: 32px;
            padding: 8px 11px;
            font-size: 13px;
            line-height: 1;
        }

        .memory-toolbar-actions .is-disabled {
            pointer-events: none;
            opacity: 0.38;
        }

        .memory-list-scroll {
            max-height: min(72vh, 920px);
            overflow-y: auto;
            padding-right: 6px;
        }

        .memory-timeline {
            display: grid;
            gap: 12px;
        }

        .memory-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 14px;
            padding: 16px 18px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(12, 21, 41, 0.92), rgba(8, 14, 28, 0.94));
            border: 1px solid rgba(155, 198, 255, 0.12);
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .memory-row:hover {
            transform: translateY(-1px);
            border-color: rgba(174, 214, 255, 0.24);
            box-shadow: 0 18px 34px rgba(4, 10, 22, 0.3);
        }

        .memory-select {
            margin-top: 4px;
            accent-color: #7fb7ff;
        }

        .memory-row-body {
            display: grid;
            gap: 10px;
        }

        .memory-list-scroll::-webkit-scrollbar {
            width: 10px;
        }

        .memory-list-scroll::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 194, 255, 0.28);
        }

        .memory-list-scroll::-webkit-scrollbar-track {
            background: rgba(8, 14, 28, 0.42);
        }

        @media (max-width: 760px) {
            .memory-index-space {
                padding: 18px;
                border-radius: 24px;
            }

            .memory-toolbar {
                flex-wrap: wrap;
                align-items: stretch;
            }

            .memory-toolbar-main {
                width: 100%;
                flex-wrap: wrap;
            }

            .memory-search-form {
                width: 100%;
                flex-wrap: wrap;
                align-items: stretch;
            }

            .memory-search-form input {
                width: 100%;
            }

            .memory-toolbar-actions {
                width: 100%;
                align-items: stretch;
            }

            .memory-toolbar-actions .btn,
            .memory-toolbar-actions form {
                flex: 1 1 0;
            }

            .memory-toolbar-actions form button {
                width: 100%;
            }

            .memory-period-filter {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0;
            }

            .memory-row {
                grid-template-columns: 1fr;
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
