@extends('layouts.app')

@section('title', 'ホーム | 分身AI MVP')
@section('body_class', 'body-home')
@section('page_class', 'page-home')

@section('content')
    @php
        $memoryBubbles = [
            ['size' => 248, 'top' => '10%', 'left' => '8%', 'drift' => '18px', 'duration' => '18s', 'delay' => '-4s', 'rotate' => -4, 'tint' => '118, 196, 255', 'z' => 1, 'cores' => [['size' => 34, 'top' => '28%', 'left' => '30%', 'color' => '255, 122, 182'], ['size' => 18, 'top' => '57%', 'left' => '56%', 'color' => '120, 233, 255']]],
            ['size' => 132, 'top' => '8%', 'left' => '34%', 'drift' => '12px', 'duration' => '14s', 'delay' => '-7s', 'rotate' => 5, 'tint' => '176, 205, 255', 'z' => 3, 'cores' => [['size' => 16, 'top' => '38%', 'left' => '40%', 'color' => '255, 214, 104']]],
            ['size' => 198, 'top' => '24%', 'left' => '24%', 'drift' => '16px', 'duration' => '17s', 'delay' => '-11s', 'rotate' => 3, 'tint' => '126, 220, 255', 'z' => 2, 'cores' => [['size' => 24, 'top' => '34%', 'left' => '28%', 'color' => '137, 251, 192'], ['size' => 16, 'top' => '56%', 'left' => '54%', 'color' => '197, 140, 255']]],
            ['size' => 316, 'top' => '6%', 'left' => '48%', 'drift' => '20px', 'duration' => '20s', 'delay' => '-9s', 'rotate' => -5, 'tint' => '108, 187, 255', 'z' => 1, 'cores' => [['size' => 42, 'top' => '28%', 'left' => '32%', 'color' => '255, 124, 148'], ['size' => 22, 'top' => '54%', 'left' => '58%', 'color' => '255, 205, 102'], ['size' => 18, 'top' => '44%', 'left' => '46%', 'color' => '118, 234, 255']]],
            ['size' => 154, 'top' => '18%', 'left' => '72%', 'drift' => '13px', 'duration' => '15s', 'delay' => '-5s', 'rotate' => 4, 'tint' => '159, 214, 255', 'z' => 4, 'cores' => [['size' => 18, 'top' => '42%', 'left' => '42%', 'color' => '255, 145, 104']]],
            ['size' => 112, 'top' => '32%', 'left' => '4%', 'drift' => '10px', 'duration' => '13s', 'delay' => '-2s', 'rotate' => -3, 'tint' => '138, 201, 255', 'z' => 3, 'cores' => [['size' => 14, 'top' => '42%', 'left' => '40%', 'color' => '255, 108, 205']]],
            ['size' => 238, 'top' => '40%', 'left' => '16%', 'drift' => '17px', 'duration' => '18s', 'delay' => '-8s', 'rotate' => 5, 'tint' => '126, 193, 255', 'z' => 1, 'cores' => [['size' => 28, 'top' => '30%', 'left' => '31%', 'color' => '255, 154, 110'], ['size' => 18, 'top' => '56%', 'left' => '56%', 'color' => '138, 243, 186']]],
            ['size' => 142, 'top' => '48%', 'left' => '42%', 'drift' => '12px', 'duration' => '14s', 'delay' => '-10s', 'rotate' => -4, 'tint' => '187, 220, 255', 'z' => 4, 'cores' => [['size' => 16, 'top' => '40%', 'left' => '40%', 'color' => '132, 230, 255']]],
            ['size' => 286, 'top' => '44%', 'left' => '54%', 'drift' => '18px', 'duration' => '19s', 'delay' => '-6s', 'rotate' => 4, 'tint' => '116, 202, 255', 'z' => 2, 'cores' => [['size' => 36, 'top' => '28%', 'left' => '30%', 'color' => '202, 142, 255'], ['size' => 20, 'top' => '56%', 'left' => '57%', 'color' => '255, 211, 103']]],
            ['size' => 188, 'top' => '62%', 'left' => '34%', 'drift' => '14px', 'duration' => '16s', 'delay' => '-12s', 'rotate' => -5, 'tint' => '148, 214, 255', 'z' => 3, 'cores' => [['size' => 22, 'top' => '34%', 'left' => '34%', 'color' => '255, 120, 168'], ['size' => 14, 'top' => '56%', 'left' => '55%', 'color' => '116, 244, 205']]],
            ['size' => 128, 'top' => '70%', 'left' => '62%', 'drift' => '11px', 'duration' => '13s', 'delay' => '-3s', 'rotate' => 5, 'tint' => '175, 205, 255', 'z' => 4, 'cores' => [['size' => 16, 'top' => '42%', 'left' => '40%', 'color' => '255, 152, 102']]],
            ['size' => 222, 'top' => '66%', 'left' => '76%', 'drift' => '16px', 'duration' => '18s', 'delay' => '-9s', 'rotate' => -4, 'tint' => '132, 196, 255', 'z' => 2, 'cores' => [['size' => 26, 'top' => '30%', 'left' => '31%', 'color' => '255, 205, 108'], ['size' => 16, 'top' => '58%', 'left' => '54%', 'color' => '124, 229, 255']]],
        ];
    @endphp

    <section class="home-panel">
        <div class="home-left-orb" aria-hidden="true">
            <span class="left-orb-sheen"></span>
            <span class="left-orb-reflection"></span>
            <span class="left-orb-haze"></span>
        </div>
        <div class="home-memory-shell" aria-hidden="true">
            <span class="memory-shell-sheen"></span>
            <span class="memory-shell-reflection"></span>
            <span class="memory-shell-haze"></span>
        </div>
        <div class="home-memory-field" aria-hidden="true">
            @foreach ($memoryBubbles as $bubble)
                <div
                    class="memory-bubble"
                    style="
                        --bubble-size: {{ $bubble['size'] }}px;
                        --bubble-top: {{ $bubble['top'] }};
                        --bubble-left: {{ $bubble['left'] }};
                        --bubble-drift: {{ $bubble['drift'] }};
                        --float-duration: {{ $bubble['duration'] }};
                        --float-delay: {{ $bubble['delay'] }};
                        --bubble-rotate: {{ $bubble['rotate'] }}deg;
                        --bubble-rotate-back: {{ $bubble['rotate'] * -0.55 }}deg;
                        --bubble-rotate-soft: {{ $bubble['rotate'] * 0.38 }}deg;
                        --bubble-tint: {{ $bubble['tint'] }};
                        --bubble-z: {{ $bubble['z'] }};
                    "
                >
                    <span class="bubble-sheen"></span>
                    <span class="bubble-reflection"></span>
                    <span class="bubble-haze"></span>
                    @foreach ($bubble['cores'] as $core)
                        <span
                            class="memory-core"
                            style="
                                --core-size: {{ $core['size'] }}px;
                                --core-top: {{ $core['top'] }};
                                --core-left: {{ $core['left'] }};
                                --core-color: {{ $core['color'] }};
                            "
                        ></span>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="home-content">
            <div class="home-brand">
                <span class="eyebrow">BUNSHIN AI</span>
                <h1>記憶分身AI</h1>
            </div>

            <div class="home-actions">
                <a class="home-action-btn" href="{{ route('memories.create') }}">記憶を追加する</a>
                <a class="home-action-btn" href="{{ route('memories.index') }}">記憶を見る</a>
                <span class="home-action-btn is-disabled" aria-disabled="true">記憶と話す（ダミー）</span>
                <span class="home-action-btn is-disabled" aria-disabled="true">友だちと共有する（ダミー）</span>
            </div>
        </div>
    </section>

    <style>
        .body-home {
            background: #000;
        }

        .home-panel {
            position: relative;
            min-height: 100vh;
            padding: 56px 48px;
            overflow: hidden;
            background:
                radial-gradient(circle at 16% 20%, rgba(101, 155, 255, 0.2), transparent 26%),
                radial-gradient(circle at 72% 22%, rgba(103, 220, 255, 0.16), transparent 24%),
                radial-gradient(circle at 58% 78%, rgba(255, 150, 206, 0.12), transparent 26%),
                radial-gradient(circle at 82% 58%, rgba(142, 255, 212, 0.1), transparent 22%),
                linear-gradient(180deg, #05070d 0%, #0a1020 52%, #070b16 100%);
            color: rgba(245, 248, 252, 0.96);
        }

        .home-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.06) 0.7px, transparent 0.9px),
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0));
            background-size: 9px 9px, 100% 100%;
            opacity: 0.28;
        }

        .home-left-orb {
            position: absolute;
            top: 50%;
            left: 0;
            width: min(58vw, 840px);
            aspect-ratio: 1 / 1;
            overflow: hidden;
            pointer-events: none;
            transform: translate(-48%, -50%);
            border-radius: 50%;
            background:
                radial-gradient(circle at 28% 30%, rgba(255, 255, 255, 0.28), transparent 14%),
                radial-gradient(circle at 74% 72%, rgba(255, 182, 220, 0.16), transparent 24%),
                radial-gradient(circle at 56% 54%, rgba(112, 202, 255, 0.18), rgba(76, 111, 156, 0.09) 56%, rgba(20, 28, 46, 0.12) 74%, rgba(255, 255, 255, 0.06) 100%);
            border: 1px solid rgba(228, 241, 255, 0.28);
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.08),
                inset -28px -36px 72px rgba(110, 186, 255, 0.12),
                0 0 68px rgba(126, 197, 255, 0.18);
            filter: saturate(1.05);
            animation: left-orb-float 17s ease-in-out infinite;
        }

        .home-left-orb::before {
            content: "";
            position: absolute;
            inset: 6% 7%;
            border-radius: 50%;
            border: 1px solid rgba(235, 244, 255, 0.18);
        }

        .left-orb-sheen,
        .left-orb-reflection,
        .left-orb-haze {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .left-orb-sheen {
            top: 15%;
            right: 17%;
            width: 28%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0) 74%);
            filter: blur(1px);
            animation: left-orb-sheen-drift 12s ease-in-out infinite;
        }

        .left-orb-reflection {
            top: 24%;
            left: 23%;
            width: 16%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.28), rgba(255, 255, 255, 0) 78%);
            filter: blur(2px);
            animation: left-orb-reflection-drift 14s ease-in-out infinite;
        }

        .left-orb-haze {
            inset: auto auto 10% 11%;
            width: 40%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(113, 198, 255, 0.24), rgba(113, 198, 255, 0) 72%);
            filter: blur(16px);
            animation: left-orb-haze-drift 15s ease-in-out infinite;
        }

        .home-memory-field {
            position: absolute;
            inset: 0 0 0 34%;
            overflow: hidden;
            pointer-events: none;
            z-index: 2;
        }

        .home-memory-shell {
            position: absolute;
            top: 50%;
            right: -14%;
            width: min(78vw, 1180px);
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            pointer-events: none;
            transform: translateY(-50%);
            background:
                radial-gradient(circle at 28% 26%, rgba(255, 255, 255, 0.16), transparent 16%),
                radial-gradient(circle at 74% 72%, rgba(184, 220, 255, 0.12), transparent 24%),
                radial-gradient(circle at 50% 50%, rgba(124, 187, 255, 0.09), rgba(92, 117, 156, 0.03) 60%, rgba(18, 24, 36, 0.03) 76%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(214, 236, 255, 0.14);
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.03),
                inset -24px -30px 64px rgba(130, 194, 255, 0.06),
                0 0 46px rgba(124, 187, 255, 0.08);
            overflow: hidden;
            z-index: 1;
            animation: memory-shell-float 22s ease-in-out infinite;
        }

        .home-memory-shell::before {
            content: "";
            position: absolute;
            inset: 6% 7%;
            border-radius: 50%;
            border: 1px solid rgba(237, 245, 255, 0.05);
        }

        .memory-shell-sheen,
        .memory-shell-reflection,
        .memory-shell-haze {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .memory-shell-sheen {
            top: 14%;
            right: 18%;
            width: 24%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.28), rgba(255, 255, 255, 0) 76%);
            animation: memory-shell-sheen-drift 15s ease-in-out infinite;
        }

        .memory-shell-reflection {
            top: 24%;
            left: 22%;
            width: 14%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0) 78%);
            filter: blur(2px);
        }

        .memory-shell-haze {
            inset: auto auto 10% 12%;
            width: 40%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(130, 194, 255, 0.12), rgba(130, 194, 255, 0) 74%);
            filter: blur(18px);
        }

        .memory-bubble {
            position: absolute;
            top: var(--bubble-top);
            left: var(--bubble-left);
            z-index: var(--bubble-z);
            width: var(--bubble-size);
            height: var(--bubble-size);
            border-radius: 50%;
            background:
                radial-gradient(circle at 30% 28%, rgba(255, 255, 255, 0.22), transparent 18%),
                radial-gradient(circle at 67% 70%, rgba(184, 220, 255, 0.12), transparent 28%),
                radial-gradient(circle at 50% 50%, rgba(124, 187, 255, 0.09), rgba(78, 104, 138, 0.03) 58%, rgba(18, 24, 36, 0.04) 74%, rgba(255, 255, 255, 0.03) 100%);
            border: 1px solid rgba(214, 236, 255, 0.14);
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.04),
                inset -16px -24px 40px rgba(130, 194, 255, 0.05),
                0 0 28px rgba(124, 187, 255, 0.08);
            backdrop-filter: blur(2px);
            animation: bubble-float var(--float-duration) ease-in-out infinite;
            animation-delay: var(--float-delay);
            will-change: transform;
        }

        .memory-bubble::before {
            content: "";
            position: absolute;
            inset: 7% 8%;
            border-radius: 50%;
            border: 1px solid rgba(235, 244, 255, 0.06);
        }

        .bubble-sheen,
        .bubble-reflection,
        .bubble-haze {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .bubble-sheen {
            top: 15%;
            right: 17%;
            width: 25%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.26), rgba(255, 255, 255, 0) 76%);
        }

        .bubble-reflection {
            top: 26%;
            left: 24%;
            width: 14%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0) 78%);
            filter: blur(2px);
        }

        .bubble-haze {
            inset: auto auto 12% 14%;
            width: 42%;
            aspect-ratio: 1 / 1;
            background: radial-gradient(circle, rgba(184, 220, 255, 0.14), rgba(184, 220, 255, 0) 74%);
            filter: blur(12px);
        }

        .memory-core {
            position: absolute;
            top: var(--core-top);
            left: var(--core-left);
            width: calc(var(--core-size) * 2.2);
            height: calc(var(--core-size) * 2.2);
            border-radius: 50%;
            isolation: isolate;
            background:
                radial-gradient(circle at 34% 30%, rgba(248, 249, 255, 0.84), rgba(240, 243, 250, 0.7) 22%, rgba(var(--core-color), 0.42) 48%, rgba(var(--core-color), 0.2) 72%, rgba(39, 60, 92, 0.06) 100%);
            box-shadow:
                0 0 8px rgba(255, 255, 255, 0.1),
                0 0 18px rgba(255, 255, 255, 0.05),
                0 0 30px rgba(var(--core-color), 0.18),
                inset 0 -3px 7px rgba(34, 62, 98, 0.08);
            filter: saturate(0.72) brightness(0.92);
            animation: memory-core-drift calc(var(--float-duration) * 0.92) ease-in-out infinite;
            animation-delay: var(--float-delay);
        }

        .memory-core::before,
        .memory-core::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            pointer-events: none;
        }

        .memory-core::before {
            background: radial-gradient(circle, rgba(var(--core-color), 0.18), rgba(var(--core-color), 0.06) 58%, rgba(var(--core-color), 0) 80%);
            transform: scale(1.38);
            filter: blur(12px);
            z-index: -2;
        }

        .memory-core::after {
            inset: 16% auto auto 18%;
            width: 32%;
            height: 32%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.34), rgba(255, 255, 255, 0) 78%);
            filter: blur(1.5px);
            z-index: 1;
        }

        .home-content {
            position: relative;
            z-index: 5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            width: 100%;
        }

        .home-brand {
            position: absolute;
            top: 56px;
            left: 48px;
            max-width: 420px;
            text-align: left;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        .home-brand .eyebrow {
            display: inline-flex;
            padding: 8px 14px;
            border-radius: 12px;
            background: rgba(17, 24, 39, 0.46);
            border: 1px solid rgba(214, 233, 255, 0.24);
            color: rgba(245, 247, 250, 0.92);
            letter-spacing: 0.16em;
            font-size: 11px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.16);
            backdrop-filter: blur(12px);
        }

        .home-brand h1 {
            margin: 16px 0 0;
            font-size: clamp(40px, 5.4vw, 76px);
            line-height: 1.04;
            color: rgba(247, 250, 255, 0.98);
            letter-spacing: 0.04em;
        }

        .home-actions {
            display: grid;
            gap: 14px;
            width: min(420px, 100%);
            justify-items: end;
        }

        .home-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 56px;
            padding: 0 18px;
            width: min(360px, 100%);
            border-radius: 14px;
            border: 1px solid rgba(202, 229, 255, 0.18);
            background: linear-gradient(135deg, rgba(18, 24, 39, 0.56), rgba(28, 36, 52, 0.72));
            color: rgba(244, 247, 251, 0.94);
            box-shadow: 0 18px 32px rgba(0, 0, 0, 0.18);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
            transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
            backdrop-filter: blur(14px);
        }

        .home-action-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(222, 239, 255, 0.34);
            background: linear-gradient(135deg, rgba(36, 48, 69, 0.72), rgba(44, 58, 82, 0.82));
            color: rgba(252, 253, 255, 0.98);
            box-shadow: 0 20px 34px rgba(0, 0, 0, 0.2);
        }

        .home-action-btn.is-disabled {
            opacity: 0.58;
            cursor: default;
        }

        .home-action-btn.is-disabled:hover {
            transform: none;
            border-color: rgba(202, 229, 255, 0.18);
            background: linear-gradient(135deg, rgba(18, 24, 39, 0.56), rgba(28, 36, 52, 0.72));
            color: rgba(244, 247, 251, 0.94);
            box-shadow: 0 18px 32px rgba(0, 0, 0, 0.18);
        }

        @media (max-width: 760px) {
            .home-panel {
                min-height: 100vh;
                padding: 34px 18px;
            }

            .home-memory-field {
                inset: 18% 0 0 18%;
            }

            .home-memory-shell {
                top: 50%;
                right: -38%;
                width: min(128vw, 920px);
            }

            .home-content {
                min-height: 100vh;
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-end;
                gap: 36px;
                padding-top: 120px;
                padding-bottom: 32px;
            }

            .home-brand {
                position: static;
                max-width: none;
                margin-bottom: auto;
            }

            .home-actions {
                width: 100%;
                justify-items: stretch;
            }

            .home-action-btn {
                min-height: 52px;
                width: 100%;
                font-size: 16px;
            }

            .home-left-orb {
                top: 42%;
                width: min(84vw, 620px);
                transform: translate(-58%, -50%);
            }
        }

        @keyframes bubble-float {
            0%, 100% {
                transform: translate3d(0, 0, 0) rotate(0deg);
            }
            25% {
                transform: translate3d(calc(var(--bubble-drift) * 0.32), calc(var(--bubble-drift) * -0.58), 0) rotate(var(--bubble-rotate));
            }
            50% {
                transform: translate3d(calc(var(--bubble-drift) * -0.16), calc(var(--bubble-drift) * -0.94), 0) rotate(var(--bubble-rotate-back));
            }
            75% {
                transform: translate3d(calc(var(--bubble-drift) * -0.36), calc(var(--bubble-drift) * -0.36), 0) rotate(var(--bubble-rotate-soft));
            }
        }

        @keyframes memory-core-drift {
            0%, 100% {
                transform: translate3d(0, 0, 0) scale(1);
            }
            50% {
                transform: translate3d(5px, -6px, 0) scale(1.07);
            }
        }

        @keyframes memory-shell-float {
            0%, 100% {
                transform: translateY(-50%) scale(1) rotate(0deg);
            }
            50% {
                transform: translateY(-51.8%) scale(1.012) rotate(-1deg);
            }
        }

        @keyframes memory-shell-sheen-drift {
            0%, 100% {
                transform: translate3d(0, 0, 0) scale(1);
                opacity: 0.85;
            }
            50% {
                transform: translate3d(-10px, 8px, 0) scale(1.05);
                opacity: 0.68;
            }
        }

        @keyframes left-orb-float {
            0%, 100% {
                transform: translate(-48%, -50%) scale(1) rotate(0deg);
            }
            25% {
                transform: translate(-46.5%, -51.4%) scale(1.02) rotate(1.2deg);
            }
            50% {
                transform: translate(-49.6%, -48.5%) scale(0.985) rotate(-0.9deg);
            }
            75% {
                transform: translate(-47.2%, -50.8%) scale(1.015) rotate(0.6deg);
            }
        }

        @keyframes left-orb-sheen-drift {
            0%, 100% {
                transform: translate3d(0, 0, 0) scale(1);
                opacity: 1;
            }
            50% {
                transform: translate3d(-8px, 6px, 0) scale(1.06);
                opacity: 0.88;
            }
        }

        @keyframes left-orb-reflection-drift {
            0%, 100% {
                transform: translate3d(0, 0, 0);
                opacity: 0.9;
            }
            50% {
                transform: translate3d(10px, -8px, 0);
                opacity: 0.72;
            }
        }

        @keyframes left-orb-haze-drift {
            0%, 100% {
                transform: translate3d(0, 0, 0) scale(1);
            }
            50% {
                transform: translate3d(14px, -10px, 0) scale(1.08);
            }
        }
    </style>
@endsection
