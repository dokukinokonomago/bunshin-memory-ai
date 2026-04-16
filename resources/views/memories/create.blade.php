@extends('layouts.app')

@section('title', '記憶を追加 | 分身AI MVP')

@php
    $emotionPalettes = [
        '嬉しい' => ['#ffd86b', '#ff9e19'],
        '楽しい' => ['#ffc95e', '#ff7a1a'],
        '安心' => ['#ffe07f', '#e0aa2d'],
        'ホッとした' => ['#f8c98b', '#c9823f'],
        '幸せ' => ['#ffb47d', '#f06b2f'],
        '満足' => ['#ff9f70', '#d95822'],
        'ワクワク' => ['#ff9270', '#ff5331'],
        '感謝' => ['#ffb7a3', '#ce6f55'],
        '誇らしい' => ['#ff8d7a', '#bd4723'],
        '自信がある' => ['#ff7b67', '#a83317'],
        '普通' => ['#eef6ff', '#a2b8cc'],
        'なんとなく' => ['#dce9ff', '#63a6ff'],
        '落ち着いている' => ['#d7fff3', '#36b89a'],
        'ぼーっとした' => ['#e9f5ff', '#7cb4d9'],
        '考え中' => ['#e0e6ff', '#6e7fe8'],
        'モヤモヤ' => ['#f2dbff', '#b46be2'],
        '少し不安' => ['#dde8ff', '#5d88ef'],
        '疲れた' => ['#e3e0f7', '#7c7ea5'],
        '迷い' => ['#e4d8ff', '#8654d9'],
        '気まずい' => ['#ffdff6', '#d46cc5'],
        '引っかかる' => ['#ddd4ff', '#6f55c9'],
        '不安' => ['#eadfff', '#8f7cff'],
        '悲しい' => ['#d5e5ff', '#3b83d6'],
        'イライラ' => ['#ffd8e8', '#e5488a'],
        '怒り' => ['#ffd3d3', '#e04545'],
        '落ち込み' => ['#d5d8ff', '#5562db'],
        '孤独' => ['#d7d0ff', '#4c3fb3'],
        '無力感' => ['#cfe0ff', '#3870c8'],
        '自信がない' => ['#e0d7ff', '#7257de'],
    ];
@endphp

