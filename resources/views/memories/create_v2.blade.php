@extends('layouts.app')

@section('title', '記憶を追加 | 分身AI MVP')
@section('body_class', 'body-memory-create-v2')
@section('page_class', 'page-memory-create-v2')
@section('hide_auth_dock', '1')

@php
    $eras = ['幼少期', '小学生', '中学生', '高校生', '大学生', '成人期', '不明'];

    $emotionGroups = [
        'positive' => [
            ['label' => '感動', 'tone' => 'positive'],
            ['label' => '嬉しい', 'tone' => 'positive'],
            ['label' => '楽しい', 'tone' => 'positive'],
            ['label' => '安心', 'tone' => 'positive'],
            ['label' => 'ホッとした', 'tone' => 'positive'],
            ['label' => '幸せ', 'tone' => 'positive'],
            ['label' => '満足', 'tone' => 'positive'],
            ['label' => 'ワクワク', 'tone' => 'positive'],
            ['label' => '感謝', 'tone' => 'positive'],
            ['label' => '誇らしい', 'tone' => 'positive'],
            ['label' => '自信がある', 'tone' => 'positive'],
        ],
        'neutral' => [
            ['label' => '普通', 'tone' => 'neutral'],
            ['label' => 'なんとなく', 'tone' => 'neutral'],
            ['label' => '落ち着いている', 'tone' => 'neutral'],
            ['label' => 'ぼーっとした', 'tone' => 'neutral'],
            ['label' => '考え中', 'tone' => 'neutral'],
        ],
        'negative-light' => [
            ['label' => 'モヤモヤ', 'tone' => 'negative'],
            ['label' => '少し不安', 'tone' => 'negative'],
            ['label' => '疲れた', 'tone' => 'negative'],
            ['label' => '迷い', 'tone' => 'negative'],
            ['label' => '気まずい', 'tone' => 'negative'],
            ['label' => '引っかかる', 'tone' => 'negative'],
        ],
        'negative-heavy' => [
            ['label' => '不安', 'tone' => 'negative'],
            ['label' => '悲しい', 'tone' => 'negative'],
            ['label' => 'イライラ', 'tone' => 'negative'],
            ['label' => '怒り', 'tone' => 'negative'],
            ['label' => '落ち込み', 'tone' => 'negative'],
            ['label' => '孤独', 'tone' => 'negative'],
            ['label' => '無力感', 'tone' => 'negative'],
            ['label' => '自信がない', 'tone' => 'negative'],
        ],
    ];

    $emotionOptions = array_merge(
        $emotionGroups['positive'],
        $emotionGroups['neutral'],
        $emotionGroups['negative-light'],
        $emotionGroups['negative-heavy'],
    );

    $emotionBuckets = [
        'good' => [
            'label' => '良い',
            'tone' => 'positive',
            'summary' => 'うれしい・前向きな気持ち',
            'items' => $emotionGroups['positive'],
            'layout' => [
                ['left' => 28, 'top' => 24, 'size' => 78, 'delay' => '0s'],
                ['left' => 37, 'top' => 10, 'size' => 74, 'delay' => '1.4s'],
                ['left' => 60, 'top' => 16, 'size' => 82, 'delay' => '2.6s'],
                ['left' => 77, 'top' => 30, 'size' => 72, 'delay' => '3.8s'],
                ['left' => 18, 'top' => 39, 'size' => 78, 'delay' => '1.1s'],
                ['left' => 40, 'top' => 34, 'size' => 96, 'delay' => '2s'],
                ['left' => 61, 'top' => 42, 'size' => 76, 'delay' => '4.3s'],
                ['left' => 79, 'top' => 55, 'size' => 86, 'delay' => '0.8s'],
                ['left' => 20, 'top' => 66, 'size' => 92, 'delay' => '2.9s'],
                ['left' => 47, 'top' => 61, 'size' => 78, 'delay' => '3.4s'],
                ['left' => 66, 'top' => 76, 'size' => 84, 'delay' => '1.7s'],
            ],
        ],
        'normal' => [
            'label' => '普通',
            'tone' => 'neutral',
            'summary' => '静かで落ち着いた気持ち',
            'items' => $emotionGroups['neutral'],
            'layout' => [
                ['left' => 28, 'top' => 24, 'size' => 92, 'delay' => '0s'],
                ['left' => 58, 'top' => 18, 'size' => 78, 'delay' => '1.5s'],
                ['left' => 74, 'top' => 43, 'size' => 88, 'delay' => '3.2s'],
                ['left' => 32, 'top' => 60, 'size' => 84, 'delay' => '2.1s'],
                ['left' => 56, 'top' => 72, 'size' => 96, 'delay' => '4.1s'],
            ],
        ],
        'bad' => [
            'label' => '悪い',
            'tone' => 'negative',
            'summary' => '揺れや重さを含む気持ち',
            'items' => array_merge($emotionGroups['negative-light'], $emotionGroups['negative-heavy']),
            'layout' => [
                ['left' => 28, 'top' => 25, 'size' => 74, 'delay' => '0s'],
                ['left' => 35, 'top' => 10, 'size' => 72, 'delay' => '1.2s'],
                ['left' => 56, 'top' => 14, 'size' => 80, 'delay' => '2.5s'],
                ['left' => 75, 'top' => 20, 'size' => 74, 'delay' => '3.3s'],
                ['left' => 20, 'top' => 35, 'size' => 82, 'delay' => '4.1s'],
                ['left' => 42, 'top' => 31, 'size' => 92, 'delay' => '1.9s'],
                ['left' => 68, 'top' => 39, 'size' => 76, 'delay' => '2.7s'],
                ['left' => 82, 'top' => 49, 'size' => 70, 'delay' => '0.9s'],
                ['left' => 16, 'top' => 56, 'size' => 84, 'delay' => '3.6s'],
                ['left' => 35, 'top' => 58, 'size' => 72, 'delay' => '1.4s'],
                ['left' => 55, 'top' => 61, 'size' => 88, 'delay' => '2.2s'],
                ['left' => 74, 'top' => 68, 'size' => 76, 'delay' => '4.4s'],
                ['left' => 26, 'top' => 77, 'size' => 80, 'delay' => '2.9s'],
                ['left' => 50, 'top' => 81, 'size' => 86, 'delay' => '3.8s'],
            ],
        ],
    ];

    $emotionToneMap = collect($emotionOptions)->mapWithKeys(fn ($emotion) => [
        $emotion['label'] => $emotion['tone'],
    ])->all();

    $emotionBucketMap = collect($emotionBuckets)->flatMap(
        fn ($bucket, $key) => collect($bucket['items'])->mapWithKeys(fn ($emotion) => [$emotion['label'] => $key])
    )->all();

    $initialEra = (string) old('period', '高校生');
    $initialContent = old('content', '');
    $initialEmotion = (string) old('emotion', '普通');
    $initialEmotionBucket = (string) old('emotion_group', $emotionBucketMap[$initialEmotion] ?? 'normal');

    if (!array_key_exists($initialEmotionBucket, $emotionBuckets)) {
        $initialEmotionBucket = 'normal';
    }

    if ($initialEmotion !== '' && !array_key_exists($initialEmotion, $emotionBucketMap)) {
        $emotionBuckets[$initialEmotionBucket]['items'][] = [
            'label' => $initialEmotion,
            'tone' => $emotionBuckets[$initialEmotionBucket]['tone'],
        ];
        $emotionBuckets[$initialEmotionBucket]['layout'][] = [
            'left' => 52,
            'top' => 52,
            'size' => 74,
            'delay' => '1.1s',
        ];
        $emotionBucketMap[$initialEmotion] = $initialEmotionBucket;
        $emotionToneMap[$initialEmotion] = $emotionBuckets[$initialEmotionBucket]['tone'];
    }

    $initialTone = $emotionToneMap[$initialEmotion] ?? 'neutral';

    $contentLength = mb_strlen(trim($initialContent));
    $filledLevel = $contentLength === 0
        ? 'soft'
        : ($contentLength < 30 ? 'soft' : ($contentLength < 90 ? 'medium' : 'dense'));
