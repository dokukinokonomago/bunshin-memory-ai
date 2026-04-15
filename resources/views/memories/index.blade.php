@extends('layouts.app')

@section('title', '記憶一覧 | 分身AI MVP')

@php
    $allowedPeriods = array_merge(['すべて'], $periods);
@endphp

@section('content')
    <section class="hero">
        <div class="hero-card">
            <span class="eyebrow">Laravel + MySQL + Docker</span>
            <h1>分身AI 記憶ストック</h1>
            <p class="hero-copy">
                年代・内容・感情の3要素で記憶を管理するMVPです。Laravelで一覧、登録、詳細、削除を実装し、
                MySQLとApacheを含めてDocker内で完結させています。
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="{{ route('memories.create') }}">新しい記憶を追加</a>
                <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶の玉を見る</a>
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧を更新</a>
            </div>
        </div>

        <aside class="side-card">
            <div class="stat-grid">
                <div class="stat">
                    <span class="stat-label">total memories</span>
                    <div class="stat-value">{{ $allCount }}</div>
                </div>
                <div class="stat">
                    <span class="stat-label">filter</span>
                    <div class="stat-value">{{ $selectedPeriod }}</div>
                </div>
            </div>

            <div>
                <div class="panel-header" style="margin-bottom: 12px;">
                    <div>
                        <h3>最近の記憶</h3>
                        <p class="panel-subtitle">保存順に直近3件を表示します。</p>
                    </div>
                </div>

                <div class="recent-list">
                    @forelse ($recentMemories as $memory)
                        <a class="recent-item" href="{{ route('memories.show', $memory) }}">
                            <strong>{{ $memory->period }} / {{ $memory->emotion }}</strong>
                            <div>{{ \Illuminate\Support\Str::limit($memory->content, 36) }}</div>
                        </a>
                    @empty
                        <div class="recent-item">まだ記憶がありません。</div>
                    @endforelse
                </div>
            </div>
        </aside>
    </section>

    <section class="layout">
        <main class="panel">
            @if (session('status') === 'created')
                <div class="flash">記憶を保存しました。</div>
            @endif

            @if (session('status') === 'deleted')
                <div class="flash">記憶を削除しました。</div>
            @endif

            <div class="panel-header">
                <div>
                    <h2>記憶一覧画面</h2>
                    <p class="panel-subtitle">年代で絞り込みながら、保存済みの記憶をカード形式で確認できます。</p>
                </div>
                <a class="btn btn-primary" href="{{ route('memories.create') }}">新規追加</a>
            </div>

            <div class="chip-row" style="margin-bottom: 18px;">
                @foreach ($allowedPeriods as $period)
                    <a class="chip-link {{ $selectedPeriod === $period ? 'active' : '' }}" href="{{ route('memories.index', ['period' => $period === 'すべて' ? null : $period]) }}">
                        {{ $period }}
                    </a>
                @endforeach
            </div>

            @if ($memories->isEmpty())
                <div class="empty-state">
                    まだ記憶がありません。<br>
                    「新しい記憶を追加」から最初の1件を登録してください。
                </div>
            @else
                <div class="memory-grid">
                    @foreach ($memories as $memory)
                        @php
                            $tone = $emotionToneMap[$memory->emotion] ?? 'ニュートラル';
                            $badgeClass = str_contains($tone, 'ポジティブ') ? 'badge-positive' : (str_contains($tone, 'ニュートラル') ? 'badge-neutral' : 'badge-negative');
                        @endphp
                        <a class="memory-card" href="{{ route('memories.show', $memory) }}">
                            <div class="memory-meta">
                                <strong>{{ $memory->period }}</strong>
                                <span>{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d H:i') }}</span>
                            </div>
                            <p class="memory-content">{{ \Illuminate\Support\Str::limit($memory->content, 72) }}</p>
                            <div class="memory-footer">
                                <span class="badge {{ $badgeClass }}">{{ $memory->emotion }}</span>
                                <span>詳細を見る</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </main>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h3>MVP構成</h3>
                    <p class="panel-subtitle">仕様書に対して、この Laravel 構成で担保している範囲です。</p>
                </div>
            </div>

            <div class="note-card">
                <p>
                    保存先はMySQLです。コンテナ起動時に migration を自動実行し、記憶登録、一覧、詳細、
                    年代フィルター、削除の5要件を満たします。
                </p>
            </div>

            <div class="note-card" style="margin-top: 14px;">
                <p>
                    Web は <strong>28080</strong> 番、MySQL は <strong>13306</strong> 番へ変更しています。
                    既存ポートとの衝突を避ける前提です。
                </p>
            </div>
        </aside>
    </section>
@endsection
