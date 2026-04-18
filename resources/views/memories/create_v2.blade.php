@extends('layouts.app')

@section('title', '記憶を追加 Preview | 分身AI MVP')
@section('body_class', 'body-memory-create-v2')
@section('page_class', 'page-memory-create-v2')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/pages/memories-create-v2.css') }}">
@endpush

@section('content')
    <div class="memory-create-v2-space">
        <section
            class="memory-create-v2 theme-{{ $createComposerInitialState['group'] }}"
            data-memory-create-v2
            data-filled-level="{{ $createComposerInitialState['filledLevel'] }}"
        >
            <script type="application/json" class="memory-create-v2__config">
                @json([
                    'groupMeta' => $createComposerGroupMeta,
                    'emotionToGroup' => $createComposerEmotionToGroup,
                    'initialGroup' => $createComposerInitialState['group'],
                    'hasErrors' => $errors->any(),
                    'storageKey' => 'memory-create-v2-draft',
                ])
            </script>

            <div class="memory-create-v2__ambient" aria-hidden="true">
                <span class="memory-create-v2__star star-1"></span>
                <span class="memory-create-v2__star star-2"></span>
                <span class="memory-create-v2__star star-3"></span>
                <span class="memory-create-v2__star star-4"></span>
                <span class="memory-create-v2__orb orb-1"></span>
                <span class="memory-create-v2__orb orb-2"></span>
                <span class="memory-create-v2__orb orb-3"></span>
            </div>

            <form method="post" action="{{ route('memories.store') }}" class="memory-create-v2__shell">
                @csrf

                <aside class="memory-create-v2__intro">
                    <span class="memory-create-v2__eyebrow">MEMORY INPUT</span>
                    <h1 class="memory-create-v2__title">新しい記憶を残す</h1>
                    <p class="memory-create-v2__description">
                        いつのことだったか、どんな出来事だったか、どんな気持ちが残っているか。
                        記憶の輪郭に光を与えながら、一覧画面と同じ世界の中で保存できる新UIです。
                    </p>
                    <div class="memory-create-v2__intro-actions">
                        <a class="memory-create-v2__ghost-button" href="{{ route('memories.index') }}">一覧へ戻る</a>
                        <span class="memory-create-v2__preview-note">Preview route</span>
                    </div>
                </aside>

                <main class="memory-create-v2__composer">
                    <section class="memory-create-v2__panel">
                        <div class="memory-create-v2__step-header" aria-hidden="true">
                            <div class="memory-create-v2__step-item is-done">
                                <span class="memory-create-v2__step-dot"></span>
                                <span>1 年代</span>
                            </div>
                            <div class="memory-create-v2__step-line"></div>
                            <div class="memory-create-v2__step-item is-active">
                                <span class="memory-create-v2__step-dot"></span>
                                <span>2 内容</span>
                            </div>
                            <div class="memory-create-v2__step-line"></div>
                            <div class="memory-create-v2__step-item">
                                <span class="memory-create-v2__step-dot"></span>
                                <span>3 感情</span>
                            </div>
                        </div>

                        @if ($errors->any())
                            <div class="memory-create-v2__errors">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <section class="memory-create-v2__section">
                            <header class="memory-create-v2__section-heading">
                                <h2>いつ頃の記憶ですか</h2>
                                <p>年代を選ぶと、右側の記憶バブルにも静かに反映されます。</p>
                            </header>

                            <div class="memory-create-v2__era-grid">
                                @foreach ($eras as $era)
                                    <label class="memory-create-v2__era-chip">
                                        <input
                                            class="memory-create-v2__visually-hidden"
                                            type="radio"
                                            name="period"
                                            value="{{ $era }}"
                                            {{ $createComposerInitialState['period'] === $era ? 'checked' : '' }}
                                        >
                                        <span>{{ $era }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </section>

                        <section class="memory-create-v2__section">
                            <header class="memory-create-v2__section-heading">
                                <h2>どんな記憶ですか</h2>
                                <p>出来事、景色、音、空気、そのときの気持ち。思い出せることを自由に書いてください。</p>
                            </header>

                            <div class="memory-create-v2__textarea-wrap">
                                <textarea
                                    id="content"
                                    name="content"
                                    class="memory-create-v2__textarea"
                                    data-content-input
                                    placeholder="たとえば：&#10;・放課後、校舎の窓から見えた空&#10;・帰り道で友達と話したこと&#10;・なぜか今でも覚えている匂い"
                                >{{ $createComposerInitialState['content'] }}</textarea>

                                <div class="memory-create-v2__textarea-meta">
                                    <span data-char-count>{{ $createComposerInitialState['contentLength'] }} 文字</span>
                                    <span>記憶の輪郭をゆっくり残してください</span>
                                </div>
                            </div>
                        </section>

                        <section class="memory-create-v2__section">
                            <header class="memory-create-v2__section-heading">
                                <h2>この記憶に残っている気持ち</h2>
                                <p>感情カテゴリを切り替えながら、いちばん近いバブルをひとつ選んでください。</p>
                            </header>

                            <div class="memory-create-v2__group-row" role="tablist" aria-label="感情グループ">
                                @foreach ($createComposerGroupMeta as $groupKey => $meta)
                                    <button
                                        type="button"
                                        class="memory-create-v2__group-chip {{ $createComposerInitialState['group'] === $groupKey ? 'is-selected' : '' }}"
                                        data-group-button="{{ $groupKey }}"
                                    >
                                        {{ $meta['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            @foreach ($createComposerEmotionOptions as $groupKey => $groupEmotions)
                                <div
                                    class="memory-create-v2__bubble-field {{ $createComposerInitialState['group'] === $groupKey ? 'is-active' : '' }}"
                                    data-group-field="{{ $groupKey }}"
                                >
                                    @foreach ($groupEmotions as $index => $emotion)
                                        <label class="memory-create-v2__emotion-bubble {{ $createComposerBubbleSizeClasses[$index % count($createComposerBubbleSizeClasses)] }}">
                                            <input
                                                class="memory-create-v2__visually-hidden"
                                                type="radio"
                                                name="emotion"
                                                value="{{ $emotion }}"
                                                {{ $createComposerInitialState['emotion'] === $emotion ? 'checked' : '' }}
                                            >
                                            <span>{{ $emotion }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </section>
                    </section>
                </main>

                <aside class="memory-create-v2__preview">
                    <section class="memory-create-v2__panel memory-create-v2__preview-panel">
                        <header class="memory-create-v2__preview-heading">
                            <span class="memory-create-v2__preview-label">生成中の記憶</span>
                            <p data-preview-tone>{{ $createComposerGroupMeta[$createComposerInitialState['group']]['tone'] }}</p>
                        </header>

                        <div
                            class="memory-create-v2__preview-bubble is-{{ $createComposerInitialState['filledLevel'] }}"
                            data-preview-bubble
                        >
                            <div class="memory-create-v2__preview-core"></div>
                            <div class="memory-create-v2__preview-ring ring-1"></div>
                            <div class="memory-create-v2__preview-ring ring-2"></div>
                            <div class="memory-create-v2__preview-content">
                                <small data-preview-period>{{ $createComposerInitialState['period'] }}</small>
                                <strong data-preview-emotion>{{ $createComposerInitialState['emotion'] }}</strong>
                                <span data-preview-label>{{ $createComposerGroupMeta[$createComposerInitialState['group']]['previewLabel'] }}</span>
                            </div>
                        </div>

                        <div class="memory-create-v2__summary-card">
                            <div class="memory-create-v2__summary-row">
                                <span>年代</span>
                                <strong data-summary-period>{{ $createComposerInitialState['period'] }}</strong>
                            </div>
                            <div class="memory-create-v2__summary-row">
                                <span>感情</span>
                                <strong data-summary-emotion>{{ $createComposerInitialState['emotion'] }}</strong>
                            </div>
                            <div class="memory-create-v2__summary-row">
                                <span>状態</span>
                                <strong data-summary-state>{{ trim($createComposerInitialState['content']) !== '' ? '入力中' : '保存前' }}</strong>
                            </div>
                        </div>

                        <div class="memory-create-v2__preview-actions">
                            <button class="memory-create-v2__secondary-button" type="button" data-draft-save>下書きにする</button>
                            <button class="memory-create-v2__primary-button" type="submit">この記憶を保存</button>
                        </div>

                        <p class="memory-create-v2__draft-status" data-draft-status aria-live="polite"></p>
                    </section>
                </aside>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/memories-create-v2.js') }}" defer></script>
@endpush
