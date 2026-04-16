@extends('layouts.app')

@section('title', '全記憶一覧 | 分身AI MVP')

@section('content')
    <section class="hero">
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
            <form method="get" action="{{ route('memories.index') }}" class="memory-search-form">
                <label for="q" class="detail-label">検索</label>
                <input id="q" type="search" name="q" value="{{ $searchQuery }}" placeholder="キーワードで検索">
                <button class="btn btn-secondary" type="submit">検索</button>
                @if ($searchQuery !== '')
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

    <style>
        .memory-list-panel {
            display: grid;
            gap: 18px;
        }

        .memory-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            justify-content: space-between;
            gap: 16px;
        }

        .memory-search-form {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 12px;
            flex: 1 1 520px;
        }

        .memory-search-form label {
            margin: 0;
        }

        .memory-search-form input {
            flex: 1 1 280px;
            min-width: 240px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.9);
        }

        .memory-toolbar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .memory-toolbar-actions form {
            margin: 0;
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
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(31, 36, 48, 0.08);
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .memory-row:hover {
            transform: translateY(-1px);
            border-color: rgba(31, 36, 48, 0.16);
            box-shadow: 0 18px 34px rgba(37, 32, 52, 0.08);
        }

        .memory-select {
            margin-top: 4px;
            accent-color: var(--ink);
        }

        .memory-row-body {
            display: grid;
            gap: 10px;
        }

        .memory-row-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            color: var(--subtle);
            font-size: 14px;
        }

        .memory-row-meta strong {
            color: var(--ink);
        }

        .memory-row-content {
            margin: 0;
            color: var(--ink);
            line-height: 1.8;
        }

        @media (max-width: 760px) {
            .memory-toolbar,
            .memory-search-form,
            .memory-toolbar-actions {
                align-items: stretch;
            }

            .memory-toolbar-actions {
                width: 100%;
            }

            .memory-toolbar-actions .btn,
            .memory-toolbar-actions form {
                flex: 1 1 0;
            }

            .memory-toolbar-actions form button {
                width: 100%;
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
