@extends('layouts.app')

@section('title', '記憶を追加 | 分身AI MVP')
@section('body_class', 'body-memory-create')
@section('page_class', 'page-memory-create')

@php
    $eras = ['幼少期', '小学生', '中学生', '高校生', '大学生', '成人期', '不明'];
    $groupMeta = [
        'warm' => [
            'label' => 'あたたかい',
            'previewLabel' => 'やわらかな光',
            'tone' => 'やさしく、あたたかく残る記憶',
        ],
        'calm' => [
            'label' => '静かな',
            'previewLabel' => '静かな余韻',
            'tone' => '落ち着いた空気をまとった記憶',
        ],
        'sway' => [
            'label' => '揺れている',
            'previewLabel' => 'ゆらぐ光',
            'tone' => '気持ちが少し揺れている記憶',
        ],
        'heavy' => [
            'label' => '重たい',
            'previewLabel' => '深い残響',
            'tone' => '重く深く沈むような記憶',
        ],
    ];
    $emotionOptions = [
        'warm' => ['嬉しい', '楽しい', 'ホッとした', '幸せ', '満足', '感動', '誇らしい'],
        'calm' => ['普通', 'なんとなく', '落ち着いている', 'ぼーっとした', '考え中'],
        'sway' => ['モヤモヤ', '少し不安', '疲れた', '迷い', '気まずい', '引っかかる'],
        'heavy' => ['悲しい', '不安', '落ち込み', '孤独', '無力感', '自信がない', '怒り'],
    ];
    $bubbleSizeClass = ['lg', 'md', 'sm', 'md', 'lg', 'sm', 'md'];
    $emotionToGroup = [];

    foreach ($emotionOptions as $groupKey => $groupEmotions) {
        foreach ($groupEmotions as $emotion) {
            $emotionToGroup[$emotion] = $groupKey;
        }
    }

    $initialEra = old('period', '中学生');
    $initialContent = old('content', '');
    $initialEmotion = old('emotion', 'ホッとした');
    $initialGroup = $emotionToGroup[$initialEmotion] ?? 'warm';
    $contentLength = mb_strlen(trim($initialContent));
    $filledLevel = $contentLength === 0 ? 'empty' : ($contentLength < 60 ? 'soft' : ($contentLength < 140 ? 'medium' : 'dense'));
@endphp

