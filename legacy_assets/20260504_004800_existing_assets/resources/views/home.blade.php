@extends('layouts.app')

@section('title', 'ホーム | 分身AI MVP')
@section('body_class', 'body-home')
@section('page_class', 'page-home')

@section('content')
    @php
        $orbitBubbles = [
            ['size' => 132, 'top' => '10%', 'right' => '20%', 'delay' => '-3s', 'tone' => 'blue'],
            ['size' => 88, 'top' => '18%', 'right' => '7%', 'delay' => '-7s', 'tone' => 'violet'],
            ['size' => 164, 'top' => '31%', 'right' => '30%', 'delay' => '-5s', 'tone' => 'cyan'],
            ['size' => 108, 'top' => '46%', 'right' => '12%', 'delay' => '-11s', 'tone' => 'ice'],
            ['size' => 146, 'top' => '58%', 'right' => '26%', 'delay' => '-9s', 'tone' => 'violet'],
            ['size' => 94, 'top' => '74%', 'right' => '8%', 'delay' => '-4s', 'tone' => 'blue'],
        ];
        $actions = [
            ['label' => '記憶を追加する', 'href' => route('memories.create'), 'class' => 'is-primary'],
            ['label' => '記憶を見る', 'href' => route('memories.index'), 'class' => 'is-amber'],
            ['label' => '記憶と話す（ダミー）', 'href' => null, 'class' => 'is-cyan is-disabled'],
            ['label' => '友だちと共有する（ダミー）', 'href' => null, 'class' => 'is-violet is-disabled'],
        ];
    @endphp

    <section class="home-reframe">
        <div class="home-reframe-decor" aria-hidden="true">
            <span class="home-glow glow-a"></span>
            <span class="home-glow glow-b"></span>
            <span class="home-glow glow-c"></span>
            <span class="home-grid"></span>
            <span class="home-arc arc-a"></span>
            <span class="home-arc arc-b"></span>
        </div>

        <div class="home-reframe-layout">
            <div class="home-copy-column">
                <span class="home-kicker">BUNSHIN AI</span>
                <h1>記憶分身AI</h1>
                <p>
                    記憶をひとつずつ光る粒として保存し、
                    いつでも眺めて、掘って、未来の分身へつないでいく。
                </p>

                <div class="home-primary-bubble-wrap" aria-hidden="true">
                    <span class="home-primary-orbit orbit-one"></span>
                    <span class="home-primary-orbit orbit-two"></span>
                    <span class="home-primary-orbit orbit-three"></span>

                    <div class="home-primary-bubble">
                        <span class="home-primary-bubble-core"></span>
                        <span class="home-primary-bubble-halo"></span>
                    </div>
                </div>
            </div>

            <div class="home-action-column">
                <div class="home-action-stack">
                    @foreach ($actions as $action)
                        @if ($action['href'])
                            <a class="home-liquid-btn {{ $action['class'] }}" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                        @else
                            <span class="home-liquid-btn {{ $action['class'] }}" aria-disabled="true">{{ $action['label'] }}</span>
                        @endif
                    @endforeach
                </div>

                <div class="home-orbit-field" aria-hidden="true">
                    @foreach ($orbitBubbles as $bubble)
                        <span
                            class="home-orbit-bubble tone-{{ $bubble['tone'] }}"
                            style="
                                --bubble-size: {{ $bubble['size'] }}px;
                                --bubble-top: {{ $bubble['top'] }};
                                --bubble-right: {{ $bubble['right'] }};
                                --bubble-delay: {{ $bubble['delay'] }};
                            "
                        ></span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <style>
        .body-home {
            background: #000;
        }

        .home-reframe {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            padding: 52px 56px;
            background:
                radial-gradient(circle at 15% 18%, rgba(91, 132, 255, 0.18), transparent 22%),
                radial-gradient(circle at 82% 16%, rgba(94, 218, 255, 0.14), transparent 18%),
                radial-gradient(circle at 62% 80%, rgba(184, 78, 255, 0.12), transparent 20%),
                linear-gradient(180deg, #02040a 0%, #050916 38%, #070d1a 100%);
            color: rgba(244, 248, 255, 0.96);
        }

        .home-reframe::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at center, rgba(255, 255, 255, 0.05) 0.7px, transparent 1px);
            background-size: 12px 12px;
            opacity: 0.24;
        }

        .home-reframe-decor {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .home-glow,
        .home-grid,
        .home-arc {
            position: absolute;
        }

        .home-glow {
            border-radius: 50%;
            filter: blur(18px);
        }

        .home-glow.glow-a {
            width: 320px;
            height: 320px;
            left: -120px;
            top: 24%;
            background: radial-gradient(circle, rgba(83, 142, 255, 0.24), transparent 70%);
        }

        .home-glow.glow-b {
            width: 280px;
            height: 280px;
            right: -80px;
            top: 18%;
            background: radial-gradient(circle, rgba(93, 220, 255, 0.18), transparent 70%);
        }

        .home-glow.glow-c {
            width: 260px;
            height: 260px;
            right: 18%;
            bottom: -120px;
            background: radial-gradient(circle, rgba(149, 97, 255, 0.18), transparent 72%);
        }

        .home-grid {
            inset: 0;
            background-image:
                linear-gradient(rgba(110, 149, 214, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(110, 149, 214, 0.05) 1px, transparent 1px);
            background-size: 88px 88px;
            opacity: 0.16;
            mask-image: radial-gradient(circle at center, rgba(0, 0, 0, 0.88), transparent 92%);
        }

        .home-arc {
            border-radius: 50%;
            border: 1px solid rgba(133, 185, 255, 0.1);
        }

        .home-arc.arc-a {
            width: min(56vw, 760px);
            aspect-ratio: 1 / 1;
            left: -18%;
            top: 12%;
        }

        .home-arc.arc-b {
            width: min(64vw, 920px);
            aspect-ratio: 1 / 1;
            right: -20%;
            top: 4%;
        }

        .home-reframe-layout {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
            gap: 28px;
            min-height: calc(100vh - 104px);
            align-items: center;
        }

        .home-copy-column,
        .home-action-column {
            min-width: 0;
        }

        .home-copy-column {
            display: grid;
            gap: 18px;
            align-content: center;
        }

        .home-kicker {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            min-height: 38px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid rgba(134, 178, 255, 0.14);
            background:
                linear-gradient(180deg, rgba(17, 25, 44, 0.92), rgba(12, 18, 33, 0.92));
            color: rgba(204, 226, 255, 0.86);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.22em;
        }

        .home-copy-column h1 {
            margin: 0;
            font-size: clamp(60px, 8.2vw, 120px);
            line-height: 0.94;
            letter-spacing: 0.02em;
            color: rgba(248, 251, 255, 0.99);
            text-shadow: 0 16px 40px rgba(6, 10, 24, 0.34);
        }

        .home-copy-column p {
            margin: 0;
            max-width: 520px;
            color: rgba(190, 210, 241, 0.82);
            font-size: 18px;
            line-height: 1.9;
        }

        .home-primary-bubble-wrap {
            position: relative;
            width: min(42vw, 520px);
            aspect-ratio: 1 / 1;
            margin-top: 8px;
        }

        .home-primary-orbit,
        .home-primary-bubble,
        .home-primary-bubble-core,
        .home-primary-bubble-halo {
            position: absolute;
            border-radius: 50%;
        }

        .home-primary-orbit {
            border: 1px solid rgba(121, 186, 255, 0.14);
        }

        .home-primary-orbit.orbit-one {
            inset: 5%;
            transform: rotate(12deg);
        }

        .home-primary-orbit.orbit-two {
            inset: 13%;
            opacity: 0.72;
            transform: rotate(58deg) scaleX(1.06);
        }

        .home-primary-orbit.orbit-three {
            inset: 18%;
            opacity: 0.56;
            transform: rotate(-24deg) scaleY(0.86);
        }

        .home-primary-bubble {
            inset: 20%;
            background:
                radial-gradient(circle at 34% 26%, rgba(255, 255, 255, 0.88), transparent 18%),
                radial-gradient(circle at 68% 64%, rgba(84, 234, 255, 0.3), transparent 34%),
                radial-gradient(circle at 50% 50%, rgba(73, 114, 255, 0.2), rgba(12, 18, 38, 0.1) 62%, rgba(255, 255, 255, 0.06) 100%);
            border: 1px solid rgba(203, 229, 255, 0.26);
            box-shadow:
                inset -24px -34px 70px rgba(22, 46, 125, 0.34),
                inset 26px 22px 52px rgba(255, 255, 255, 0.14),
                0 0 80px rgba(87, 162, 255, 0.26);
            animation: homeBubblePulse 8s ease-in-out infinite;
        }

        .home-primary-bubble::before {
            content: "";
            position: absolute;
            width: 26%;
            height: 18%;
            left: 16%;
            top: 12%;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            filter: blur(14px);
            transform: rotate(-18deg);
        }

        .home-primary-bubble-core {
            inset: 26%;
            background:
                radial-gradient(circle at 42% 38%, rgba(123, 228, 255, 0.9), rgba(88, 156, 255, 0.78) 44%, rgba(61, 77, 255, 0.58) 72%, transparent 100%);
            mix-blend-mode: screen;
            opacity: 0.88;
            filter: blur(2px);
        }

        .home-primary-bubble-halo {
            inset: -14%;
            background: radial-gradient(circle, rgba(88, 162, 255, 0.26), transparent 62%);
            filter: blur(34px);
            opacity: 0.9;
        }

        .home-action-column {
            position: relative;
            min-height: 100%;
            display: grid;
            align-content: center;
            justify-items: end;
        }

        .home-action-stack {
            position: relative;
            z-index: 2;
            display: grid;
            gap: 16px;
            width: min(420px, 100%);
        }

        .home-liquid-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 76px;
            padding: 0 30px;
            width: 100%;
            border-radius: 999px;
            color: rgba(249, 251, 255, 0.98);
            font-size: 24px;
            font-weight: 500;
            letter-spacing: 0.01em;
            background:
                linear-gradient(180deg, rgba(15, 18, 28, 0.96), rgba(10, 12, 20, 0.98));
            border: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow:
                inset 0 2px 0 rgba(255, 255, 255, 0.18),
                inset 0 -2px 0 rgba(0, 0, 0, 0.32),
                0 22px 38px rgba(0, 0, 0, 0.28);
            text-shadow: 0 2px 18px rgba(0, 0, 0, 0.36);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            backdrop-filter: blur(18px);
        }

        .home-liquid-btn::before,
        .home-liquid-btn::after {
            content: "";
            position: absolute;
            pointer-events: none;
        }

        .home-liquid-btn::before {
            inset: 1px;
            border-radius: inherit;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.02) 34%, rgba(255, 255, 255, 0.01)),
                linear-gradient(135deg, rgba(255, 255, 255, 0.08), transparent 34%);
            mix-blend-mode: screen;
        }

        .home-liquid-btn::after {
            width: 30%;
            height: 32%;
            top: 8%;
            right: 8%;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.22);
            filter: blur(14px);
            transform: rotate(-8deg);
        }

        .home-liquid-btn:hover {
            transform: translateY(-2px) scale(1.01);
            border-color: rgba(255, 255, 255, 0.34);
            box-shadow:
                inset 0 2px 0 rgba(255, 255, 255, 0.2),
                inset 0 -2px 0 rgba(0, 0, 0, 0.36),
                0 26px 46px rgba(0, 0, 0, 0.34);
        }

        .home-liquid-btn.is-primary {
            box-shadow:
                inset 0 2px 0 rgba(255, 255, 255, 0.18),
                inset 0 -2px 0 rgba(9, 16, 45, 0.4),
                0 0 0 1px rgba(123, 184, 255, 0.18),
                0 0 44px rgba(77, 126, 255, 0.34),
                0 20px 38px rgba(10, 18, 48, 0.38);
        }

        .home-liquid-btn.is-primary::before {
            background:
                linear-gradient(180deg, rgba(124, 196, 255, 0.3), rgba(83, 116, 255, 0.18) 42%, rgba(255, 255, 255, 0.04)),
                linear-gradient(90deg, rgba(77, 126, 255, 0.22), rgba(64, 213, 255, 0.12));
        }

        .home-liquid-btn.is-amber {
            box-shadow:
                inset 0 2px 0 rgba(255, 255, 255, 0.18),
                inset 0 -2px 0 rgba(52, 28, 9, 0.42),
                0 0 0 1px rgba(255, 183, 120, 0.18),
                0 0 44px rgba(255, 124, 78, 0.28),
                0 20px 38px rgba(48, 24, 10, 0.34);
        }

        .home-liquid-btn.is-amber::before {
            background:
                linear-gradient(180deg, rgba(255, 172, 124, 0.28), rgba(255, 111, 76, 0.18) 42%, rgba(255, 255, 255, 0.04)),
                linear-gradient(90deg, rgba(255, 133, 86, 0.22), rgba(255, 210, 120, 0.1));
        }

        .home-liquid-btn.is-cyan {
            box-shadow:
                inset 0 2px 0 rgba(255, 255, 255, 0.18),
                inset 0 -2px 0 rgba(10, 34, 52, 0.42),
                0 0 0 1px rgba(120, 214, 255, 0.18),
                0 0 40px rgba(66, 204, 255, 0.24),
                0 18px 32px rgba(10, 30, 44, 0.3);
        }

        .home-liquid-btn.is-cyan::before {
            background:
                linear-gradient(180deg, rgba(108, 228, 255, 0.24), rgba(74, 158, 255, 0.14) 42%, rgba(255, 255, 255, 0.04)),
                linear-gradient(90deg, rgba(82, 202, 255, 0.2), rgba(125, 239, 255, 0.08));
        }

        .home-liquid-btn.is-violet {
            box-shadow:
                inset 0 2px 0 rgba(255, 255, 255, 0.18),
                inset 0 -2px 0 rgba(29, 13, 52, 0.42),
                0 0 0 1px rgba(191, 125, 255, 0.18),
                0 0 40px rgba(184, 84, 255, 0.26),
                0 18px 32px rgba(29, 14, 42, 0.32);
        }

        .home-liquid-btn.is-violet::before {
            background:
                linear-gradient(180deg, rgba(208, 120, 255, 0.26), rgba(106, 90, 255, 0.16) 42%, rgba(255, 255, 255, 0.04)),
                linear-gradient(90deg, rgba(182, 82, 255, 0.22), rgba(118, 123, 255, 0.1));
        }

        .home-liquid-btn.is-disabled {
            opacity: 0.6;
            cursor: default;
        }

        .home-liquid-btn.is-disabled:hover {
            transform: none;
        }

        .home-orbit-field {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .home-orbit-bubble {
            position: absolute;
            top: var(--bubble-top);
            right: var(--bubble-right);
            width: var(--bubble-size);
            height: var(--bubble-size);
            border-radius: 50%;
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.86), transparent 16%),
                radial-gradient(circle at 62% 62%, rgba(72, 186, 255, 0.22), transparent 38%),
                radial-gradient(circle at 50% 50%, rgba(36, 56, 122, 0.32), rgba(10, 18, 33, 0.08) 70%, rgba(255, 255, 255, 0.04) 100%);
            border: 1px solid rgba(211, 230, 255, 0.22);
            box-shadow:
                inset -18px -20px 34px rgba(20, 30, 88, 0.28),
                inset 18px 14px 28px rgba(255, 255, 255, 0.12),
                0 0 46px rgba(87, 162, 255, 0.14);
            animation: orbitFloat 16s ease-in-out infinite;
            animation-delay: var(--bubble-delay);
        }

        .home-orbit-bubble::before {
            content: "";
            position: absolute;
            inset: 9%;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .home-orbit-bubble.tone-blue {
            box-shadow:
                inset -18px -20px 34px rgba(20, 30, 88, 0.32),
                inset 18px 14px 28px rgba(255, 255, 255, 0.12),
                0 0 48px rgba(84, 144, 255, 0.18);
        }

        .home-orbit-bubble.tone-cyan {
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.86), transparent 16%),
                radial-gradient(circle at 58% 56%, rgba(92, 244, 255, 0.28), transparent 38%),
                radial-gradient(circle at 50% 50%, rgba(36, 92, 122, 0.28), rgba(10, 18, 33, 0.08) 70%, rgba(255, 255, 255, 0.04) 100%);
        }

        .home-orbit-bubble.tone-violet {
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.86), transparent 16%),
                radial-gradient(circle at 58% 56%, rgba(205, 120, 255, 0.24), transparent 38%),
                radial-gradient(circle at 50% 50%, rgba(48, 34, 112, 0.3), rgba(10, 18, 33, 0.08) 70%, rgba(255, 255, 255, 0.04) 100%);
        }

        .home-orbit-bubble.tone-ice {
            background:
                radial-gradient(circle at 30% 24%, rgba(255, 255, 255, 0.88), transparent 16%),
                radial-gradient(circle at 58% 56%, rgba(175, 230, 255, 0.24), transparent 38%),
                radial-gradient(circle at 50% 50%, rgba(56, 92, 144, 0.24), rgba(10, 18, 33, 0.08) 70%, rgba(255, 255, 255, 0.04) 100%);
        }

        @media (max-width: 980px) {
            .home-reframe {
                padding: 36px 22px;
            }

            .home-reframe-layout {
                grid-template-columns: 1fr;
                gap: 24px;
                min-height: auto;
            }

            .home-copy-column {
                justify-items: start;
            }

            .home-primary-bubble-wrap {
                width: min(80vw, 420px);
            }

            .home-action-column {
                justify-items: stretch;
            }

            .home-action-stack {
                width: 100%;
            }

            .home-liquid-btn {
                min-height: 68px;
                font-size: 20px;
            }

            .home-orbit-field {
                position: relative;
                height: 220px;
                margin-top: 14px;
            }
        }

        @media (max-width: 640px) {
            .home-copy-column h1 {
                font-size: 56px;
            }

            .home-copy-column p {
                font-size: 16px;
            }

            .home-liquid-btn {
                min-height: 60px;
                padding: 0 20px;
                font-size: 18px;
            }

            .home-primary-bubble-wrap {
                width: min(88vw, 360px);
            }
        }

        @keyframes homeBubblePulse {
            0%, 100% { transform: scale(0.985); }
            50% { transform: scale(1.015); }
        }

        @keyframes orbitFloat {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            25% { transform: translate3d(10px, -12px, 0) scale(1.02); }
            50% { transform: translate3d(-6px, -16px, 0) scale(0.985); }
            75% { transform: translate3d(-10px, -6px, 0) scale(1.01); }
        }
    </style>
@endsection
