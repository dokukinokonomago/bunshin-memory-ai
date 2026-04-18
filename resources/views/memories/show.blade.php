@extends('layouts.app')

@section('title', '記憶ステータス | 分身AI MVP')

@php
    $badgeClass = str_contains($tone, 'ポジティブ') ? 'badge-positive' : (str_contains($tone, 'ニュートラル') ? 'badge-neutral' : 'badge-negative');
@endphp

@section('content')
    <section class="memory-status-panel">
        <div class="memory-status-copy">
            <span class="eyebrow">MEMORY STATUS VIEW</span>
            <div class="memory-status-topline">
                <h1>記憶ステータス</h1>
                <div class="memory-status-actions">
                    <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶玉へ戻る</a>
                    <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧を見る</a>
                </div>
            </div>
        </div>

        <div class="memory-status-stage">
            <div class="memory-focus-shell">
                <div class="memory-focus-orbit memory-focus-orbit-back"></div>
                <div class="memory-focus-orbit memory-focus-orbit-front"></div>
                <div class="memory-focus-bubble" style="--bubble-start: {{ $colors[0] }}; --bubble-end: {{ $colors[1] }};">
                    <div class="memory-focus-aura"></div>
                    <div class="memory-focus-core">
                        <span class="memory-focus-period">{{ $memory->period }}</span>
                        <strong>{{ $theme }}</strong>
                        <span class="memory-focus-emotion">{{ $memory->emotion }}</span>
                    </div>
                </div>
            </div>

            <div class="memory-status-info-strip">
                <article class="memory-status-card memory-status-card-wide">
                    <span class="memory-status-label">テーマ</span>
                    <h2>{{ $theme }}</h2>
                </article>

                <article class="memory-status-card memory-status-card-wide">
                    <span class="memory-status-label">内容</span>
                    <p>{{ $memory->content }}</p>
                </article>

                <article class="memory-status-card">
                    <span class="memory-status-label">ライフステージ</span>
                    <div class="memory-status-chip">{{ $memory->period }}</div>
                </article>

                <article class="memory-status-card">
                    <span class="memory-status-label">感情</span>
                    <div class="memory-status-emotion">
                        <span class="badge {{ $badgeClass }}">{{ $memory->emotion }}</span>
                        <small>{{ $tone }}</small>
                    </div>
                </article>
            </div>
        </div>

        <div class="memory-status-footer">
            <div class="memory-status-meta">
                <span class="memory-status-label">保存日</span>
                <strong>{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d') }}</strong>
            </div>

            <div class="memory-status-footer-actions">
                <a class="btn btn-secondary" href="{{ route('memories.edit', $memory) }}">修正する</a>
                <form method="post" action="{{ route('memories.destroy', $memory) }}" onsubmit="return confirm('この記憶を削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-secondary btn-danger" type="submit">削除する</button>
                </form>
            </div>
        </div>
    </section>

    <style>
        .memory-status-panel {
            position: relative;
            min-height: min(920px, calc(100vh - 72px));
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 18px;
            padding: 28px;
            overflow: hidden;
            border-radius: 30px;
            background:
                radial-gradient(circle at 18% 18%, rgba(86, 132, 255, 0.18), transparent 20%),
                radial-gradient(circle at 82% 16%, rgba(126, 209, 255, 0.14), transparent 18%),
                radial-gradient(circle at 50% 72%, rgba(88, 108, 255, 0.12), transparent 26%),
                linear-gradient(160deg, #02040b 0%, #050916 48%, #0a1124 100%);
            color: rgba(238, 245, 255, 0.94);
            box-shadow: 0 30px 80px rgba(6, 10, 24, 0.36);
        }

        .memory-status-panel::before,
        .memory-status-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .memory-status-panel::before {
            width: 420px;
            height: 420px;
            left: -140px;
            top: -120px;
            background: radial-gradient(circle, rgba(91, 155, 255, 0.16), transparent 68%);
        }

        .memory-status-panel::after {
            width: 340px;
            height: 340px;
            right: -40px;
            bottom: -140px;
            background: radial-gradient(circle, rgba(120, 214, 255, 0.14), transparent 70%);
        }

        .memory-status-copy,
        .memory-status-stage,
        .memory-status-footer {
            position: relative;
            z-index: 1;
        }

        .memory-status-topline {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 18px;
            flex-wrap: wrap;
        }

        .memory-status-copy .eyebrow {
            padding: 8px 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(12, 21, 44, 0.92), rgba(27, 47, 88, 0.78));
            border: 1px solid rgba(166, 205, 255, 0.18);
            color: rgba(216, 234, 255, 0.92);
            letter-spacing: 0.16em;
            font-size: 11px;
            box-shadow: 0 10px 26px rgba(8, 14, 30, 0.28);
            backdrop-filter: blur(12px);
        }

        .memory-status-copy h1 {
            margin: 16px 0 0;
            font-size: clamp(30px, 3.4vw, 50px);
            letter-spacing: 0.04em;
            color: rgba(247, 250, 255, 0.96);
        }

        .memory-status-actions,
        .memory-status-footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .memory-status-panel .btn {
            border-radius: 12px;
            border: 1px solid rgba(166, 204, 255, 0.16);
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.28);
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .memory-status-panel .btn:hover {
            transform: translateY(-1px);
            border-color: rgba(196, 224, 255, 0.34);
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
            color: rgba(250, 252, 255, 0.98);
            box-shadow: 0 14px 28px rgba(18, 36, 78, 0.32);
        }

        .memory-status-stage {
            display: grid;
            align-content: center;
            justify-items: center;
            gap: 20px;
            min-height: 0;
        }

        .memory-focus-shell {
            position: relative;
            width: min(46vw, 460px);
            aspect-ratio: 1 / 1;
            display: grid;
            place-items: center;
        }

        .memory-focus-orbit {
            position: absolute;
            inset: 7%;
            border-radius: 50%;
            background: rgba(143, 201, 255, 0.08);
            filter: blur(30px);
            animation: focusShellPulse 8.4s ease-in-out infinite;
        }

        .memory-focus-orbit-back {
            transform: translate(28px, -18px) scale(0.9);
            opacity: 0.44;
        }

        .memory-focus-orbit-front {
            transform: translate(-16px, 18px) scale(0.82);
            opacity: 0.24;
            animation-duration: 9.2s;
            animation-delay: -1.6s;
        }

        .memory-focus-bubble {
            position: relative;
            width: min(34vw, 350px);
            aspect-ratio: 1 / 1;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background:
                radial-gradient(circle at 30% 28%, rgba(255, 255, 255, 0.82), transparent 22%),
                radial-gradient(circle at 36% 34%, color-mix(in srgb, var(--bubble-start) 82%, white 18%) 0%, color-mix(in srgb, var(--bubble-start) 52%, transparent 48%) 42%, color-mix(in srgb, var(--bubble-end) 76%, transparent 24%) 100%);
            box-shadow:
                inset -24px -34px 84px rgba(13, 22, 48, 0.34),
                inset 22px 28px 72px rgba(255, 255, 255, 0.16),
                0 0 120px color-mix(in srgb, var(--bubble-end) 34%, transparent 66%);
            animation: focusBubblePulse 6.8s ease-in-out infinite;
        }

        .memory-focus-bubble::before,
        .memory-focus-bubble::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .memory-focus-bubble::before {
            inset: 3%;
            border: 1px solid rgba(235, 244, 255, 0.34);
            filter: blur(2px);
        }

        .memory-focus-bubble::after {
            width: 30%;
            height: 16%;
            top: 14%;
            left: 18%;
            background: rgba(255, 255, 255, 0.22);
            filter: blur(12px);
            transform: rotate(-18deg);
        }

        .memory-focus-aura {
            position: absolute;
            inset: -7%;
            border-radius: 50%;
            background: radial-gradient(circle, color-mix(in srgb, var(--bubble-end) 24%, transparent 76%) 0%, transparent 62%);
            filter: blur(40px);
            opacity: 0.88;
        }

        .memory-focus-core {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 8px;
            width: min(76%, 260px);
            text-align: center;
        }

        .memory-focus-period,
        .memory-focus-emotion {
            color: rgba(242, 247, 255, 0.82);
            font-size: clamp(12px, 1.2vw, 16px);
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .memory-focus-core strong {
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.14;
            color: rgba(250, 252, 255, 0.98);
            text-shadow: 0 12px 34px rgba(6, 10, 24, 0.32);
        }

        .memory-status-info-strip {
            width: 100%;
            display: grid;
            grid-template-columns: 1.15fr 1.45fr 0.75fr 0.75fr;
            gap: 14px;
            align-items: stretch;
        }

        .memory-status-card {
            min-height: 100%;
            padding: 18px 18px 16px;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(10, 18, 34, 0.92), rgba(7, 13, 27, 0.94));
            border: 1px solid rgba(155, 198, 255, 0.12);
            box-shadow: 0 18px 44px rgba(2, 4, 12, 0.34);
            backdrop-filter: blur(14px);
        }

        .memory-status-label {
            display: block;
            margin-bottom: 10px;
            color: rgba(170, 200, 242, 0.62);
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .memory-status-card h2 {
            margin: 0;
            font-size: 24px;
            line-height: 1.28;
            color: rgba(248, 251, 255, 0.98);
        }

        .memory-status-card p {
            margin: 0;
            color: rgba(212, 223, 244, 0.82);
            line-height: 1.8;
            max-height: 7.2em;
            overflow: auto;
        }

        .memory-status-chip {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 12px;
            background: rgba(122, 167, 230, 0.14);
            border: 1px solid rgba(177, 212, 255, 0.14);
            color: rgba(244, 248, 255, 0.96);
            font-weight: 700;
        }

        .memory-status-emotion {
            display: grid;
            gap: 8px;
            justify-items: start;
        }

        .memory-status-emotion .badge {
            padding: 10px 14px;
            border-radius: 12px;
            color: #111827;
        }

        .memory-status-emotion small,
        .memory-status-meta strong {
            color: rgba(188, 214, 255, 0.78);
            font-size: 13px;
            letter-spacing: 0.08em;
        }

        .memory-status-footer {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
            flex-wrap: wrap;
        }

        .memory-status-meta {
            display: grid;
            gap: 6px;
        }

        .memory-status-footer form {
            margin: 0;
        }

        @keyframes focusBubblePulse {
            0% { transform: scale(0.98); }
            50% { transform: scale(1.02); }
            100% { transform: scale(0.98); }
        }

        @keyframes focusShellPulse {
            0% { transform: scale(0.98); opacity: 0.72; }
            50% { transform: scale(1.02); opacity: 1; }
            100% { transform: scale(0.98); opacity: 0.72; }
        }

        @media (max-width: 980px) {
            .memory-status-panel {
                min-height: auto;
            }

            .memory-status-info-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .memory-focus-shell {
                width: min(58vw, 420px);
            }

            .memory-focus-bubble {
                width: min(42vw, 320px);
            }
        }

        @media (max-width: 760px) {
            .memory-status-panel {
                padding: 18px;
                border-radius: 24px;
            }

            .memory-status-copy h1 {
                font-size: 34px;
            }

            .memory-status-info-strip {
                grid-template-columns: 1fr;
            }

            .memory-focus-shell {
                width: min(82vw, 360px);
            }

            .memory-focus-bubble {
                width: min(62vw, 280px);
            }
        }
    </style>
@endsection
