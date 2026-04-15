@extends('layouts.app')

@section('title', '記憶詳細 | 分身AI MVP')

@php
    $tone = $emotionToneMap[$memory->emotion] ?? 'ニュートラル';
    $badgeClass = str_contains($tone, 'ポジティブ') ? 'badge-positive' : (str_contains($tone, 'ニュートラル') ? 'badge-neutral' : 'badge-negative');
@endphp

@section('content')
    <section class="hero">
        <div class="hero-card">
            <span class="eyebrow">Memory Detail</span>
            <h1>記憶の詳細</h1>
            <p class="hero-copy">
                保存済みの記憶を確認できます。不要になった記憶はこの画面から削除できます。
            </p>
            <div class="hero-actions">
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧へ戻る</a>
            </div>
        </div>

        <aside class="side-card">
            <div class="note-card">
                <p>保存日時は `created_at` を表示しています。編集機能はMVPの対象外なので、ここでは削除のみ可能です。</p>
            </div>
        </aside>
    </section>

    <section class="layout">
        <main class="panel">
            <div class="panel-header">
                <div>
                    <h2>記憶詳細画面</h2>
                    <p class="panel-subtitle">保存済みの記憶内容と感情を確認します。</p>
                </div>
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">戻る</a>
            </div>

            <div class="detail-stack">
                <div class="detail-box">
                    <span class="detail-label">年代</span>
                    <strong>{{ $memory->period }}</strong>
                </div>

                <div class="detail-box">
                    <span class="detail-label">感情</span>
                    <span class="badge {{ $badgeClass }}">{{ $memory->emotion }}</span>
                </div>

                <div class="detail-box">
                    <span class="detail-label">内容</span>
                    <p class="detail-content">{{ $memory->content }}</p>
                </div>

                <div class="detail-box">
                    <span class="detail-label">保存日時</span>
                    <strong>{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d H:i') }}</strong>
                </div>
            </div>

            <div class="detail-actions" style="margin-top: 20px;">
                <form method="post" action="{{ route('memories.destroy', $memory) }}" onsubmit="return confirm('この記憶を削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-secondary btn-danger" type="submit">削除する</button>
                </form>
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧へ戻る</a>
            </div>
        </main>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h3>データ構造</h3>
                    <p class="panel-subtitle">表示内容は `memories` テーブルの内容です。</p>
                </div>
            </div>

            <div class="note-card">
                <p>カラムは `id`, `period`, `content`, `emotion`, `created_at`, `updated_at` です。詳細画面では `content` を全文表示します。</p>
            </div>
        </aside>
    </section>
@endsection
