@extends('layouts.app')

@section('title', '記憶入力 | 分身AI MVP')

@section('content')
    <section class="hero">
        <div class="hero-card">
            <span class="eyebrow">Memory Input</span>
            <h1>新しい記憶を登録</h1>
            <p class="hero-copy">
                年代、内容、感情を選ぶだけで記憶を保存できます。感情は仕様書の固定リストから単一選択です。
            </p>
            <div class="hero-actions">
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧へ戻る</a>
            </div>
        </div>

        <aside class="side-card">
            <div class="note-card">
                <p>入力内容は `memories` テーブルに保存されます。保存後は一覧画面へ戻ります。</p>
            </div>
        </aside>
    </section>

    <section class="layout">
        <main class="panel">
            <div class="panel-header">
                <div>
                    <h2>記憶入力画面</h2>
                    <p class="panel-subtitle">年代・内容・感情を入力して保存します。</p>
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

            <form method="post" action="{{ route('memories.store') }}" class="form-grid">
                @csrf

                <div class="field">
                    <label>年代</label>
                    <div class="chip-group">
                        @foreach ($periods as $period)
                            <label class="chip-option">
                                <input type="radio" name="period" value="{{ $period }}" {{ old('period', $periods[0]) === $period ? 'checked' : '' }}>
                                <span class="chip-button">{{ $period }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="field">
                    <label for="content">内容</label>
                    <textarea id="content" name="content" placeholder="その時の出来事や情景、気持ちを自由に入力してください。">{{ old('content') }}</textarea>
                </div>

                <div class="field">
                    <label>感情</label>
                    @foreach ($emotionGroups as $group => $emotions)
                        <div class="emotion-section">
                            <h4>{{ $group }}</h4>
                            <div class="chip-group">
                                @foreach ($emotions as $emotion)
                                    <label class="chip-option">
                                        <input type="radio" name="emotion" value="{{ $emotion }}" {{ old('emotion') === $emotion ? 'checked' : '' }}>
                                        <span class="chip-button">{{ $emotion }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">保存する</button>
                    <a class="btn btn-secondary" href="{{ route('memories.index') }}">キャンセル</a>
                </div>
            </form>
        </main>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h3>入力ガイド</h3>
                    <p class="panel-subtitle">MVPでは自由入力感情やAI分析は行いません。</p>
                </div>
            </div>

            <div class="note-card">
                <p>内容の文字数制限は設けていません。感情は固定選択のみで、仕様書どおりの選択肢に限定しています。</p>
            </div>
        </aside>
    </section>
@endsection
