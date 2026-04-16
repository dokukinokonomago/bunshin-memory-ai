@extends('layouts.app')

@section('title', '記憶入力 | 分身AI MVP')

@section('content')
    <section class="memory-create-page">
        <section class="memory-create-hero">
            <span class="eyebrow">Memory Input</span>
            <div class="memory-create-headline">
                <h1>新しい記憶を登録</h1>
                <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧へ戻る</a>
            </div>
            <p class="hero-copy">
                年代、内容、感情を選ぶだけで記憶を保存できます。感情は仕様書の固定リストから単一選択です。
            </p>
        </section>

        <section class="panel memory-create-panel">
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
                    <div class="chip-group period-chip-group">
                        @foreach ($periods as $period)
                            <label class="chip-option">
                                <input type="radio" name="period" value="{{ $period }}" {{ old('period', $periods[0]) === $period ? 'checked' : '' }}>
                                <span class="chip-button chip-button-dark">{{ $period }}</span>
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
                        @php
                            $emotionClass = str_contains($group, 'ポジティブ') ? 'chip-button-warm' : (str_contains($group, 'ニュートラル') ? 'chip-button-neutral' : 'chip-button-cool');
                        @endphp
                        <div class="emotion-section">
                            <h4>{{ $group }}</h4>
                            <div class="chip-group">
                                @foreach ($emotions as $emotion)
                                    <label class="chip-option">
                                        <input type="radio" name="emotion" value="{{ $emotion }}" {{ old('emotion') === $emotion ? 'checked' : '' }}>
                                        <span class="chip-button {{ $emotionClass }}">{{ $emotion }}</span>
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
        </section>
    </section>

    <style>
        .memory-create-page {
            display: grid;
            gap: 22px;
        }

        .memory-create-hero,
        .memory-create-panel {
            background: linear-gradient(180deg, rgba(10, 18, 34, 0.9), rgba(7, 13, 27, 0.92));
            border: 1px solid rgba(155, 198, 255, 0.12);
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(2, 4, 12, 0.28);
            backdrop-filter: blur(14px);
        }

        .memory-create-hero {
            padding: 28px;
        }

        .memory-create-headline {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin: 16px 0 12px;
        }

        .memory-create-headline h1 {
            margin: 0;
            color: rgba(247, 250, 255, 0.96);
        }

        .memory-create-page .hero-copy,
        .memory-create-page .field label,
        .memory-create-page .emotion-section h4 {
            color: rgba(188, 214, 255, 0.74);
        }

        .memory-create-panel {
            padding: 24px;
        }

        .memory-create-page .btn,
        .memory-create-page .chip-button {
            border-radius: 12px;
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease;
        }

        .memory-create-page .btn:hover,
        .memory-create-page .chip-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(18, 36, 78, 0.32);
        }

        .memory-create-page .btn-primary,
        .memory-create-page .btn-secondary {
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            border-color: rgba(166, 204, 255, 0.16);
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.28);
        }

        .memory-create-page .btn-primary:hover,
        .memory-create-page .btn-secondary:hover {
            border-color: rgba(196, 224, 255, 0.34);
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
            color: rgba(250, 252, 255, 0.98);
        }

        .memory-create-page .chip-group {
            gap: 10px;
        }

        .memory-create-page .chip-option input:checked + .chip-button,
        .memory-create-page .chip-button:hover {
            border-color: rgba(214, 230, 255, 0.32);
            color: rgba(250, 252, 255, 0.98);
        }

        .chip-button-dark {
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            border: 1px solid rgba(166, 204, 255, 0.16);
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.24);
        }

        .chip-button-dark:hover,
        .chip-option input:checked + .chip-button-dark {
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
        }

        .chip-button-warm {
            background: linear-gradient(135deg, rgba(97, 37, 18, 0.94), rgba(146, 74, 34, 0.96));
            border: 1px solid rgba(247, 186, 134, 0.24);
            color: rgba(255, 238, 228, 0.94);
            box-shadow: 0 10px 24px rgba(60, 20, 8, 0.22);
        }

        .chip-button-warm:hover,
        .chip-option input:checked + .chip-button-warm {
            background: linear-gradient(135deg, rgba(235, 122, 73, 0.92), rgba(255, 168, 97, 0.92));
        }

        .chip-button-neutral {
            background: linear-gradient(135deg, rgba(52, 59, 72, 0.94), rgba(82, 96, 112, 0.96));
            border: 1px solid rgba(184, 196, 214, 0.22);
            color: rgba(238, 243, 251, 0.94);
            box-shadow: 0 10px 24px rgba(20, 24, 32, 0.22);
        }

        .chip-button-neutral:hover,
        .chip-option input:checked + .chip-button-neutral {
            background: linear-gradient(135deg, rgba(129, 144, 164, 0.92), rgba(171, 183, 198, 0.9));
            color: rgba(18, 26, 44, 0.96);
        }

        .chip-button-cool {
            background: linear-gradient(135deg, rgba(18, 41, 96, 0.94), rgba(34, 69, 146, 0.96));
            border: 1px solid rgba(147, 192, 255, 0.24);
            color: rgba(232, 241, 255, 0.94);
            box-shadow: 0 10px 24px rgba(10, 24, 60, 0.24);
        }

        .chip-button-cool:hover,
        .chip-option input:checked + .chip-button-cool {
            background: linear-gradient(135deg, rgba(74, 139, 255, 0.92), rgba(123, 190, 255, 0.92));
        }

        .memory-create-page textarea {
            border: 1px solid rgba(171, 205, 255, 0.2);
            background: rgba(14, 22, 43, 0.88);
            color: rgba(239, 245, 255, 0.94);
        }

        .memory-create-page textarea::placeholder {
            color: rgba(176, 202, 245, 0.52);
        }

        @media (max-width: 760px) {
            .memory-create-hero,
            .memory-create-panel {
                border-radius: 22px;
            }

            .memory-create-hero,
            .memory-create-panel {
                padding: 18px;
            }
        }
    </style>
@endsection