@endphp

@section('content')
    <section
        class="mcv2-frame"
        data-memory-shot
        data-initial-tone="{{ $initialTone }}"
        data-initial-filled="{{ $filledLevel }}"
    >
        <div class="mcv2-stars" aria-hidden="true">
            <span class="mcv2-star s1"></span>
            <span class="mcv2-star s2"></span>
            <span class="mcv2-star s3 cross"></span>
            <span class="mcv2-star s4"></span>
            <span class="mcv2-star s5"></span>
            <span class="mcv2-star s6 cross"></span>
            <span class="mcv2-star s7"></span>
            <span class="mcv2-star s8"></span>
            <span class="mcv2-star s9 cross"></span>
            <span class="mcv2-star s10"></span>
        </div>

        <span class="mcv2-orb orb-left" aria-hidden="true"></span>
        <span class="mcv2-orb orb-right-top" aria-hidden="true"></span>
        <span class="mcv2-orb orb-right-mid" aria-hidden="true"></span>
        <span class="mcv2-orb orb-right-bottom" aria-hidden="true"></span>

        <form
            class="mcv2-shell tone-{{ $initialTone }} preview-{{ $filledLevel }}"
            method="post"
            action="{{ route('memories.store') }}"
            data-memory-form
        >
            @csrf
            <input type="hidden" name="emotion_group" value="{{ $initialEmotionBucket }}" data-emotion-group-input>

            <span class="mcv2-divider left" aria-hidden="true"></span>
            <span class="mcv2-divider mid" aria-hidden="true"></span>

            <aside class="mcv2-left">
                <div class="mcv2-left-glow" aria-hidden="true">
                    <span class="mcv2-left-dot d1"></span>
                    <span class="mcv2-left-dot d2"></span>
                    <span class="mcv2-left-dot d3"></span>
                    <span class="mcv2-left-dot d4"></span>
                    <span class="mcv2-left-spark s1"></span>
                    <span class="mcv2-left-spark s2"></span>
                    <span class="mcv2-left-orb"></span>
                </div>

                <div class="mcv2-eyebrow">MEMORY INPUT</div>

                <h1 class="mcv2-hero">
                    記憶を追加<br>
                    する
                </h1>

                <p class="mcv2-copy">
                    いつ、どのような感情だったか、<br>
                    振り返りながら記録しましょう。
                </p>

                <section class="mcv2-guide" aria-label="使い方">
                    <div class="mcv2-guide-eyebrow">QUICK GUIDE</div>
                    <h3 class="mcv2-guide-title">入力はこの3ステップで完了します</h3>
                    <div class="mcv2-guide-list">
                        <div class="mcv2-guide-item">
                            <span class="mcv2-guide-num">1</span>
                            <p>年代をひとつ選んで、いつ頃の記憶かを決めます。</p>
                        </div>
                        <div class="mcv2-guide-item">
                            <span class="mcv2-guide-num">2</span>
                            <p>場所や会話、その時に残った情景を短く書きます。</p>
                        </div>
                        <div class="mcv2-guide-item">
                            <span class="mcv2-guide-num">3</span>
                            <p>いちばん近い気持ちを選んだら、そのまま保存できます。</p>
                        </div>
                    </div>
                </section>

                <div class="mcv2-left-footer">
                    <a class="mcv2-ghost-btn" href="{{ route('memories.index') }}">一覧へ戻る</a>
                </div>
            </aside>

            <main class="mcv2-center">
                <section class="mcv2-card">
                    <div class="mcv2-progress" data-progress>
                        <div class="mcv2-progress-rail" aria-hidden="true">
                            <span class="mcv2-progress-fill" data-progress-fill></span>
                        </div>
                        <div class="mcv2-progress-steps">
                            <div class="mcv2-progress-step" data-progress-step="period">
                                <span class="mcv2-progress-dot">1</span>
                                <span class="mcv2-progress-label">年代を選ぶ</span>
                            </div>
                            <div class="mcv2-progress-step" data-progress-step="content">
                                <span class="mcv2-progress-dot">2</span>
                                <span class="mcv2-progress-label">内容を入力</span>
                            </div>
                            <div class="mcv2-progress-step" data-progress-step="emotion">
                                <span class="mcv2-progress-dot">3</span>
                                <span class="mcv2-progress-label">感情を選択</span>
                            </div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="mcv2-errors">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <section class="mcv2-section">
                        <div class="mcv2-section-head">
                            <h2>1. いつ頃の出来事だった？</h2>
                            <button class="mcv2-inline-reset" type="button" data-reset-section="period">リセット</button>
                        </div>

                        <div class="mcv2-select-wrap">
                            <select class="mcv2-select" name="period" data-period-select>
                                <option value="" {{ $initialEra === '' ? 'selected' : '' }}>年代を選択</option>
                                @foreach ($eras as $era)
                                    <option value="{{ $era }}" {{ $initialEra === $era ? 'selected' : '' }}>
                                        {{ $era === '大学生' ? '大学学生' : $era }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="mcv2-select-chevron" aria-hidden="true"></span>
                        </div>
                    </section>

                    <section class="mcv2-section mcv2-section-content">
                        <div class="mcv2-section-head">
                            <h2>2. どんな内容だった？</h2>
                            <button class="mcv2-inline-reset" type="button" data-reset-section="content">リセット</button>
                        </div>

                        <div class="mcv2-textarea-shell">
                            <span class="mcv2-textarea-sheen" aria-hidden="true"></span>
                            <textarea
                                class="mcv2-textarea"
                                name="content"
                                id="mcv2-content"
                                data-content-input
                                placeholder="思い出の場所、会話、感じたことなどを入力してください..."
                            >{{ $initialContent }}</textarea>
                        </div>
                    </section>

                    <section class="mcv2-section">
                        <div class="mcv2-section-head">
                            <h2>3. その時の最も近い気持ちを1つ選ぶなら、どれ？</h2>
                            <button class="mcv2-inline-reset" type="button" data-reset-section="emotion">リセット</button>
                        </div>

                        <div class="mcv2-emotion-picker">
                            @foreach ($emotionBuckets as $bucketKey => $bucket)
                                <button
                                    class="mcv2-emotion-trigger tone-{{ $bucket['tone'] }} {{ $initialEmotionBucket === $bucketKey ? 'is-active' : '' }}"
                                    type="button"
                                    data-emotion-open="{{ $bucketKey }}"
                                    data-group-label="{{ $bucket['label'] }}"
                                    aria-haspopup="dialog"
                                    aria-pressed="{{ $initialEmotionBucket === $bucketKey ? 'true' : 'false' }}"
                                >
                                    <span class="mcv2-emotion-trigger-label">{{ $bucket['label'] }}</span>
                                    <span class="mcv2-emotion-trigger-copy">{{ $bucket['summary'] }}</span>
                                    <span class="mcv2-emotion-trigger-selection" data-group-selected="{{ $bucketKey }}">
                                        {{ $initialEmotionBucket === $bucketKey ? $initialEmotion : 'タップして選ぶ' }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <div class="mcv2-emotion-current" aria-live="polite">
                            <span class="mcv2-emotion-current-label">選択中</span>
                            <strong class="mcv2-emotion-current-value" data-current-emotion>{{ $initialEmotion }}</strong>
                        </div>
                    </section>
                </section>
            </main>

            <aside class="mcv2-right">
                <div class="mcv2-preview">
                    <div class="mcv2-right-glow" aria-hidden="true"></div>
                    <span class="mcv2-bottom-orb" aria-hidden="true"></span>

                    <div class="mcv2-preview-meta">
                        <div>年代: <strong data-preview-period>{{ $initialEra }}</strong></div>
                        <div>感情: <strong data-preview-emotion>{{ $initialEmotion }}</strong></div>
                        <div>状態: <strong data-preview-state>
                            {{ trim($initialContent) === '' ? '軽く入力中' : ($contentLength < 30 ? '輪郭が見えはじめた' : ($contentLength < 90 ? '記憶が形になっている' : '濃く保存されそう')) }}
                        </strong></div>
                    </div>
                </div>

                <div class="mcv2-actions">
                    <button class="mcv2-primary" type="submit">記憶を保存する</button>
                    <button class="mcv2-secondary" type="button" data-cancel-button>キャンセル</button>
                </div>

                <div class="mcv2-emotion-modal" data-emotion-modal aria-hidden="true" hidden>
                    <div class="mcv2-emotion-modal-backdrop" data-emotion-close></div>

                    <div class="mcv2-emotion-dialog" role="dialog" aria-modal="true" aria-label="感情を選ぶ">
                        <button class="mcv2-emotion-close" type="button" data-emotion-close aria-label="閉じる">×</button>

                        @foreach ($emotionBuckets as $bucketKey => $bucket)
                            <section
                                class="mcv2-emotion-panel tone-{{ $bucket['tone'] }}"
                                data-emotion-panel="{{ $bucketKey }}"
                                {{ $initialEmotionBucket === $bucketKey ? '' : 'hidden' }}
                            >
                                <div class="mcv2-emotion-panel-head">
                                    <h3 class="mcv2-emotion-panel-title">
                                        1つ選んでください
                                    </h3>
                                </div>

                                <div class="mcv2-emotion-sphere">
                                    <span class="mcv2-emotion-sphere-ring ring-a" aria-hidden="true"></span>
                                    <span class="mcv2-emotion-sphere-ring ring-b" aria-hidden="true"></span>

                                    @foreach ($bucket['items'] as $index => $emotion)
                                        @php
                                            $layout = $bucket['layout'][$index];
                                        @endphp
                                        <label
                                            class="mcv2-emotion-orb tone-{{ $bucket['tone'] }}"
                                            style="--orb-left: {{ $layout['left'] }}%; --orb-top: {{ $layout['top'] }}%; --orb-size: {{ $layout['size'] }}px; --orb-delay: {{ $layout['delay'] }};"
                                        >
                                            <input
                                                type="radio"
                                                name="emotion"
                                                value="{{ $emotion['label'] }}"
                                                data-tone="{{ $emotion['tone'] }}"
                                                data-group="{{ $bucketKey }}"
                                                {{ $initialEmotion === $emotion['label'] ? 'checked' : '' }}
                                            >
                                            <span>{{ $emotion['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="mcv2-emotion-panel-actions">
                                    <button
                                        class="mcv2-emotion-add-trigger"
                                        type="button"
                                        data-add-emotion-open="{{ $bucketKey }}"
                                        data-add-emotion-tone="{{ $bucket['tone'] }}"
                                    >
                                        感情を追加
                                    </button>
                                </div>
                            </section>
                        @endforeach

                        <div class="mcv2-add-emotion-modal" data-add-emotion-modal hidden>
                            <div class="mcv2-add-emotion-backdrop" data-add-emotion-close></div>
                            <div class="mcv2-add-emotion-dialog" role="dialog" aria-modal="true" aria-label="感情を追加">
                                <button class="mcv2-add-emotion-close" type="button" data-add-emotion-close aria-label="閉じる">×</button>
                                <div class="mcv2-add-emotion-head">
                                    <h4>感情を追加</h4>
                                    <p>新しい気持ちの名前を入力してください。</p>
                                </div>
                                <label class="mcv2-add-emotion-field">
                                    <span>感情の名前</span>
                                    <input type="text" maxlength="20" data-add-emotion-input placeholder="例：じんわり嬉しい">
                                </label>
                                <button class="mcv2-add-emotion-submit" type="button" data-add-emotion-submit>追加</button>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </form>

        <div class="mcv2-toast" data-toast></div>
    </section>
@endsection

@push('styles')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/css/pages/memories-create-v2.css')
    @else
        @php
            $cssPath = public_path('css/pages/memories-create-v2.css');
            $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
        @endphp
        <link rel="stylesheet" href="{{ asset('css/pages/memories-create-v2.css') }}?v={{ $cssVersion }}">
    @endif
@endpush

@push('scripts')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/js/pages/memories-create-v2.js')
    @else
        @php
            $jsPath = public_path('js/pages/memories-create-v2.js');
            $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
        @endphp
        <script src="{{ asset('js/pages/memories-create-v2.js') }}?v={{ $jsVersion }}" defer></script>
    @endif
@endpush
