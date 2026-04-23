@extends('layouts.app')

@section('title', '記憶ステータス | 分身AI MVP')

@php
    $badgeClass = str_contains($tone, 'ポジティブ') ? 'badge-positive' : (str_contains($tone, 'ニュートラル') ? 'badge-neutral' : 'badge-negative');
@endphp

@section('content')
    <section class="memory-status-panel">
        <div class="memory-status-decor" aria-hidden="true">
            <span class="memory-status-star s1"></span>
            <span class="memory-status-star s2"></span>
            <span class="memory-status-star s3 cross"></span>
            <span class="memory-status-star s4"></span>
            <span class="memory-status-star s5"></span>
            <span class="memory-status-star s6 cross"></span>
            <span class="memory-status-star s7"></span>
            <span class="memory-status-star s8"></span>
            <span class="memory-status-orb orb-a"></span>
            <span class="memory-status-orb orb-b"></span>
            <span class="memory-status-orb orb-c"></span>
        </div>
        <div class="memory-status-sheen" aria-hidden="true"></div>

        <div class="memory-status-copy">
            <div class="memory-status-topline">
                <div class="memory-status-heading">
                    <h1>記憶ステータス</h1>
                </div>
                <div class="memory-status-actions">
                    <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">記憶玉へ戻る</a>
                    <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧を見る</a>
                    <a class="btn btn-secondary" href="{{ route('memories.edit', $memory) }}">修正する</a>
                    <form method="post" action="{{ route('memories.destroy', $memory) }}" onsubmit="return confirm('この記憶を削除しますか？');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-secondary btn-danger" type="submit">削除する</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="memory-status-stage">
            <div class="memory-status-stage-grid">
                <div class="memory-focus-chamber">
                    <div class="memory-focus-chamber-head">
                        <span class="memory-status-label">Memory Core</span>
                        <div class="memory-focus-meta-chips">
                            <span class="memory-focus-mini">{{ $memory->period }}</span>
                            <span class="memory-focus-mini">{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d') }}</span>
                            <span class="memory-focus-mini">{{ $tone }}</span>
                        </div>
                    </div>

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

                </div>

                <div class="memory-status-info-strip">
                    <article class="memory-status-card memory-status-card-theme">
                        <span class="memory-status-label">テーマ</span>
                        <h2>{{ $theme }}</h2>
                    </article>

                    <article class="memory-status-card memory-status-card-content">
                        <span class="memory-status-label">内容</span>
                        <p>{{ $memory->content }}</p>
                    </article>

                    <article class="memory-status-card memory-status-card-summary">
                        <span class="memory-status-label">ステータス</span>
                        <div class="memory-status-summary-grid">
                            <div class="memory-status-summary-item">
                                <span>ライフステージ</span>
                                <strong>{{ $memory->period }}</strong>
                            </div>
                            <div class="memory-status-summary-item memory-status-summary-emotion">
                                <span>感情</span>
                                <div class="memory-status-emotion">
                                    <span class="badge {{ $badgeClass }}">{{ $memory->emotion }}</span>
                                    <small>{{ $tone }}</small>
                                </div>
                            </div>
                            <div class="memory-status-summary-item memory-status-summary-date">
                                <span>保存日</span>
                                <strong>{{ $memory->created_at->timezone('Asia/Tokyo')->format('Y.m.d') }}</strong>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>

    </section>

    <style>
        .memory-status-panel {
            position: relative;
            min-height: min(720px, calc(100vh - 64px));
            max-height: calc(100vh - 64px);
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 14px;
            padding: 20px;
            overflow: hidden;
            border-radius: 32px;
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

        .memory-status-decor {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            border-radius: inherit;
        }

        .memory-status-sheen {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.06) 8%, rgba(255,255,255,0.015) 16%, transparent 24%),
                linear-gradient(116deg, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0.08) 10%, rgba(255,255,255,0.02) 18%, transparent 24%);
            clip-path: polygon(0 0, 100% 0, 100% 11%, 76% 16%, 61% 40%, 36% 40%, 18% 15%, 0 11%);
            mix-blend-mode: screen;
            opacity: 0.44;
            z-index: 0;
        }

        .memory-status-star {
            position: absolute;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: rgba(255,255,255,0.92);
            box-shadow: 0 0 12px rgba(255,255,255,0.38);
            animation: memoryStatusTwinkle 6.2s ease-in-out infinite;
        }

        .memory-status-star.s1 { top: 10%; left: 18%; }
        .memory-status-star.s2 { top: 16%; right: 24%; width: 3px; height: 3px; animation-delay: 1s; }
        .memory-status-star.s3 { top: 32%; left: 8%; width: 12px; height: 12px; background: transparent; box-shadow: none; animation-delay: 2.2s; }
        .memory-status-star.s4 { right: 10%; top: 42%; width: 3px; height: 3px; animation-delay: .8s; }
        .memory-status-star.s5 { left: 22%; bottom: 18%; animation-delay: 1.8s; }
        .memory-status-star.s6 { right: 18%; bottom: 22%; width: 12px; height: 12px; background: transparent; box-shadow: none; animation-delay: 2.6s; }
        .memory-status-star.s7 { left: 44%; top: 14%; width: 3px; height: 3px; animation-delay: 1.4s; }
        .memory-status-star.s8 { right: 36%; bottom: 10%; width: 3px; height: 3px; animation-delay: 3.1s; }

        .memory-status-star.cross::before,
        .memory-status-star.cross::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255,255,255,0.92);
            border-radius: 999px;
            box-shadow: 0 0 12px rgba(255,255,255,0.28);
        }

        .memory-status-star.cross::before {
            width: 12px;
            height: 2px;
        }

        .memory-status-star.cross::after {
            width: 2px;
            height: 12px;
        }

        .memory-status-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(0.4px);
        }

        .memory-status-orb.orb-a {
            width: 132px;
            height: 132px;
            left: 4%;
            top: 34%;
            background:
                radial-gradient(circle at 34% 28%, rgba(255,255,255,0.44), transparent 20%),
                radial-gradient(circle at 58% 58%, rgba(182, 168, 255, 0.72), rgba(109, 96, 255, 0.18) 64%, transparent 78%);
            opacity: 0.7;
        }

        .memory-status-orb.orb-b {
            width: 108px;
            height: 108px;
            right: 6%;
            top: 22%;
            background:
                radial-gradient(circle at 34% 28%, rgba(255,255,255,0.36), transparent 20%),
                radial-gradient(circle at 58% 58%, rgba(255, 199, 148, 0.68), rgba(238, 144, 89, 0.16) 64%, transparent 78%);
            opacity: 0.62;
        }

        .memory-status-orb.orb-c {
            width: 88px;
            height: 88px;
            right: 18%;
            bottom: 12%;
            background:
                radial-gradient(circle at 34% 28%, rgba(255,255,255,0.36), transparent 20%),
                radial-gradient(circle at 58% 58%, rgba(164, 226, 255, 0.62), rgba(103, 167, 255, 0.14) 64%, transparent 78%);
            opacity: 0.56;
        }

        .memory-status-copy,
        .memory-status-stage {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

        .memory-status-topline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .memory-status-copy {
            padding-bottom: 0;
        }

        .memory-status-heading {
            display: grid;
            max-width: 640px;
        }

        .memory-status-copy h1 {
            margin: 0;
            font-size: clamp(28px, 3vw, 40px);
            letter-spacing: 0.04em;
            color: rgba(247, 250, 255, 0.96);
            line-height: 1;
        }

        .memory-status-actions,
        .memory-status-footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .memory-status-panel .btn {
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid rgba(166, 204, 255, 0.14);
            background: linear-gradient(135deg, rgba(29, 40, 70, 0.88), rgba(15, 24, 44, 0.92));
            color: rgba(232, 241, 255, 0.92);
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(6, 10, 24, 0.22);
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .memory-status-panel .btn:hover {
            transform: translateY(-1px);
            border-color: rgba(196, 224, 255, 0.34);
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
            color: rgba(250, 252, 255, 0.98);
            box-shadow: 0 12px 24px rgba(18, 36, 78, 0.28);
        }

        .memory-status-stage {
            display: block;
            min-height: 0;
            overflow: hidden;
        }

        .memory-status-stage-grid {
            width: 100%;
            display: grid;
            grid-template-columns: minmax(390px, 1.08fr) minmax(280px, 0.78fr);
            gap: 14px;
            align-items: stretch;
            min-height: 0;
            height: 100%;
        }

        .memory-focus-chamber {
            position: relative;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 8px;
            padding: 16px 16px 14px;
            border-radius: 28px;
            border: 1px solid rgba(168, 199, 255, 0.12);
            background:
                linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04) 18%, rgba(255,255,255,0.012) 34%, transparent 46%),
                linear-gradient(135deg, rgba(12, 18, 35, 0.96), rgba(8, 14, 27, 0.96));
            box-shadow:
                0 26px 58px rgba(2, 4, 12, 0.36),
                inset 0 1px 0 rgba(255,255,255,0.16);
            overflow: hidden;
            min-height: 0;
            height: 100%;
        }

        .memory-focus-chamber::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.28), rgba(255,255,255,0.09) 8%, rgba(255,255,255,0.02) 16%, transparent 26%),
                linear-gradient(120deg, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0.08) 12%, rgba(255,255,255,0.016) 22%, transparent 30%);
            clip-path: polygon(0 0, 100% 0, 100% 11%, 72% 16%, 54% 42%, 30% 42%, 14% 14%, 0 10%);
            mix-blend-mode: screen;
            opacity: 0.72;
            pointer-events: none;
        }

        .memory-focus-chamber::after {
            content: "";
            position: absolute;
            top: 8px;
            left: 8%;
            width: 66%;
            height: 22%;
            border-radius: 999px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05) 46%, transparent 100%);
            filter: blur(20px);
            transform: rotate(-11deg);
            opacity: 0.48;
            pointer-events: none;
        }

        .memory-focus-chamber-head {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 14px;
            justify-items: center;
        }

        .memory-focus-meta-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        .memory-focus-mini {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 11px;
            border-radius: 999px;
            border: 1px solid rgba(176, 206, 255, 0.14);
            background: rgba(255,255,255,0.05);
            color: rgba(239,245,255,0.86);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .memory-focus-shell {
            position: relative;
            width: min(100%, 470px);
            aspect-ratio: 1 / 1;
            display: grid;
            place-items: center;
            margin: 0 auto;
        }

        .memory-focus-orbit {
            position: absolute;
            inset: 6%;
            border-radius: 50%;
            background: rgba(143, 201, 255, 0.05);
            filter: blur(42px);
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
            width: min(76%, 360px);
            aspect-ratio: 1 / 1;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background:
                radial-gradient(circle at 30% 28%, rgba(255, 255, 255, 0.78), transparent 18%),
                radial-gradient(circle at 24% 22%, rgba(255, 255, 255, 0.26), transparent 30%),
                radial-gradient(circle at 52% 56%, color-mix(in srgb, var(--bubble-start) 58%, white 42%) 0%, color-mix(in srgb, var(--bubble-start) 28%, transparent 72%) 38%, color-mix(in srgb, var(--bubble-end) 52%, transparent 48%) 72%, rgba(255,255,255,0.06) 100%);
            box-shadow:
                inset -30px -38px 90px rgba(13, 22, 48, 0.18),
                inset 30px 34px 76px rgba(255, 255, 255, 0.14),
                0 0 90px color-mix(in srgb, var(--bubble-end) 24%, transparent 76%),
                0 0 40px color-mix(in srgb, var(--bubble-start) 18%, transparent 82%);
            filter: saturate(1.06);
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
            inset: 2%;
            border: 1px solid rgba(235, 244, 255, 0.18);
            filter: blur(8px);
        }

        .memory-focus-bubble::after {
            width: 38%;
            height: 20%;
            top: 11%;
            left: 16%;
            background: rgba(255, 255, 255, 0.18);
            filter: blur(20px);
            transform: rotate(-18deg);
        }

        .memory-focus-aura {
            position: absolute;
            inset: -10%;
            border-radius: 50%;
            background:
                radial-gradient(circle at 50% 54%, color-mix(in srgb, var(--bubble-end) 22%, transparent 78%) 0%, transparent 56%),
                radial-gradient(circle at 42% 44%, color-mix(in srgb, var(--bubble-start) 18%, transparent 82%) 0%, transparent 50%);
            filter: blur(52px);
            opacity: 0.92;
        }

        .memory-focus-core {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 7px;
            width: min(74%, 238px);
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
            font-size: clamp(22px, 2.4vw, 34px);
            line-height: 1.14;
            color: rgba(250, 252, 255, 0.98);
            text-shadow: 0 12px 34px rgba(6, 10, 24, 0.32);
        }

        .memory-status-info-strip {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: auto minmax(0, 1fr) auto;
            gap: 10px;
            align-items: stretch;
            align-content: stretch;
            min-height: 0;
            height: 100%;
            overflow: hidden;
            position: relative;
            border-radius: 24px;
            isolation: isolate;
        }

        .memory-status-info-strip::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.22), rgba(255,255,255,0.06) 10%, rgba(255,255,255,0.014) 18%, transparent 28%),
                linear-gradient(118deg, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0.05) 14%, rgba(255,255,255,0.012) 24%, transparent 32%);
            clip-path: polygon(0 0, 100% 0, 100% 12%, 74% 18%, 58% 44%, 30% 44%, 14% 16%, 0 10%);
            mix-blend-mode: screen;
            opacity: 0.58;
            pointer-events: none;
            z-index: 0;
        }

        .memory-status-info-strip::after {
            content: "";
            position: absolute;
            top: 10px;
            left: 8%;
            width: 64%;
            height: 20%;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(255,255,255,0.16), rgba(255,255,255,0.04) 54%, transparent 100%);
            filter: blur(22px);
            transform: rotate(-10deg);
            opacity: 0.34;
            pointer-events: none;
            z-index: 0;
        }

        .memory-status-card {
            min-height: 0;
            padding: 15px 16px 14px;
            border-radius: 20px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.035) 18%, rgba(255,255,255,0.01) 28%, transparent 36%),
                linear-gradient(180deg, rgba(10, 18, 34, 0.94), rgba(7, 13, 27, 0.96));
            border: 1px solid rgba(155, 198, 255, 0.12);
            box-shadow:
                0 18px 44px rgba(2, 4, 12, 0.34),
                inset 0 1px 0 rgba(255,255,255,0.12);
            backdrop-filter: blur(14px);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .memory-status-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05) 12%, rgba(255,255,255,0.014) 22%, transparent 30%),
                linear-gradient(118deg, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0.05) 12%, rgba(255,255,255,0.014) 24%, transparent 30%);
            clip-path: polygon(0 0, 100% 0, 100% 16%, 70% 24%, 52% 72%, 30% 72%, 14% 22%, 0 16%);
            mix-blend-mode: screen;
            opacity: 0.52;
            pointer-events: none;
        }

        .memory-status-card::after {
            content: "";
            position: absolute;
            top: 8px;
            left: 8%;
            width: 62%;
            height: 26%;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.05) 60%, transparent 100%);
            filter: blur(16px);
            transform: rotate(-12deg);
            opacity: 0.42;
            pointer-events: none;
        }

        .memory-status-label {
            display: block;
            margin-bottom: 7px;
            color: rgba(170, 200, 242, 0.62);
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .memory-status-card h2 {
            margin: 0;
            font-size: 20px;
            line-height: 1.22;
            color: rgba(248, 251, 255, 0.98);
        }

        .memory-status-card p {
            margin: 0;
            color: rgba(212, 223, 244, 0.82);
            line-height: 1.62;
            max-height: 8.2em;
            overflow: auto;
        }

        .memory-status-card-content p {
            min-height: 100%;
        }

        .memory-status-card-theme,
        .memory-status-card-content,
        .memory-status-card-summary {
            min-height: 0;
        }

        .memory-status-summary-grid {
            display: grid;
            gap: 8px;
        }

        .memory-status-summary-item {
            display: grid;
            gap: 6px;
            padding: 10px 12px;
            border-radius: 16px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.07), rgba(255,255,255,0.02)),
                rgba(114, 154, 220, 0.08);
            border: 1px solid rgba(177, 212, 255, 0.1);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
        }

        .memory-status-summary-item > span {
            color: rgba(183, 204, 239, 0.68);
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .memory-status-summary-item > strong {
            color: rgba(246, 249, 255, 0.96);
            font-size: 15px;
            letter-spacing: 0.04em;
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
            gap: 6px;
            justify-items: start;
        }

        .memory-status-emotion .badge {
            padding: 9px 13px;
            border-radius: 999px;
            color: #111827;
        }

        .memory-status-emotion small {
            color: rgba(188, 214, 255, 0.78);
            font-size: 13px;
            letter-spacing: 0.08em;
        }

        .memory-status-summary-date strong {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 14px;
            background: rgba(122, 167, 230, 0.1);
            border: 1px solid rgba(177, 212, 255, 0.1);
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

        @keyframes memoryStatusTwinkle {
            0%, 100% { opacity: 0.38; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.16); }
        }

        @media (max-width: 980px) {
            .memory-status-panel {
                min-height: auto;
                max-height: none;
                overflow: visible;
            }

            .memory-status-stage-grid {
                grid-template-columns: 1fr;
                height: auto;
            }

            .memory-focus-shell {
                width: min(58vw, 460px);
            }

            .memory-focus-bubble {
                width: min(62%, 340px);
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