@section('content')
    <section
        class="memory-create-page theme-{{ $initialGroup }}"
        data-memory-create
        data-initial-group="{{ $initialGroup }}"
        data-filled-level="{{ $filledLevel }}"
    >
        <div class="memory-ambient" aria-hidden="true">
            <span class="star s1"></span>
            <span class="star s2"></span>
            <span class="star s3"></span>
            <span class="star s4"></span>
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
        </div>

        <form method="post" action="{{ route('memories.store') }}" class="memory-shell">
            @csrf

            <aside class="memory-left">
                <div class="memory-eyebrow">MEMORY INPUT</div>
                <h1 class="memory-title">新しい記憶を残す</h1>
                <p class="memory-description">
                    いつのことだったか、どんな出来事だったか、どんな気持ちが残っているか。
                    記憶にそっと色をつけて保存します。
                </p>
                <a class="ghost-button" href="{{ route('memories.index') }}">一覧へ戻る</a>
            </aside>

            <main class="memory-center">
                <section class="composer-card">
                    <div class="step-header" aria-hidden="true">
                        <div class="step-item is-done">
                            <span class="step-dot"></span>
                            <span>1 年代</span>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-item is-active">
                            <span class="step-dot"></span>
                            <span>2 内容</span>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-item">
                            <span class="step-dot"></span>
                            <span>3 感情</span>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="memory-error-list">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="composer-section">
                        <div class="section-heading-wrap">
                            <h2>いつ頃の記憶ですか</h2>
                            <p>ひとつ選ぶと、記憶バブルの光が少し変わります</p>
                        </div>

                        <div class="era-grid">
                            @foreach ($eras as $era)
                                <label class="era-chip">
                                    <input
                                        class="visually-hidden"
                                        type="radio"
                                        name="period"
                                        value="{{ $era }}"
                                        {{ $initialEra === $era ? 'checked' : '' }}
                                    >
                                    <span>{{ $era }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="composer-section">
                        <div class="section-heading-wrap">
                            <h2>どんな記憶ですか</h2>
                            <p>出来事、景色、音、空気、そのときの気持ち。思い出せることを自由に。</p>
                        </div>

                        <div class="memory-textarea-wrap">
                            <textarea
                                id="content"
                                name="content"
                                class="memory-textarea"
                                data-content-input
                                placeholder="たとえば：&#10;・放課後、校舎の窓から見えた空&#10;・帰り道で友達と話したこと&#10;・なぜか今でも覚えている匂い"
                            >{{ $initialContent }}</textarea>
                            <div class="textarea-meta">
                                <span data-char-count>{{ $contentLength }} 文字</span>
                                <span>記憶の輪郭をゆっくり残してください</span>
                            </div>
                        </div>
                    </div>

                    <div class="composer-section">
                        <div class="section-heading-wrap">
                            <h2>この記憶に残っている気持ち</h2>
                            <p>近いものをひとつ選んでください</p>
                        </div>

                        <div class="emotion-group-row" role="tablist" aria-label="感情グループ">
                            @foreach ($groupMeta as $groupKey => $meta)
                                <button
                                    type="button"
                                    class="emotion-group-chip {{ $initialGroup === $groupKey ? 'is-selected' : '' }}"
                                    data-group-button="{{ $groupKey }}"
                                >
                                    {{ $meta['label'] }}
                                </button>
                            @endforeach
                        </div>

                        @foreach ($emotionOptions as $groupKey => $groupEmotions)
                            <div
                                class="emotion-bubble-field {{ $initialGroup === $groupKey ? 'is-active' : '' }}"
                                data-group-field="{{ $groupKey }}"
                            >
                                @foreach ($groupEmotions as $index => $emotion)
                                    <label class="emotion-bubble {{ $bubbleSizeClass[$index % count($bubbleSizeClass)] }}">
                                        <input
                                            class="visually-hidden"
                                            type="radio"
                                            name="emotion"
                                            value="{{ $emotion }}"
                                            {{ $initialEmotion === $emotion ? 'checked' : '' }}
                                        >
                                        <span>{{ $emotion }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section>
            </main>

            <aside class="memory-right">
                <div class="preview-panel">
                    <div class="preview-heading">
                        <span class="preview-label">生成中の記憶</span>
                        <p data-preview-tone>{{ $groupMeta[$initialGroup]['tone'] }}</p>
                    </div>

                    <div class="preview-bubble preview-{{ $filledLevel }}" data-preview-bubble>
                        <div class="preview-bubble-core"></div>
                        <div class="preview-bubble-ring ring-1"></div>
                        <div class="preview-bubble-ring ring-2"></div>
                        <div class="preview-content">
                            <small data-preview-era>{{ $initialEra }}</small>
                            <strong data-preview-emotion>{{ $initialEmotion }}</strong>
                            <span data-preview-label>{{ $groupMeta[$initialGroup]['previewLabel'] }}</span>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-row">
                            <span>年代</span>
                            <strong data-summary-era>{{ $initialEra }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>感情</span>
                            <strong data-summary-emotion>{{ $initialEmotion }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>状態</span>
                            <strong data-summary-state>{{ trim($initialContent) !== '' ? '入力中' : '保存前' }}</strong>
                        </div>
                    </div>

                    <div class="preview-actions">
                        <button class="secondary-button" type="button" data-draft-save>下書きにする</button>
                        <button class="primary-button" type="submit">この記憶を保存</button>
                    </div>

                    <p class="draft-status" data-draft-status aria-live="polite"></p>
                </div>
            </aside>
        </form>
    </section>

    <style>
        .page.page-memory-create {
            width: min(1480px, calc(100vw - 24px));
            padding: 16px 0 28px;
        }

        .body-memory-create {
            background:
                radial-gradient(circle at 12% 16%, rgba(255, 186, 140, 0.18), transparent 20%),
                radial-gradient(circle at 88% 12%, rgba(126, 209, 255, 0.14), transparent 20%),
                radial-gradient(circle at 74% 84%, rgba(135, 118, 255, 0.18), transparent 26%),
                linear-gradient(180deg, #050a17 0%, #091226 46%, #101c35 100%);
        }

        .memory-create-page {
            --theme-primary: #f39a63;
            --theme-secondary: rgba(255, 193, 120, 0.42);
            --theme-surface: rgba(255, 165, 112, 0.12);
            --theme-shadow: rgba(242, 137, 72, 0.28);
            --theme-glow: rgba(255, 220, 170, 0.88);
            position: relative;
            min-height: calc(100vh - 44px);
            overflow: hidden;
            border-radius: 36px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at 12% 18%, rgba(255, 255, 255, 0.08), transparent 18%),
                radial-gradient(circle at 85% 14%, rgba(255, 255, 255, 0.05), transparent 20%),
                linear-gradient(135deg, rgba(7, 13, 27, 0.96), rgba(9, 17, 35, 0.92));
            box-shadow: 0 40px 90px rgba(0, 0, 0, 0.34);
            isolation: isolate;
        }

        .memory-create-page.theme-calm {
            --theme-primary: #7eb3f4;
            --theme-secondary: rgba(135, 184, 255, 0.36);
            --theme-surface: rgba(102, 146, 255, 0.12);
            --theme-shadow: rgba(80, 124, 255, 0.25);
            --theme-glow: rgba(205, 228, 255, 0.86);
        }

        .memory-create-page.theme-sway {
            --theme-primary: #5fb8ff;
            --theme-secondary: rgba(101, 208, 255, 0.34);
            --theme-surface: rgba(54, 126, 222, 0.12);
            --theme-shadow: rgba(36, 111, 196, 0.26);
            --theme-glow: rgba(195, 236, 255, 0.84);
        }

        .memory-create-page.theme-heavy {
            --theme-primary: #9e7dff;
            --theme-secondary: rgba(176, 121, 255, 0.34);
            --theme-surface: rgba(134, 102, 255, 0.12);
            --theme-shadow: rgba(113, 69, 218, 0.3);
            --theme-glow: rgba(222, 211, 255, 0.84);
        }

        .memory-shell {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(250px, 0.82fr) minmax(0, 1.45fr) minmax(300px, 0.9fr);
            gap: 24px;
            padding: 26px;
            align-items: start;
        }

        .memory-left,
        .composer-card,
        .preview-panel {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.09);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03)),
                rgba(7, 12, 26, 0.62);
            box-shadow: 0 28px 70px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(18px);
        }

        .memory-left {
            padding: 28px 24px;
            display: grid;
            gap: 18px;
            align-content: start;
            min-height: 100%;
        }

        .memory-eyebrow,
        .preview-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(226, 236, 255, 0.7);
            font-size: 11px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .memory-title {
            margin: 0;
            color: rgba(249, 251, 255, 0.98);
            font-size: clamp(36px, 4vw, 60px);
            line-height: 1.02;
            letter-spacing: 0.03em;
        }

        .memory-description,
        .section-heading-wrap p,
        .preview-heading p,
        .textarea-meta,
        .draft-status {
            color: rgba(205, 221, 255, 0.72);
            line-height: 1.8;
        }

        .memory-description {
            margin: 0;
            font-size: 15px;
            max-width: 24rem;
        }

        .ghost-button,
        .secondary-button,
        .primary-button,
        .emotion-group-chip,
        .era-chip span,
        .emotion-bubble span {
            appearance: none;
            border: 1px solid transparent;
            font: inherit;
        }

        .ghost-button,
        .secondary-button,
        .primary-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 50px;
            padding: 0 18px;
            border-radius: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.22s ease, border-color 0.22s ease, background 0.22s ease, box-shadow 0.22s ease;
        }

        .ghost-button,
        .secondary-button {
            color: rgba(235, 242, 255, 0.92);
            border-color: rgba(171, 202, 255, 0.16);
            background: linear-gradient(135deg, rgba(17, 28, 52, 0.94), rgba(8, 15, 31, 0.98));
            box-shadow: 0 14px 28px rgba(6, 10, 24, 0.24);
        }

        .primary-button {
            color: #fff9f5;
            background: linear-gradient(135deg, color-mix(in srgb, var(--theme-primary) 85%, white 15%), var(--theme-primary));
            box-shadow: 0 18px 40px var(--theme-shadow);
        }

        .ghost-button:hover,
        .secondary-button:hover,
        .primary-button:hover,
        .emotion-group-chip:hover,
        .era-chip:hover span,
        .emotion-bubble:hover span {
            transform: translateY(-2px);
        }

        .composer-card {
            padding: 28px;
            display: grid;
            gap: 24px;
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            color: rgba(202, 220, 255, 0.68);
        }

        .step-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 13px;
            letter-spacing: 0.04em;
        }

        .step-item.is-done,
        .step-item.is-active {
            color: rgba(248, 251, 255, 0.96);
            border-color: rgba(255, 255, 255, 0.14);
        }

        .step-dot,
        .star {
            display: block;
            border-radius: 999px;
        }

        .step-dot {
            width: 9px;
            height: 9px;
            background: rgba(255, 255, 255, 0.28);
            box-shadow: 0 0 0 8px rgba(255, 255, 255, 0.03);
        }

        .step-item.is-active .step-dot,
        .step-item.is-done .step-dot {
            background: var(--theme-glow);
            box-shadow: 0 0 16px rgba(255, 255, 255, 0.24);
        }

        .step-line {
            width: 40px;
            height: 1px;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0.04));
        }

        .memory-error-list {
            display: grid;
            gap: 8px;
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(140, 35, 43, 0.18);
            border: 1px solid rgba(255, 137, 137, 0.18);
            color: rgba(255, 221, 221, 0.94);
            font-size: 14px;
        }

        .composer-section {
            display: grid;
            gap: 18px;
        }

        .section-heading-wrap h2 {
            margin: 0 0 6px;
            color: rgba(248, 251, 255, 0.98);
            font-size: clamp(22px, 2.6vw, 28px);
            letter-spacing: 0.02em;
        }

        .section-heading-wrap p,
        .preview-heading p {
            margin: 0;
            font-size: 14px;
        }

        .era-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .era-chip {
            display: block;
            cursor: pointer;
        }

        .era-chip span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 54px;
            padding: 12px 16px;
            border-radius: 18px;
            color: rgba(235, 242, 255, 0.92);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(171, 202, 255, 0.12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            transition: transform 0.22s ease, border-color 0.22s ease, background 0.22s ease, box-shadow 0.22s ease, color 0.22s ease;
        }

        .era-chip input:checked + span {
            color: #fff9f4;
            border-color: color-mix(in srgb, var(--theme-primary) 60%, white 20%);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--theme-primary) 26%, transparent), rgba(255, 255, 255, 0.05)),
                rgba(255, 255, 255, 0.08);
            box-shadow:
                0 14px 30px color-mix(in srgb, var(--theme-shadow) 82%, transparent),
                inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .memory-textarea-wrap {
            display: grid;
            gap: 10px;
        }

        .memory-textarea {
            width: 100%;
            min-height: 220px;
            resize: vertical;
            padding: 20px 22px;
            border-radius: 24px;
            border: 1px solid rgba(172, 198, 255, 0.14);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03)),
                rgba(9, 14, 30, 0.9);
            color: rgba(244, 248, 255, 0.96);
            line-height: 1.95;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transition: border-color 0.22s ease, box-shadow 0.22s ease;
        }

        .memory-textarea:focus {
            outline: none;
            border-color: color-mix(in srgb, var(--theme-primary) 54%, white 24%);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.04), 0 16px 40px rgba(0, 0, 0, 0.18);
        }

        .memory-textarea::placeholder {
            color: rgba(178, 196, 231, 0.46);
        }

        .textarea-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
        }

        .emotion-group-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .emotion-group-chip {
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: rgba(223, 234, 255, 0.82);
            cursor: pointer;
            transition: transform 0.22s ease, border-color 0.22s ease, background 0.22s ease, color 0.22s ease, box-shadow 0.22s ease;
        }

        .emotion-group-chip.is-selected {
            color: #fffaf5;
            border-color: color-mix(in srgb, var(--theme-primary) 55%, white 18%);
            background: linear-gradient(135deg, color-mix(in srgb, var(--theme-primary) 30%, transparent), rgba(255, 255, 255, 0.06));
            box-shadow: 0 16px 30px color-mix(in srgb, var(--theme-shadow) 80%, transparent);
        }

        .emotion-bubble-field {
            display: none;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
        }

        .emotion-bubble-field.is-active {
            display: flex;
        }

        .emotion-bubble {
            display: block;
            cursor: pointer;
        }

        .emotion-bubble span {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-width: 82px;
            min-height: 82px;
            padding: 16px;
            border-radius: 999px;
            text-align: center;
            color: rgba(239, 245, 255, 0.9);
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.22), transparent 30%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03)),
                rgba(11, 18, 37, 0.92);
            border-color: rgba(180, 205, 255, 0.12);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.08),
                0 12px 24px rgba(0, 0, 0, 0.18);
            transition: transform 0.22s ease, border-color 0.22s ease, background 0.22s ease, box-shadow 0.22s ease, color 0.22s ease;
        }

        .emotion-bubble.lg span {
            min-width: 114px;
            min-height: 114px;
            font-size: 15px;
        }

        .emotion-bubble.md span {
            min-width: 96px;
            min-height: 96px;
            font-size: 14px;
        }

        .emotion-bubble.sm span {
            min-width: 84px;
            min-height: 84px;
            font-size: 13px;
        }

        .emotion-bubble input:checked + span {
            color: #fffaf4;
            border-color: color-mix(in srgb, var(--theme-primary) 58%, white 18%);
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.28), transparent 30%),
                linear-gradient(180deg, color-mix(in srgb, var(--theme-primary) 32%, rgba(255,255,255,0.08)), rgba(255, 255, 255, 0.06)),
                rgba(14, 21, 42, 0.95);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 20px 42px color-mix(in srgb, var(--theme-shadow) 76%, transparent);
        }

        .memory-right {
            position: sticky;
            top: 16px;
        }

        .preview-panel {
            padding: 24px;
            display: grid;
            gap: 20px;
        }

        .preview-heading {
            display: grid;
            gap: 10px;
        }

        .preview-bubble {
            position: relative;
            aspect-ratio: 1 / 1;
            width: min(100%, 320px);
            margin: 0 auto;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at 35% 28%, rgba(255, 255, 255, 0.32), transparent 32%),
                radial-gradient(circle at 66% 70%, color-mix(in srgb, var(--theme-primary) 22%, transparent), transparent 44%),
                rgba(255, 255, 255, 0.06);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.14),
                0 28px 58px rgba(0, 0, 0, 0.2);
            transition: transform 0.24s ease, box-shadow 0.24s ease;
        }

        .preview-empty {
            transform: scale(0.88);
            opacity: 0.92;
        }

        .preview-soft {
            transform: scale(0.95);
        }

        .preview-medium {
            transform: scale(1);
        }

        .preview-dense {
            transform: scale(1.06);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                0 32px 64px color-mix(in srgb, var(--theme-shadow) 76%, rgba(0, 0, 0, 0.12));
        }

        .preview-bubble-core,
        .preview-bubble-ring {
            position: absolute;
            inset: 0;
            margin: auto;
            border-radius: 999px;
            pointer-events: none;
        }

        .preview-bubble-core {
            width: 54%;
            height: 54%;
            background: radial-gradient(circle, color-mix(in srgb, var(--theme-primary) 38%, white 22%), transparent 68%);
            filter: blur(1px);
        }

        .preview-bubble-ring {
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .preview-bubble-ring.ring-1 {
            width: 72%;
            height: 72%;
        }

        .preview-bubble-ring.ring-2 {
            width: 88%;
            height: 88%;
            border-color: rgba(255, 255, 255, 0.08);
        }

        .preview-content {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 10px;
            text-align: center;
            padding: 22px;
        }

        .preview-content small,
        .preview-content span {
            color: rgba(221, 233, 255, 0.76);
        }

        .preview-content small {
            font-size: 12px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .preview-content strong {
            color: rgba(248, 251, 255, 0.98);
            font-size: clamp(24px, 4vw, 32px);
            line-height: 1.2;
        }

        .preview-content span {
            font-size: 13px;
        }

        .summary-card {
            display: grid;
            gap: 12px;
            padding: 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .summary-row span {
            color: rgba(205, 221, 255, 0.68);
            font-size: 13px;
        }

        .summary-row strong {
            color: rgba(247, 250, 255, 0.98);
            font-size: 15px;
        }

        .preview-actions {
            display: grid;
            gap: 12px;
        }

        .draft-status {
            min-height: 26px;
            margin: 0;
            font-size: 13px;
        }

        .memory-ambient {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        .star {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(255, 255, 255, 0.65);
            box-shadow: 0 0 18px rgba(255, 255, 255, 0.3);
        }

        .star.s1 { top: 12%; left: 14%; }
        .star.s2 { top: 18%; right: 18%; }
        .star.s3 { bottom: 24%; left: 11%; }
        .star.s4 { bottom: 18%; right: 12%; }

        .orb {
            position: absolute;
            border-radius: 999px;
            background: radial-gradient(circle, color-mix(in srgb, var(--theme-primary) 26%, white 10%), transparent 68%);
            filter: blur(10px);
            opacity: 0.82;
        }

        .orb-1 {
            top: 8%;
            right: 30%;
            width: 180px;
            height: 180px;
        }

        .orb-2 {
            bottom: 8%;
            left: 16%;
            width: 240px;
            height: 240px;
        }

        .orb-3 {
            bottom: 18%;
            right: 8%;
            width: 140px;
            height: 140px;
        }

        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 1200px) {
            .memory-shell {
                grid-template-columns: minmax(220px, 0.78fr) minmax(0, 1fr);
            }

            .memory-right {
                grid-column: 1 / -1;
                position: static;
            }

            .preview-panel {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
            }

            .preview-heading,
            .preview-actions,
            .draft-status {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 860px) {
            .page.page-memory-create {
                width: min(100vw - 16px, 1480px);
                padding: 8px 0 16px;
            }

            .memory-create-page {
                min-height: auto;
                border-radius: 28px;
            }

            .memory-shell {
                grid-template-columns: 1fr;
                padding: 16px;
            }

            .memory-left,
            .composer-card,
            .preview-panel {
                border-radius: 24px;
            }

            .preview-panel {
                grid-template-columns: 1fr;
            }

            .era-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .memory-title {
                font-size: 34px;
            }

            .composer-card,
            .memory-left,
            .preview-panel {
                padding: 20px 18px;
            }

            .step-header {
                gap: 8px;
            }

            .step-line {
                width: 24px;
            }

            .textarea-meta,
            .summary-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .emotion-bubble-field {
                gap: 10px;
            }
        }
    </style>

    <script>
        (() => {
            const root = document.querySelector("[data-memory-create]");

            if (!root) {
                return;
            }

            const form = root.querySelector("form");
            const contentInput = root.querySelector("[data-content-input]");
            const charCount = root.querySelector("[data-char-count]");
            const previewBubble = root.querySelector("[data-preview-bubble]");
            const previewTone = root.querySelector("[data-preview-tone]");
            const previewEra = root.querySelector("[data-preview-era]");
            const previewEmotion = root.querySelector("[data-preview-emotion]");
            const previewLabel = root.querySelector("[data-preview-label]");
            const summaryEra = root.querySelector("[data-summary-era]");
            const summaryEmotion = root.querySelector("[data-summary-emotion]");
            const summaryState = root.querySelector("[data-summary-state]");
            const draftButton = root.querySelector("[data-draft-save]");
            const draftStatus = root.querySelector("[data-draft-status]");
            const groupButtons = Array.from(root.querySelectorAll("[data-group-button]"));
            const groupFields = Array.from(root.querySelectorAll("[data-group-field]"));
            const draftKey = "memory-create-draft";
            const groupMeta = @json($groupMeta);
            const emotionToGroup = @json($emotionToGroup);
            const hasErrors = @json($errors->any());

            const filledClasses = ["preview-empty", "preview-soft", "preview-medium", "preview-dense"];

            const getSelectedPeriod = () => form.querySelector('input[name="period"]:checked')?.value ?? "年代未選択";
            const getSelectedEmotion = () => form.querySelector('input[name="emotion"]:checked')?.value ?? "感情を選択";
            const getSelectedGroup = () => {
                const selectedEmotion = form.querySelector('input[name="emotion"]:checked')?.value;

                return emotionToGroup[selectedEmotion] ?? root.dataset.initialGroup ?? "warm";
            };

            const getFilledLevel = () => {
                const length = contentInput.value.trim().length;

                if (length === 0) {
                    return "empty";
                }

                if (length < 60) {
                    return "soft";
                }

                if (length < 140) {
                    return "medium";
                }

                return "dense";
            };

            const setTheme = (group) => {
                root.classList.remove("theme-warm", "theme-calm", "theme-sway", "theme-heavy");
                root.classList.add(`theme-${group}`);

                groupButtons.forEach((button) => {
                    button.classList.toggle("is-selected", button.dataset.groupButton === group);
                });

                groupFields.forEach((field) => {
                    field.classList.toggle("is-active", field.dataset.groupField === group);
                });

                const meta = groupMeta[group];

                if (meta) {
                    previewTone.textContent = meta.tone;
                    previewLabel.textContent = meta.previewLabel;
                }
            };

            const refreshPreview = () => {
                const selectedGroup = getSelectedGroup();
                const selectedPeriod = getSelectedPeriod();
                const selectedEmotion = getSelectedEmotion();
                const filledLevel = getFilledLevel();
                const trimmedLength = contentInput.value.trim().length;

                setTheme(selectedGroup);
                previewEra.textContent = selectedPeriod;
                previewEmotion.textContent = selectedEmotion;
                summaryEra.textContent = selectedPeriod;
                summaryEmotion.textContent = selectedEmotion;
                summaryState.textContent = trimmedLength > 0 ? "入力中" : "保存前";
                charCount.textContent = `${trimmedLength} 文字`;

                filledClasses.forEach((className) => previewBubble.classList.remove(className));
                previewBubble.classList.add(`preview-${filledLevel}`);
            };

            const setGroupSelection = (group) => {
                const field = root.querySelector(`[data-group-field="${group}"]`);

                if (!field) {
                    return;
                }

                const selectedEmotion = form.querySelector('input[name="emotion"]:checked');
                const isSameGroupSelected = selectedEmotion && emotionToGroup[selectedEmotion.value] === group;

                if (!isSameGroupSelected) {
                    const nextInput = field.querySelector('input[name="emotion"]');

                    if (nextInput) {
                        nextInput.checked = true;
                    }
                }

                refreshPreview();
            };

            const hydrateDraft = () => {
                if (hasErrors) {
                    return;
                }

                try {
                    const raw = window.localStorage.getItem(draftKey);

                    if (!raw) {
                        return;
                    }

                    const draft = JSON.parse(raw);

                    if (contentInput.value.trim() !== "") {
                        return;
                    }

                    if (draft.period) {
                        const periodInput = form.querySelector(`input[name="period"][value="${draft.period}"]`);

                        if (periodInput) {
                            periodInput.checked = true;
                        }
                    }

                    if (typeof draft.content === "string") {
                        contentInput.value = draft.content;
                    }

                    if (draft.emotion) {
                        const emotionInput = form.querySelector(`input[name="emotion"][value="${draft.emotion}"]`);

                        if (emotionInput) {
                            emotionInput.checked = true;
                        }
                    }

                    draftStatus.textContent = "前回の下書きを読み込みました。";
                } catch (error) {
                    draftStatus.textContent = "";
                }
            };

            groupButtons.forEach((button) => {
                button.addEventListener("click", () => {
                    setGroupSelection(button.dataset.groupButton);
                });
            });

            form.addEventListener("change", (event) => {
                const target = event.target;

                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                if (target.name === "emotion" || target.name === "period") {
                    refreshPreview();
                }
            });

            contentInput.addEventListener("input", refreshPreview);

            draftButton?.addEventListener("click", () => {
                try {
                    const draft = {
                        period: form.querySelector('input[name="period"]:checked')?.value ?? null,
                        content: contentInput.value,
                        emotion: form.querySelector('input[name="emotion"]:checked')?.value ?? null,
                    };

                    window.localStorage.setItem(draftKey, JSON.stringify(draft));
                    draftStatus.textContent = "このブラウザに下書きを保存しました。";
                } catch (error) {
                    draftStatus.textContent = "下書き保存に失敗しました。";
                }
            });

            hydrateDraft();
            refreshPreview();
        })();
    </script>
@endsection