@section('content')
    <section class="memory-create-page">
        <section class="memory-create-hero">
            <span class="eyebrow">MEMORY INPUT</span>
            <div class="memory-create-headline">
                <h1>記憶を追加</h1>
                <a class="memory-control-btn" href="{{ route('memories.index') }}">一覧へ戻る</a>
            </div>
            <p class="hero-copy">
                年代、内容、感情を選ぶだけで記憶を保存できます。感情ボタンの色は、そのまま記憶玉の色合いとして扱えるように設計しています。
            </p>
        </section>

        <section class="memory-create-panel">
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
                    <div class="memory-chip-grid memory-period-grid">
                        @foreach ($periods as $period)
                            <label class="memory-chip-option">
                                <input type="radio" name="period" value="{{ $period }}" {{ old('period', $periods[0]) === $period ? 'checked' : '' }}>
                                <span class="memory-chip memory-chip-dark">{{ $period }}</span>
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
                    <div class="emotion-groups">
                        @foreach ($emotionGroups as $group => $emotions)
                            <section class="emotion-group-card">
                                <h4>{{ $group }}</h4>
                                <div class="memory-chip-grid">
                                    @foreach ($emotions as $emotion)
                                        @php
                                            [$startColor, $endColor] = $emotionPalettes[$emotion];
                                        @endphp
                                        <label class="memory-chip-option">
                                            <input type="radio" name="emotion" value="{{ $emotion }}" {{ old('emotion') === $emotion ? 'checked' : '' }}>
                                            <span
                                                class="memory-chip memory-chip-emotion"
                                                style="--emotion-start: {{ $startColor }}; --emotion-end: {{ $endColor }};"
                                            >{{ $emotion }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>

                <div class="form-actions">
                    <button class="memory-control-btn memory-control-btn-primary" type="submit">保存する</button>
                    <a class="memory-control-btn" href="{{ route('memories.index') }}">キャンセル</a>
                </div>
            </form>
        </section>
    </section>

    <style>
        .memory-create-page {
            display: grid;
            gap: 26px;
        }

        .memory-create-hero,
        .memory-create-panel {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid rgba(155, 198, 255, 0.12);
            background:
                radial-gradient(circle at 20% 18%, rgba(100, 155, 255, 0.08), transparent 24%),
                radial-gradient(circle at 82% 12%, rgba(121, 215, 255, 0.08), transparent 22%),
                linear-gradient(180deg, rgba(9, 17, 34, 0.94), rgba(6, 12, 24, 0.96));
            box-shadow: 0 28px 70px rgba(2, 4, 12, 0.34);
            backdrop-filter: blur(18px);
        }

        .memory-create-hero {
            padding: 30px 32px 26px;
        }

        .memory-create-headline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            margin: 18px 0 12px;
        }

        .memory-create-headline h1 {
            margin: 0;
            color: rgba(248, 251, 255, 0.98);
            font-size: clamp(34px, 4vw, 52px);
            letter-spacing: 0.03em;
        }

        .memory-create-page .hero-copy,
        .memory-create-page .field label,
        .memory-create-page .emotion-group-card h4 {
            color: rgba(188, 214, 255, 0.74);
        }

        .memory-create-page .hero-copy {
            max-width: 780px;
            margin: 0;
        }

        .memory-create-panel {
            padding: 28px;
        }

        .memory-create-page .field {
            gap: 14px;
        }

        .memory-create-page .field > label {
            font-size: 13px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .memory-chip-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .memory-period-grid {
            gap: 10px;
        }

        .memory-chip-option {
            position: relative;
        }

        .memory-chip-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .memory-chip,
        .memory-control-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 16px;
            border: 1px solid rgba(167, 202, 255, 0.16);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-decoration: none;
            transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease, background 0.22s ease, color 0.22s ease, filter 0.22s ease;
        }

        .memory-control-btn {
            background: linear-gradient(135deg, rgba(17, 28, 52, 0.96), rgba(8, 15, 31, 0.98));
            color: rgba(234, 242, 255, 0.92);
            box-shadow: 0 14px 28px rgba(6, 10, 24, 0.24);
        }

        .memory-control-btn:hover {
            transform: translateY(-2px);
            border-color: rgba(205, 228, 255, 0.34);
            background: linear-gradient(135deg, rgba(94, 155, 255, 0.42), rgba(45, 86, 191, 0.96));
            color: rgba(250, 252, 255, 0.98);
            box-shadow: 0 18px 36px rgba(15, 33, 74, 0.34);
        }

        .memory-control-btn-primary {
            min-width: 156px;
        }

        .memory-chip {
            position: relative;
            overflow: hidden;
            cursor: pointer;
            color: rgba(244, 248, 255, 0.94);
            box-shadow: 0 12px 26px rgba(6, 10, 24, 0.2);
        }

        .memory-chip::before {
            content: "";
            position: absolute;
            inset: 1px;
            border-radius: 15px;
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.14), transparent 34%);
            opacity: 0.8;
            pointer-events: none;
        }

        .memory-chip:hover,
        .memory-chip-option input:checked + .memory-chip {
            transform: translateY(-2px) scale(1.02);
            border-color: rgba(217, 232, 255, 0.34);
            box-shadow: 0 18px 34px rgba(14, 34, 82, 0.26);
            color: rgba(250, 252, 255, 0.98);
        }

        .memory-chip-dark {
            background: linear-gradient(135deg, rgba(16, 26, 50, 0.96), rgba(7, 14, 29, 0.98));
        }

        .memory-chip-dark:hover,
        .memory-chip-option input:checked + .memory-chip-dark {
            background: linear-gradient(135deg, rgba(83, 145, 255, 0.38), rgba(40, 72, 160, 0.92));
        }

        .memory-chip-emotion {
            border-color: color-mix(in srgb, var(--emotion-end) 22%, rgba(240, 247, 255, 0.12));
            background:
                radial-gradient(circle at 28% 24%, rgba(255, 255, 255, 0.42), transparent 22%),
                linear-gradient(135deg, color-mix(in srgb, var(--emotion-start) 92%, white 8%), color-mix(in srgb, var(--emotion-end) 92%, black 8%));
            color: rgba(20, 22, 34, 0.9);
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .memory-chip-emotion:hover,
        .memory-chip-option input:checked + .memory-chip-emotion {
            filter: saturate(1.08) brightness(1.06);
            box-shadow:
                0 18px 34px color-mix(in srgb, var(--emotion-end) 24%, rgba(9, 15, 31, 0.76)),
                0 0 0 1px color-mix(in srgb, var(--emotion-start) 44%, rgba(255, 255, 255, 0.1));
            color: rgba(14, 18, 28, 0.96);
        }

        .emotion-groups {
            display: grid;
            gap: 18px;
        }

        .emotion-group-card {
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(145, 183, 240, 0.1);
            background: linear-gradient(180deg, rgba(8, 15, 28, 0.72), rgba(9, 16, 31, 0.44));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .emotion-group-card h4 {
            margin: 0 0 14px;
            font-size: 12px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .memory-create-page textarea {
            width: 100%;
            min-height: 190px;
            padding: 18px 20px;
            border-radius: 22px;
            border: 1px solid rgba(171, 205, 255, 0.16);
            background: linear-gradient(180deg, rgba(14, 22, 43, 0.92), rgba(10, 17, 33, 0.96));
            color: rgba(239, 245, 255, 0.95);
            resize: vertical;
            line-height: 1.9;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
            transition: border-color 0.22s ease, box-shadow 0.22s ease;
        }

        .memory-create-page textarea:focus {
            outline: none;
            border-color: rgba(128, 185, 255, 0.34);
            box-shadow: 0 0 0 1px rgba(105, 159, 255, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .memory-create-page textarea::placeholder {
            color: rgba(176, 202, 245, 0.48);
        }

        .memory-create-page .error-list {
            background: rgba(88, 20, 34, 0.34);
            border: 1px solid rgba(255, 154, 181, 0.18);
            color: rgba(255, 214, 226, 0.92);
        }

        .form-actions {
            margin-top: 4px;
        }

        @media (max-width: 760px) {
            .memory-create-hero,
            .memory-create-panel {
                padding: 20px 18px;
                border-radius: 24px;
            }

            .memory-chip,
            .memory-control-btn {
                min-height: 44px;
                padding: 0 14px;
                font-size: 13px;
            }

            .memory-create-headline {
                align-items: flex-start;
            }
        }
    </style>
@endsection
