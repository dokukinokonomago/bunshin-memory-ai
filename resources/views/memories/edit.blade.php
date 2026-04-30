@extends('layouts.app')

@section('title', '記憶修正 | 分身AI MVP')

@section('content')
    <section class="hero">
        <div class="hero-card">
            <span class="eyebrow">Memory Edit</span>
            <h1>記憶を修正</h1>
            <p class="hero-copy">
                保存済みの記憶を更新します。年代、内容、感情を見直して保存してください。
            </p>
            <div class="hero-actions">
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧へ戻る</a>
            </div>
        </div>

        <aside class="side-card">
            <div class="note-card">
                <p>更新後は一覧画面へ戻ります。削除は一覧画面から行えます。</p>
            </div>
        </aside>
    </section>

    <section class="layout">
        <main class="panel">
            <div class="panel-header">
                <div>
                    <h2>記憶修正画面</h2>
                    <p class="panel-subtitle">保存済みの内容を更新します。</p>
                </div>
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">戻る</a>
            </div>

            @if ($errors->any())
                <div class="error-list">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('memories.update', $memory) }}" class="form-grid">
                @csrf
                @method('PUT')

                <div class="field">
                    <label>年代</label>
                    <div class="chip-group">
                        @foreach ($periods as $period)
                            <label class="chip-option">
                                <input type="radio" name="period" value="{{ $period }}" {{ old('period', $memory->period) === $period ? 'checked' : '' }}>
                                <span class="chip-button">{{ $period }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="field">
                    <label for="content">内容</label>
                    <textarea id="content" name="content" placeholder="その時の出来事や情景、気持ちを自由に入力してください。">{{ old('content', $memory->content) }}</textarea>
                </div>

                <div class="field">
                    <label for="tags">関連タグ</label>
                    <input
                        id="tags"
                        type="text"
                        name="tags"
                        value="{{ old('tags', implode(', ', $memory->tags ?? [])) }}"
                        placeholder="例：家族, 夏祭り, 部活"
                    >
                </div>

                <div class="field">
                    <label>感情</label>
                    @foreach ($emotionGroups as $group => $emotions)
                        <div class="emotion-section">
                            <h4>{{ $group }}</h4>
                            <div class="chip-group">
                                @foreach ($emotions as $emotion)
                                    <label class="chip-option">
                                        <input type="radio" name="emotion" value="{{ $emotion }}" {{ old('emotion', $memory->emotion) === $emotion ? 'checked' : '' }}>
                                        <span class="chip-button">{{ $emotion }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">更新する</button>
                    <a class="btn btn-secondary" href="{{ route('memories.index') }}">キャンセル</a>
                </div>
            </form>
        </main>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h3>編集ガイド</h3>
                    <p class="panel-subtitle">項目構成は新規作成と同じです。</p>
                </div>
            </div>

            <div class="note-card">
                <p>年代、内容、感情を更新できます。MVPでは履歴管理までは行いません。</p>
            </div>
        </aside>
    </section>
@endsection
