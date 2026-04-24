@extends('layouts.app')

@section('title', 'YOUの記憶 | 分身AI MVP')
@section('page_class', 'page-bubbles-full')

@section('content')
    <section class="panel bubble-stage-panel">
        @if ($bubbleMemories->isEmpty())
            <div class="empty-state">
                @if ($selectedPeriod !== 'すべて')
                    「{{ $selectedPeriod }}」に該当する記憶がありません。<br>
                    右上の年代別表示から別の年代を選んでください。
                @else
                    記憶がまだありません。<br>
                    一覧に戻るか、サンプルデータを投入してください。
                @endif
            </div>
        @else
            @php
                $bubbleBaseParams = $selectedPeriod !== 'すべて' ? ['period' => $selectedPeriod] : [];
                $bubbleBaseRoute = route('memories.bubbles');
            @endphp
            <div class="bubble-stage-top">
                <div class="bubble-stage-copy">
                    <span class="eyebrow">PERSONAL MEMORY ARCHIVE</span>
                    <h1>YOUの記憶</h1>
                </div>

                <div class="bubble-stage-hub">
                    <details class="bubble-stage-hub-card bubble-stage-actions">
                        <summary class="btn btn-secondary bubble-stage-actions-trigger">今日は何をする？</summary>
                        <div class="bubble-stage-actions-menu">
                            <a class="btn btn-secondary bubble-rail-btn" href="#" aria-disabled="true">記憶を追加</a>
                            <a class="btn btn-secondary bubble-rail-btn" href="#" aria-disabled="true">記憶と話す</a>
                            <a class="btn btn-secondary bubble-rail-btn" href="{{ route('memories.index') }}">記憶一覧を見る</a>

                            <form method="get" action="{{ route('memories.bubbles') }}" class="bubble-filter-form">
                                <label for="period" class="bubble-side-label">年代別で表示</label>
                                <select id="period" name="period" class="bubble-select">
                                    <option value="すべて" {{ $selectedPeriod === 'すべて' ? 'selected' : '' }}>すべて</option>
                                    @foreach ($periods as $period)
                                        <option value="{{ $period }}" {{ $selectedPeriod === $period ? 'selected' : '' }}>{{ $period }}</option>
                                    @endforeach
                                </select>
                                <div class="bubble-filter-actions">
                                    <button type="submit" class="btn btn-primary">表示する</button>
                                    @if ($selectedPeriod !== 'すべて')
                                        <a class="btn btn-secondary" href="{{ route('memories.bubbles') }}">解除</a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </details>
                </div>
            </div>

            <section class="bubble-stage-count bubble-stage-count-floating" aria-label="全記憶数">
                <span class="bubble-side-label">全記憶数</span>
                <strong>{{ $matchingCount }}</strong>
                <span class="bubble-stage-count-caption">memories drifting now</span>
            </section>

            <div class="bubble-stage-rail">
                <div class="bubble-stage-side bubble-stage-rail-card">
                    @if ($layerCount > 1)
                        <section class="bubble-rail-section bubble-stage-nav">
                            <span class="bubble-side-label">表示階層</span>
                            <strong>第{{ $currentLayer }}層 / 全{{ $layerCount }}層</strong>
                            <div class="bubble-nav-actions">
                                @if ($hasNextLayer)
                                    <a class="bubble-mini-btn" href="{{ route('memories.bubbles', array_merge($bubbleBaseParams, ['layer' => $currentLayer + 1])) }}">もっと見る</a>
                                @else
                                    <span class="bubble-mini-btn is-disabled">もっと見る</span>
                                @endif

                                @if ($hasPreviousLayer)
                                    <a class="bubble-mini-btn" href="{{ route('memories.bubbles', array_merge($bubbleBaseParams, ['layer' => $currentLayer - 1])) }}">1つ戻る</a>
                                    <a class="bubble-mini-btn" href="{{ route('memories.bubbles', $bubbleBaseParams) }}">最初に戻る</a>
                                @else
                                    <span class="bubble-mini-btn is-disabled">1つ戻る</span>
                                    <span class="bubble-mini-btn is-disabled">最初に戻る</span>
                                @endif
                            </div>
                        </section>
                    @endif

                </div>
            </div>

            <div class="bubble-stage-shell">
                <div class="bubble-caption">MEMORY BUBBLE / DRAG TO MOVE / SCROLL OR PINCH TO ZOOM</div>
                @if ($selectedPeriod !== 'すべて')
                    <div class="bubble-period-banner">{{ $selectedPeriod }}</div>
                @endif
                <svg id="bubbleStage" viewBox="0 0 1400 920" xmlns="http://www.w3.org/2000/svg" aria-label="YOUの記憶">
                    <defs id="bubbleDefs">
                        <filter id="shellGlow" x="-80%" y="-80%" width="260%" height="260%">
                            <feGaussianBlur stdDeviation="44"></feGaussianBlur>
                        </filter>
                        <filter id="stackShadow" x="-120%" y="-120%" width="340%" height="340%">
                            <feGaussianBlur stdDeviation="24"></feGaussianBlur>
                        </filter>
                        <filter id="ballShadow" x="-70%" y="-70%" width="240%" height="240%">
                            <feDropShadow dx="0" dy="12" stdDeviation="14" flood-color="#6f7a92" flood-opacity="0.18" />
                        </filter>
                        <filter id="ballAura" x="-160%" y="-160%" width="420%" height="420%">
                            <feGaussianBlur stdDeviation="28"></feGaussianBlur>
                        </filter>
                    </defs>

                    <circle cx="160" cy="720" r="128" fill="rgba(184, 220, 255, 0.12)"></circle>
                    <circle cx="1230" cy="150" r="96" fill="rgba(201, 226, 255, 0.14)"></circle>
                    <circle cx="210" cy="140" r="4" fill="rgba(255,255,255,0.82)"></circle>
                    <circle cx="320" cy="210" r="2.8" fill="rgba(220,236,255,0.72)"></circle>
                    <circle cx="1110" cy="112" r="3.2" fill="rgba(255,255,255,0.86)"></circle>
                    <circle cx="1205" cy="238" r="2.2" fill="rgba(216,232,255,0.72)"></circle>
                    <circle cx="1048" cy="732" r="3.8" fill="rgba(255,255,255,0.74)"></circle>
                    <circle cx="248" cy="622" r="2.6" fill="rgba(218,234,255,0.7)"></circle>

                    @if ($layerCount > 1)
                        @foreach (range(min($layerCount - 1, 3), 1) as $stackIndex)
                            @php
                                $stackOffsetX = 38 * $stackIndex;
                                $stackOffsetY = -30 * $stackIndex;
                                $stackRadius = 376 - (14 * $stackIndex);
                                $stackOpacity = 0.1 + (0.03 * (min($layerCount - 1, 3) - $stackIndex));
                            @endphp
                            <ellipse
                                cx="{{ 714 + $stackOffsetX }}"
                                cy="{{ 548 + $stackOffsetY }}"
                                rx="{{ 176 - (12 * $stackIndex) }}"
                                ry="{{ 52 - (4 * $stackIndex) }}"
                                fill="rgba(20, 48, 86, 0.28)"
                                filter="url(#stackShadow)"
                                class="bubble-stack-shadow bubble-shell-breath"
                                style="--shell-duration: {{ 8.4 + ($stackIndex * 0.7) }}s; --shell-delay: -{{ 0.85 * $stackIndex }}s;"
                            ></ellipse>
                            <g
                                filter="url(#shellGlow)"
                                class="bubble-stack-layer bubble-shell-breath"
                                style="--shell-duration: {{ 8.4 + ($stackIndex * 0.7) }}s; --shell-delay: -{{ 0.85 * $stackIndex }}s;"
                            >
                                <circle
                                    cx="{{ 700 + $stackOffsetX }}"
                                    cy="{{ 470 + $stackOffsetY }}"
                                    r="{{ $stackRadius }}"
                                    fill="rgba(130, 194, 255, {{ $stackOpacity }})"
                                ></circle>
                                <ellipse
                                    cx="{{ 624 + $stackOffsetX }}"
                                    cy="{{ 374 + $stackOffsetY }}"
                                    rx="{{ 108 - (8 * $stackIndex) }}"
                                    ry="{{ 40 - (3 * $stackIndex) }}"
                                    fill="rgba(255,255,255,0.10)"
                                    transform="rotate(-18 {{ 624 + $stackOffsetX }} {{ 374 + $stackOffsetY }})"
                                ></ellipse>
                                <circle
                                    cx="{{ 700 + $stackOffsetX }}"
                                    cy="{{ 470 + $stackOffsetY }}"
                                    r="{{ $stackRadius - 10 }}"
                                    fill="none"
                                    stroke="rgba(214, 236, 255, 0.14)"
                                    stroke-width="2"
                                ></circle>
                            </g>
                        @endforeach
                    @endif

                    <g filter="url(#shellGlow)" class="bubble-shell-breath bubble-shell-main" style="--shell-duration: 7.6s; --shell-delay: -0.4s;">
                        <circle cx="700" cy="470" r="372" fill="rgba(124, 187, 255, 0.22)"></circle>
                    </g>

                    <ellipse
                        cx="578"
                        cy="340"
                        rx="120"
                        ry="58"
                        fill="rgba(255,255,255,0.16)"
                        transform="rotate(-20 578 340)"
                        class="bubble-shell-breath"
                        style="--shell-duration: 7.6s; --shell-delay: -0.4s;"
                    ></ellipse>
                    <ellipse
                        cx="820"
                        cy="585"
                        rx="38"
                        ry="18"
                        fill="rgba(255,255,255,0.10)"
                        transform="rotate(14 820 585)"
                        class="bubble-shell-breath"
                        style="--shell-duration: 7.6s; --shell-delay: -0.4s;"
                    ></ellipse>

                    <circle
                        cx="700"
                        cy="470"
                        r="324"
                        fill="rgba(202,228,255,0.04)"
                        class="bubble-shell-breath"
                        style="--shell-duration: 7.6s; --shell-delay: -0.4s;"
                    ></circle>

                    <g id="bubbleMapViewport" class="bubble-map-viewport">
                        <g id="bubbleMapGrid"></g>
                        <g id="bubbleMapPeriods"></g>
                        <g id="bubbleLayer"></g>
                    </g>
                </svg>
                <div class="bubble-gesture-guide" aria-hidden="true">
                    <span class="bubble-gesture-chip">
                        <i></i>
                        ふわっとドラッグで移動
                    </span>
                    <span class="bubble-gesture-chip">
                        <i></i>
                        ホイール / ピンチで拡大縮小
                    </span>
                </div>
            </div>
        @endif
    </section>

    <style>
        .page.page-bubbles-full {
            width: calc(100vw - 12px);
            max-width: none;
            padding: 8px 0 14px;
        }

        .bubble-stage-panel {
            position: relative;
            min-height: calc(100vh - 22px);
            padding: 0;
            overflow: hidden;
            background:
                radial-gradient(circle at 18% 18%, rgba(86, 132, 255, 0.18), transparent 20%),
                radial-gradient(circle at 82% 16%, rgba(126, 209, 255, 0.14), transparent 18%),
                radial-gradient(circle at 50% 72%, rgba(88, 108, 255, 0.12), transparent 26%),
                linear-gradient(160deg, #02040b 0%, #050916 48%, #0a1124 100%);
            color: rgba(238, 245, 255, 0.94);
        }

        .bubble-stage-top {
            position: absolute;
            top: 24px;
            left: 50%;
            z-index: 4;
            transform: translateX(-50%);
            display: grid;
            justify-items: center;
            gap: 12px;
            width: min(760px, calc(100% - 360px));
        }

        .bubble-stage-copy {
            position: relative;
            z-index: 1;
            max-width: 420px;
            text-align: center;
        }

        .bubble-stage-hub {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
            justify-content: center;
        }

        .bubble-stage-hub-card {
            border-radius: 18px;
            border: 1px solid rgba(171, 205, 255, 0.12);
            background: linear-gradient(180deg, rgba(12, 21, 42, 0.92), rgba(10, 18, 39, 0.82));
            box-shadow: 0 14px 28px rgba(6, 10, 24, 0.24);
            backdrop-filter: blur(12px);
        }

        .bubble-stage-count-floating {
            position: absolute;
            top: clamp(308px, 34vw, 380px);
            left: clamp(32px, 4.2vw, 74px);
            z-index: 3;
            min-width: 238px;
            max-width: 280px;
            padding: 24px 26px 22px;
            border-radius: 30px;
            border: 1px solid rgba(186, 220, 255, 0.18);
            background:
                radial-gradient(circle at 20% 18%, rgba(170, 229, 255, 0.28), transparent 34%),
                radial-gradient(circle at 76% 20%, rgba(255, 190, 226, 0.16), transparent 30%),
                linear-gradient(155deg, rgba(11, 19, 39, 0.8), rgba(8, 14, 30, 0.58));
            box-shadow:
                0 24px 54px rgba(4, 8, 20, 0.28),
                inset 0 1px 0 rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px);
            text-align: left;
            overflow: hidden;
        }

        .bubble-stage-count-floating::before,
        .bubble-stage-count-floating::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(8px);
        }

        .bubble-stage-count-floating::before {
            width: 148px;
            height: 148px;
            top: -54px;
            left: -24px;
            background: radial-gradient(circle, rgba(162, 223, 255, 0.24), transparent 68%);
        }

        .bubble-stage-count-floating::after {
            width: 122px;
            height: 122px;
            right: -34px;
            bottom: -44px;
            background: radial-gradient(circle, rgba(255, 191, 226, 0.16), transparent 70%);
        }

        .bubble-stage-copy h1 {
            margin: 16px 0 16px;
            font-size: clamp(30px, 3.4vw, 50px);
            color: rgba(245, 249, 255, 0.96);
            letter-spacing: 0.03em;
        }

        .bubble-stage-copy .eyebrow {
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

        .bubble-stage-panel .btn,
        .bubble-stage-panel .bubble-mini-btn {
            border-radius: 14px;
            border-width: 1px;
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .bubble-stage-panel .btn:hover,
        .bubble-stage-panel .bubble-mini-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(196, 224, 255, 0.34);
            color: rgba(250, 252, 255, 0.98);
            box-shadow: 0 14px 28px rgba(18, 36, 78, 0.32);
        }

        .bubble-stage-panel .btn-primary {
            background: linear-gradient(135deg, rgba(142, 204, 255, 0.28), rgba(87, 132, 255, 0.78));
            border-color: rgba(180, 218, 255, 0.24);
            color: rgba(245, 249, 255, 0.96);
            box-shadow: 0 14px 28px rgba(40, 82, 168, 0.26);
        }

        .bubble-stage-panel .btn-secondary {
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            border-color: rgba(166, 204, 255, 0.16);
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.28);
        }

        .bubble-stage-panel .btn-primary:hover,
        .bubble-stage-panel .btn-secondary:hover,
        .bubble-stage-panel .bubble-mini-btn:hover {
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
        }

        .bubble-stage-shell {
            position: relative;
            width: 100%;
            height: 100%;
            padding-bottom: 58px;
            overflow: hidden;
        }

        .bubble-stage-rail {
            position: absolute;
            top: 164px;
            right: 24px;
            z-index: 3;
            width: min(236px, 22vw);
        }

        .bubble-stage-side {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: 16px 18px;
            border-radius: 20px;
            background: transparent;
            border: 1px solid transparent;
            backdrop-filter: none;
            box-shadow: none;
        }

        .bubble-stage-rail-card {
            display: grid;
            gap: 0;
            height: 100%;
            overflow: hidden;
            align-content: start;
        }

        .bubble-rail-section {
            padding: 14px 0;
        }

        .bubble-rail-section + .bubble-rail-section {
            border-top: 1px solid rgba(171, 205, 255, 0.12);
        }

        .bubble-stage-count {
            min-width: 132px;
        }

        .bubble-stage-count strong {
            position: relative;
            z-index: 1;
            display: block;
            color: rgba(245, 249, 255, 0.98);
            font-size: clamp(62px, 7vw, 88px);
            line-height: 0.88;
            letter-spacing: -0.04em;
            text-shadow: 0 0 34px rgba(155, 214, 255, 0.12);
        }

        .bubble-stage-count-caption {
            position: relative;
            z-index: 1;
            display: block;
            margin-top: 10px;
            color: rgba(192, 215, 246, 0.74);
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .bubble-stage-actions {
            min-width: min(360px, 100%);
        }

        .bubble-rail-btn {
            width: 100%;
        }

        .bubble-stage-nav {
            padding-bottom: 2px;
            text-align: left;
        }

        .bubble-stage-nav strong {
            display: block;
            color: rgba(245, 249, 255, 0.98);
            font-size: 17px;
            margin-bottom: 10px;
            text-align: left;
        }

        .bubble-nav-actions {
            display: flex;
            flex-wrap: nowrap;
            justify-content: flex-start;
            gap: 6px;
        }

        .bubble-mini-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 26px;
            padding: 0 8px;
            border: 1px solid rgba(178, 210, 255, 0.22);
            background: linear-gradient(135deg, rgba(19, 30, 57, 0.96), rgba(10, 17, 35, 0.96));
            color: rgba(240, 246, 255, 0.92);
            font-size: 10px;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 10px 22px rgba(6, 10, 24, 0.22);
        }

        .bubble-mini-btn.is-disabled {
            opacity: 0.34;
            pointer-events: none;
        }

        .bubble-stage-actions summary {
            list-style: none;
            width: 100%;
        }

        .bubble-stage-rail .btn,
        .bubble-stage-hub .btn {
            padding: 9px 12px;
            font-size: 11px;
            border-radius: 11px;
        }

        .bubble-stage-rail .bubble-side-label,
        .bubble-stage-hub .bubble-side-label {
            font-size: 11px;
            margin-bottom: 5px;
        }

        .bubble-stage-rail .bubble-select,
        .bubble-stage-hub .bubble-select {
            padding: 10px 12px;
            font-size: 12px;
        }

        .bubble-stage-rail .bubble-filter-actions,
        .bubble-stage-hub .bubble-filter-actions {
            gap: 8px;
        }

        .bubble-stage-actions summary::-webkit-details-marker {
            display: none;
        }

        .bubble-stage-actions-menu {
            display: grid;
            gap: 10px;
            margin-top: 14px;
            padding: 16px;
            border-radius: 24px;
            background:
                radial-gradient(circle at top left, rgba(185, 236, 255, 0.18), transparent 34%),
                radial-gradient(circle at top right, rgba(255, 197, 228, 0.14), transparent 30%),
                rgba(18, 27, 50, 0.42);
            border: 1px solid rgba(224, 243, 255, 0.16);
            box-shadow:
                0 18px 38px rgba(8, 14, 30, 0.16),
                inset 0 1px 0 rgba(255,255,255,0.09);
            backdrop-filter: blur(18px);
        }

        .bubble-stage-actions-trigger {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 78px;
            padding: 18px 54px 18px 28px;
            justify-content: flex-start;
            width: 100%;
            border-radius: 999px;
            border: 1px solid rgba(220, 243, 255, 0.22);
            background:
                radial-gradient(circle at 26% 30%, rgba(212, 246, 255, 0.74), rgba(212, 246, 255, 0) 30%),
                radial-gradient(circle at 74% 26%, rgba(255, 206, 233, 0.44), rgba(255, 206, 233, 0) 26%),
                radial-gradient(circle at 52% 70%, rgba(166, 219, 255, 0.24), rgba(166, 219, 255, 0) 36%),
                linear-gradient(135deg, rgba(217, 245, 255, 0.24), rgba(142, 191, 255, 0.16));
            color: rgba(248, 252, 255, 0.98);
            box-shadow:
                0 22px 42px rgba(34, 64, 118, 0.18),
                inset 0 1px 0 rgba(255,255,255,0.28),
                inset 0 -10px 26px rgba(126, 185, 255, 0.08);
            backdrop-filter: blur(18px) saturate(130%);
            overflow: hidden;
            isolation: isolate;
            text-shadow: 0 1px 10px rgba(100, 140, 210, 0.16);
        }

        .bubble-stage-actions-trigger::after {
            content: "";
            position: absolute;
            right: 24px;
            top: 50%;
            z-index: 2;
            width: 10px;
            height: 10px;
            margin-top: -6px;
            border-right: 2px solid rgba(246, 251, 255, 0.96);
            border-bottom: 2px solid rgba(246, 251, 255, 0.96);
            transform: rotate(45deg);
            transition: transform 0.2s ease, margin-top 0.2s ease;
        }

        .bubble-stage-actions-trigger::before {
            content: "";
            position: absolute;
            inset: -10px;
            z-index: -1;
            border-radius: 999px;
            background:
                radial-gradient(circle at 20% 24%, rgba(216, 248, 255, 0.56), transparent 24%),
                radial-gradient(circle at 40% 74%, rgba(189, 234, 255, 0.3), transparent 28%),
                radial-gradient(circle at 78% 32%, rgba(255, 206, 232, 0.4), transparent 24%);
            filter: blur(12px);
            opacity: 0.96;
        }

        .bubble-stage-actions:hover .bubble-stage-actions-trigger,
        .bubble-stage-actions[open] .bubble-stage-actions-trigger {
            transform: translateY(-2px) scale(1.01);
            border-color: rgba(239, 248, 255, 0.34);
            background:
                radial-gradient(circle at 24% 28%, rgba(222, 249, 255, 0.82), rgba(222, 249, 255, 0) 30%),
                radial-gradient(circle at 76% 24%, rgba(255, 213, 236, 0.5), rgba(255, 213, 236, 0) 28%),
                radial-gradient(circle at 52% 72%, rgba(173, 224, 255, 0.28), rgba(173, 224, 255, 0) 38%),
                linear-gradient(135deg, rgba(225, 247, 255, 0.28), rgba(150, 197, 255, 0.2));
            box-shadow:
                0 28px 50px rgba(44, 86, 152, 0.22),
                inset 0 1px 0 rgba(255,255,255,0.34),
                inset 0 -12px 28px rgba(126, 185, 255, 0.12);
        }

        .bubble-stage-actions[open] .bubble-stage-actions-trigger::after {
            transform: rotate(-135deg);
            margin-top: -1px;
        }

        .bubble-filter-form {
            display: grid;
            gap: 12px;
        }

        .bubble-select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(171, 205, 255, 0.2);
            background: rgba(14, 22, 43, 0.88);
            color: rgba(239, 245, 255, 0.94);
        }

        .bubble-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .bubble-side-label {
            display: block;
            margin-bottom: 6px;
            color: rgba(188, 214, 255, 0.68);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .bubble-stage-count-floating .bubble-side-label {
            position: relative;
            z-index: 1;
            margin-bottom: 12px;
            color: rgba(208, 229, 255, 0.82);
            font-size: 12px;
            letter-spacing: 0.2em;
        }

        .bubble-caption {
            position: absolute;
            left: 28px;
            bottom: 44px;
            z-index: 2;
            color: rgba(176, 202, 245, 0.58);
            font-size: 11px;
            letter-spacing: 0.14em;
        }

        .bubble-period-banner {
            position: absolute;
            left: 50%;
            top: 86px;
            z-index: 2;
            transform: translateX(-50%);
            padding: 10px 18px;
            border-radius: 999px;
            background: rgba(10, 18, 39, 0.66);
            border: 1px solid rgba(178, 210, 255, 0.18);
            color: rgba(234, 242, 255, 0.9);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            box-shadow: 0 18px 42px rgba(4, 8, 20, 0.3);
            backdrop-filter: blur(12px);
        }

        #bubbleStage {
            width: 100%;
            height: auto;
            max-height: min(920px, calc(100vh - 72px));
            display: block;
            cursor: grab;
            touch-action: none;
        }

        #bubbleStage.is-dragging {
            cursor: grabbing;
        }

        .bubble-shell-breath {
            transform-box: fill-box;
            transform-origin: center;
            animation: shellPulse var(--shell-duration, 7.8s) ease-in-out var(--shell-delay, 0s) infinite;
            will-change: transform, opacity;
        }

        .bubble-shell-main {
            animation-duration: var(--shell-duration, 7.6s);
        }

        .bubble-map-grid-line {
            stroke: rgba(192, 220, 255, 0.08);
            stroke-width: 1;
            stroke-dasharray: 10 12;
        }

        .bubble-period-halo {
            fill: rgba(167, 213, 255, 0.075);
            stroke: rgba(175, 212, 255, 0.18);
            stroke-width: 1.2;
        }

        .bubble-period-zone {
            cursor: pointer;
        }

        .bubble-period-zone .bubble-period-halo,
        .bubble-period-zone .bubble-period-anchor,
        .bubble-period-zone .bubble-period-name,
        .bubble-period-zone .bubble-period-count {
            transition: transform 0.24s ease, opacity 0.24s ease, filter 0.24s ease, fill 0.24s ease, stroke 0.24s ease;
            transform-box: fill-box;
            transform-origin: center;
        }

        .bubble-period-zone.is-active .bubble-period-halo,
        .bubble-period-zone.is-active .bubble-period-anchor,
        .bubble-period-zone.is-active .bubble-period-name,
        .bubble-period-zone.is-active .bubble-period-count {
            transform: scale(1.06);
        }

        .bubble-period-zone.is-active .bubble-period-name {
            fill: rgba(250, 252, 255, 0.98);
        }

        .bubble-period-zone.is-active .bubble-period-count {
            fill: rgba(214, 232, 255, 0.88);
        }

        .bubble-period-anchor {
            fill: rgba(243, 249, 255, 0.88);
        }

        .bubble-period-name {
            fill: rgba(244, 248, 255, 0.94);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-anchor: middle;
        }

        .bubble-period-count {
            fill: rgba(188, 214, 255, 0.68);
            font-size: 12px;
            text-anchor: middle;
        }

        .memory-ball-wrap {
            transform-box: fill-box;
            transform-origin: center;
            will-change: opacity;
            opacity: 0;
            animation: bubbleReveal 0.72s ease var(--bubble-appear-delay, 0s) forwards;
        }

        .memory-ball {
            cursor: pointer;
        }

        .memory-ball.is-hovered .memory-ball-body {
            animation: none;
            transform: scale(var(--bubble-hover-scale, 1.16));
            filter: brightness(1.12) saturate(1.12) drop-shadow(0 26px 34px rgba(108, 127, 169, 0.2));
        }

        .memory-ball.is-period-hovered .memory-ball-body {
            animation: none;
            transform: scale(1.14);
            filter: brightness(1.16) saturate(1.14) drop-shadow(0 22px 30px rgba(104, 140, 182, 0.28));
        }

        .memory-ball-body {
            transform-box: fill-box;
            transform-origin: center;
            will-change: transform, filter;
            animation: bubblePulse var(--bubble-duration, 6.8s) ease-in-out var(--bubble-delay, 0s) infinite;
            transition: transform 0.42s cubic-bezier(0.2, 0.7, 0.2, 1), filter 0.42s cubic-bezier(0.2, 0.7, 0.2, 1);
        }

        .memory-label {
            fill: white;
            font-weight: 700;
            font-size: 12px;
            text-anchor: middle;
            dominant-baseline: middle;
            paint-order: stroke;
            stroke: rgba(0, 0, 0, 0.12);
            stroke-width: 2px;
            stroke-linejoin: round;
            pointer-events: none;
        }

        .bubble-gesture-guide {
            position: absolute;
            left: 50%;
            bottom: 10px;
            z-index: 2;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            width: min(720px, calc(100% - 60px));
        }

        .bubble-gesture-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(9, 17, 36, 0.74);
            border: 1px solid rgba(175, 212, 255, 0.12);
            color: rgba(228, 239, 255, 0.88);
            font-size: 11px;
            box-shadow: 0 12px 24px rgba(6, 10, 24, 0.18);
            backdrop-filter: blur(10px);
        }

        .bubble-gesture-chip i {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(166, 215, 255, 0.98), rgba(255, 200, 222, 0.94));
            box-shadow: 0 0 10px rgba(166, 215, 255, 0.38);
        }

        @keyframes bubblePulse {
            0% {
                transform: scale(var(--bubble-rest-scale, 0.96));
            }

            50% {
                transform: scale(var(--bubble-rise-scale, 1.06));
            }

            100% {
                transform: scale(var(--bubble-rest-scale, 0.96));
            }
        }

        @keyframes bubbleReveal {
            0% {
                opacity: 0;
                filter: blur(8px);
            }

            100% {
                opacity: 1;
                filter: blur(0);
            }
        }

        @keyframes shellPulse {
            0% {
                transform: scale(0.985);
                opacity: 0.92;
            }

            50% {
                transform: scale(1.015);
                opacity: 1;
            }

            100% {
                transform: scale(0.985);
                opacity: 0.92;
            }
        }

        @media (max-width: 980px) {
            .page.page-bubbles-full {
                width: calc(100vw - 8px);
            }

            .bubble-stage-panel {
                min-height: 760px;
            }

            .bubble-stage-top {
                width: min(660px, calc(100% - 280px));
            }

            .bubble-stage-count-floating {
                top: clamp(360px, 39vw, 436px);
                left: 24px;
                min-width: 210px;
                padding: 20px 22px 18px;
            }

            .bubble-stage-rail {
                top: 176px;
                width: min(220px, 27vw);
            }
        }

        @media (max-width: 760px) {
            .bubble-stage-panel {
                min-height: auto;
                padding-top: 156px;
            }

            .bubble-stage-top,
            .bubble-stage-count-floating,
            .bubble-stage-rail {
                position: static;
                width: auto;
                margin: 0 18px 14px;
                transform: none;
            }

            .bubble-stage-copy,
            .bubble-stage-hub {
                width: auto;
                margin: 0;
            }

            .bubble-stage-side {
                width: auto;
            }

            .bubble-stage-hub {
                display: grid;
                gap: 10px;
            }

            .bubble-stage-count {
                text-align: center;
            }

            .bubble-stage-count-floating {
                max-width: none;
                padding: 18px 18px 16px;
            }

            .bubble-stage-count strong {
                font-size: clamp(44px, 16vw, 68px);
            }

            .bubble-stage-shell {
                padding-bottom: 76px;
            }

            #bubbleStage {
                max-height: none;
            }

            .bubble-caption {
                left: 18px;
                bottom: 58px;
                max-width: calc(100% - 36px);
            }

            .bubble-period-banner {
                top: 18px;
                max-width: calc(100% - 136px);
                text-align: center;
            }

            .bubble-gesture-guide {
                width: calc(100% - 24px);
                bottom: 12px;
            }
        }
    </style>

    @if ($bubbleMemories->isNotEmpty())
        <script>
            const memories = @json($bubbleMemories);
            const periods = @json($periods);
            const selectedPeriod = @json($selectedPeriod);
            const bubblesRoute = @json($bubbleBaseRoute);
            const svgNS = "http://www.w3.org/2000/svg";
            const defs = document.getElementById("bubbleDefs");
            const bubbleLayer = document.getElementById("bubbleLayer");
            const bubbleMapGrid = document.getElementById("bubbleMapGrid");
            const bubbleMapPeriods = document.getElementById("bubbleMapPeriods");
            const bubbleMapViewport = document.getElementById("bubbleMapViewport");
            const bubbleStage = document.getElementById("bubbleStage");
            const periodZones = new Map();
            const memoryGroupsByPeriod = new Map();
            const viewport = { width: 1400, height: 920 };
            const state = {
                scale: 1,
                minScale: 0.72,
                maxScale: 1.58,
                tx: 0,
                ty: 0,
                dragging: false,
                dragStarted: false,
                pointerId: null,
                startX: 0,
                startY: 0,
                startTx: 0,
                startTy: 0,
                touchMode: null,
                pinchDistance: 0,
                pinchMidpoint: null,
                worldBounds: null,
            };

            const periodAnchors = {
                "幼少期": { x: -280, y: 150 },
                "小学生": { x: 130, y: 810 },
                "中学生": { x: 760, y: 960 },
                "高校生": { x: 1380, y: 740 },
                "大学生": { x: 1820, y: 360 },
                "成人期": { x: 2280, y: 780 },
                "不明": { x: 2220, y: -40 },
            };

            function createSvg(tag, attrs = {}) {
                const element = document.createElementNS(svgNS, tag);
                Object.entries(attrs).forEach(([key, value]) => element.setAttribute(key, value));
                return element;
            }

            function toRgba(color, alpha) {
                if (!color.startsWith("#")) {
                    return color;
                }

                const normalized = color.length === 4
                    ? color.split("").map((char, index) => index === 0 ? "" : char + char).join("")
                    : color.slice(1);

                const red = Number.parseInt(normalized.slice(0, 2), 16);
                const green = Number.parseInt(normalized.slice(2, 4), 16);
                const blue = Number.parseInt(normalized.slice(4, 6), 16);
                return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
            }

            function addGradient(index, colors) {
                const id = `memGrad${index}`;
                const gradient = createSvg("radialGradient", {
                    id,
                    cx: "30%",
                    cy: "28%",
                    r: "70%",
                    "data-memory-gradient": "true",
                });

                gradient.appendChild(createSvg("stop", { offset: "0%", "stop-color": toRgba(colors[0], 0.96) }));
                gradient.appendChild(createSvg("stop", { offset: "55%", "stop-color": toRgba(colors[0], 0.54) }));
                gradient.appendChild(createSvg("stop", { offset: "100%", "stop-color": toRgba(colors[1], 0.74) }));
                defs.appendChild(gradient);

                return id;
            }

            function radiusFor(index) {
                const pattern = [118, 108, 104, 122, 100, 112, 102, 116, 100, 114, 106, 110];
                return pattern[index % pattern.length];
            }

            function getAnchor(period) {
                return periodAnchors[period] ?? { x: 900, y: 470 };
            }

            function buildWorldData() {
                const buckets = new Map();

                memories.forEach((memory) => {
                    if (!buckets.has(memory.period)) {
                        buckets.set(memory.period, []);
                    }

                    buckets.get(memory.period).push(memory);
                });

                const periodNodes = [];
                const memoryNodes = [];

                periods.forEach((period) => {
                    const anchor = getAnchor(period);
                    const items = buckets.get(period) ?? [];

                    if (selectedPeriod !== "すべて" && selectedPeriod !== period) {
                        return;
                    }

                    periodNodes.push({
                        period,
                        count: items.length,
                        x: anchor.x,
                        y: anchor.y,
                        radius: Math.max(228, 244 + (items.length * 8)),
                    });

                    items.forEach((memory, index) => {
                        const goldenAngle = Math.PI * (3 - Math.sqrt(5));
                        const bubbleRadius = radiusFor(index);
                        const spread = 112 + Math.sqrt(items.length + 2) * 52;
                        const orbit = items.length <= 1
                            ? 0
                            : Math.sqrt((index + 0.5) / items.length) * spread;
                        const angle = index * goldenAngle - Math.PI / 2;
                        const x = anchor.x + Math.cos(angle) * orbit;
                        const y = anchor.y + Math.sin(angle) * orbit;

                        memoryNodes.push({
                            memory,
                            x,
                            y,
                            radius: bubbleRadius,
                        });
                    });
                });

                return {
                    periodNodes,
                    memoryNodes,
                };
            }

            function buildBounds(world) {
                let minX = Infinity;
                let maxX = -Infinity;
                let minY = Infinity;
                let maxY = -Infinity;

                world.periodNodes.forEach((node) => {
                    minX = Math.min(minX, node.x - node.radius - 60);
                    maxX = Math.max(maxX, node.x + node.radius + 60);
                    minY = Math.min(minY, node.y - node.radius - 80);
                    maxY = Math.max(maxY, node.y + node.radius + 40);
                });

                world.memoryNodes.forEach((node) => {
                    minX = Math.min(minX, node.x - node.radius - 40);
                    maxX = Math.max(maxX, node.x + node.radius + 40);
                    minY = Math.min(minY, node.y - node.radius - 40);
                    maxY = Math.max(maxY, node.y + node.radius + 40);
                });

                return {
                    minX,
                    minY,
                    maxX,
                    maxY,
                    width: maxX - minX,
                    height: maxY - minY,
                };
            }

            function renderGrid(bounds) {
                const step = 240;

                for (let x = Math.floor(bounds.minX / step) * step; x <= bounds.maxX; x += step) {
                    bubbleMapGrid.appendChild(createSvg("line", {
                        x1: x,
                        y1: bounds.minY - 120,
                        x2: x,
                        y2: bounds.maxY + 120,
                        class: "bubble-map-grid-line",
                    }));
                }

                for (let y = Math.floor(bounds.minY / step) * step; y <= bounds.maxY; y += step) {
                    bubbleMapGrid.appendChild(createSvg("line", {
                        x1: bounds.minX - 120,
                        y1: y,
                        x2: bounds.maxX + 120,
                        y2: y,
                        class: "bubble-map-grid-line",
                    }));
                }
            }

            function renderPeriods(world) {
                world.periodNodes.forEach((node) => {
                    const zone = createSvg("g", {
                        class: "bubble-period-zone",
                        "data-period-zone": node.period,
                    });

                    zone.appendChild(createSvg("circle", {
                        cx: node.x,
                        cy: node.y,
                        r: node.radius,
                        class: "bubble-period-halo",
                    }));

                    zone.appendChild(createSvg("circle", {
                        cx: node.x,
                        cy: node.y,
                        r: 4.5,
                        class: "bubble-period-anchor",
                    }));

                    const periodName = createSvg("text", {
                        x: node.x,
                        y: node.y - node.radius - 26,
                        class: "bubble-period-name",
                    });
                    periodName.textContent = node.period;

                    const periodCount = createSvg("text", {
                        x: node.x,
                        y: node.y - node.radius - 8,
                        class: "bubble-period-count",
                    });
                    periodCount.textContent = `${node.count} memories`;

                    zone.appendChild(periodName);
                    zone.appendChild(periodCount);
                    bubbleMapPeriods.appendChild(zone);
                    periodZones.set(node.period, zone);
                });
            }

            function createLabel(x, y, text, radius) {
                const node = createSvg("text", {
                    x,
                    y,
                    class: "memory-label",
                    "font-size": Math.max(10, radius * 0.2),
                });

                node.textContent = text;
                return node;
            }

            function renderMemories(world) {
                world.memoryNodes.forEach((node, index) => {
                    const gradientId = addGradient(index + 1, node.memory.colors);
                    const wrapper = createSvg("g", {
                        class: "memory-ball-wrap",
                        "data-period-memory": node.memory.period,
                        style: `--bubble-appear-delay:${(0.03 * index).toFixed(2)}s`,
                    });
                    const group = createSvg("a", {
                        href: `/memories/${node.memory.id}`,
                        class: "memory-ball",
                        "data-period": node.memory.period,
                        "data-emotion": node.memory.emotion,
                        "data-tags": node.memory.tags.join(","),
                        "aria-label": `${node.memory.period}の記憶`,
                        style: [
                            `--bubble-rest-scale:${(0.93 + (index % 4) * 0.02).toFixed(2)}`,
                            `--bubble-rise-scale:${(1.02 + (index % 5) * 0.025).toFixed(2)}`,
                            `--bubble-hover-scale:${(1.12 + (index % 4) * 0.025).toFixed(2)}`,
                            `--bubble-duration:${(5.2 + (index % 5) * 0.55).toFixed(2)}s`,
                            `--bubble-delay:${(-index * 0.45).toFixed(2)}s`,
                        ].join(";"),
                    });
                    const body = createSvg("g", {
                        class: "memory-ball-body",
                    });

                    const hitArea = createSvg("circle", {
                        cx: node.x,
                        cy: node.y,
                        r: node.radius + 18,
                        fill: "rgba(255,255,255,0.001)",
                        "pointer-events": "all",
                    });

                    const aura = createSvg("circle", {
                        cx: node.x,
                        cy: node.y,
                        r: node.radius + 20,
                        fill: toRgba(node.memory.colors[1], 0.2),
                        filter: "url(#ballAura)",
                    });

                    const glow = createSvg("circle", {
                        cx: node.x,
                        cy: node.y,
                        r: node.radius + 11,
                        fill: "rgba(255,255,255,0.1)",
                        filter: "url(#ballAura)",
                    });

                    const circle = createSvg("circle", {
                        cx: node.x,
                        cy: node.y,
                        r: node.radius,
                        fill: `url(#${gradientId})`,
                        filter: "url(#ballShadow)",
                        opacity: "0.88",
                    });

                    const inner = createSvg("circle", {
                        cx: node.x - node.radius * 0.25,
                        cy: node.y - node.radius * 0.28,
                        r: Math.max(10, node.radius * 0.26),
                        fill: "rgba(255,255,255,0.30)",
                    });

                    const rim = createSvg("circle", {
                        cx: node.x,
                        cy: node.y,
                        r: node.radius - 1,
                        fill: "none",
                        stroke: "rgba(255,255,255,0.1)",
                        "stroke-width": "0.9",
                        filter: "url(#ballAura)",
                    });

                    const core = createSvg("circle", {
                        cx: node.x + node.radius * 0.2,
                        cy: node.y + node.radius * 0.14,
                        r: Math.max(10, node.radius * 0.42),
                        fill: toRgba(node.memory.colors[0], 0.12),
                    });

                    group.appendChild(hitArea);
                    body.appendChild(aura);
                    body.appendChild(glow);
                    body.appendChild(circle);
                    body.appendChild(core);
                    body.appendChild(inner);
                    body.appendChild(rim);
                    body.appendChild(createLabel(node.x, node.y, node.memory.label, node.radius));
                    group.appendChild(body);
                    group.appendChild(createSvg("title", {})).textContent = `${node.memory.period} / ${node.memory.emotion}\n${node.memory.content}`;

                    wrapper.appendChild(group);
                    wrapper.addEventListener("mouseenter", () => {
                        group.classList.add("is-hovered");
                    });
                    wrapper.addEventListener("mouseleave", () => {
                        group.classList.remove("is-hovered");
                    });
                    bubbleLayer.appendChild(wrapper);

                    if (!memoryGroupsByPeriod.has(node.memory.period)) {
                        memoryGroupsByPeriod.set(node.memory.period, []);
                    }

                    memoryGroupsByPeriod.get(node.memory.period).push(group);
                });
            }

            function setActivePeriod(period) {
                periodZones.forEach((zone, zonePeriod) => {
                    zone.classList.toggle("is-active", zonePeriod === period);
                });

                memoryGroupsByPeriod.forEach((groups, groupPeriod) => {
                    groups.forEach((group) => {
                        group.classList.toggle("is-period-hovered", groupPeriod === period);
                    });
                });
            }

            function clearActivePeriod() {
                periodZones.forEach((zone) => zone.classList.remove("is-active"));
                memoryGroupsByPeriod.forEach((groups) => {
                    groups.forEach((group) => group.classList.remove("is-period-hovered"));
                });
            }

            function jumpToPeriod(period) {
                const url = new URL(bubblesRoute, window.location.origin);
                url.searchParams.set("period", period);
                window.location.href = url.toString();
            }

            function svgPointFromClient(clientX, clientY) {
                const rect = bubbleStage.getBoundingClientRect();
                return {
                    x: ((clientX - rect.left) / rect.width) * viewport.width,
                    y: ((clientY - rect.top) / rect.height) * viewport.height,
                };
            }

            function clampTranslation(scale, tx, ty) {
                const bounds = state.worldBounds;
                const marginX = 120;
                const marginY = 120;
                const scaledWidth = bounds.width * scale;
                const scaledHeight = bounds.height * scale;

                if (scaledWidth <= viewport.width - (marginX * 2)) {
                    tx = (viewport.width - scaledWidth) / 2 - (bounds.minX * scale);
                } else {
                    const minTx = viewport.width - ((bounds.maxX * scale) + marginX);
                    const maxTx = marginX - (bounds.minX * scale);
                    tx = Math.min(maxTx, Math.max(minTx, tx));
                }

                if (scaledHeight <= viewport.height - (marginY * 2)) {
                    ty = (viewport.height - scaledHeight) / 2 - (bounds.minY * scale);
                } else {
                    const minTy = viewport.height - ((bounds.maxY * scale) + marginY);
                    const maxTy = marginY - (bounds.minY * scale);
                    ty = Math.min(maxTy, Math.max(minTy, ty));
                }

                return { tx, ty };
            }

            function applyTransform() {
                const clamped = clampTranslation(state.scale, state.tx, state.ty);
                state.tx = clamped.tx;
                state.ty = clamped.ty;
                bubbleMapViewport.setAttribute("transform", `matrix(${state.scale} 0 0 ${state.scale} ${state.tx} ${state.ty})`);
            }

            function frameInitialView() {
                const bounds = state.worldBounds;
                const paddingX = 420;
                const paddingY = 320;
                const scaleX = viewport.width / (bounds.width + paddingX);
                const scaleY = viewport.height / (bounds.height + paddingY);
                state.scale = Math.min(state.maxScale, Math.max(0.9, Math.min(scaleX, scaleY)));
                state.tx = (viewport.width / 2) - (((bounds.minX + bounds.maxX) / 2) * state.scale);
                state.ty = (viewport.height / 2) - (((bounds.minY + bounds.maxY) / 2) * state.scale);
                applyTransform();
            }

            function zoomTo(nextScale, point) {
                const clampedScale = Math.min(state.maxScale, Math.max(state.minScale, nextScale));
                const worldX = (point.x - state.tx) / state.scale;
                const worldY = (point.y - state.ty) / state.scale;
                state.tx = point.x - (worldX * clampedScale);
                state.ty = point.y - (worldY * clampedScale);
                state.scale = clampedScale;
                applyTransform();
            }

            function startDrag(point, pointerId = null) {
                state.dragging = true;
                state.dragStarted = false;
                state.pointerId = pointerId;
                state.startX = point.x;
                state.startY = point.y;
                state.startTx = state.tx;
                state.startTy = state.ty;
                bubbleStage.classList.add("is-dragging");
            }

            function updateDrag(point) {
                if (!state.dragging) {
                    return;
                }

                const dx = point.x - state.startX;
                const dy = point.y - state.startY;

                if (Math.abs(dx) > 2 || Math.abs(dy) > 2) {
                    state.dragStarted = true;
                }

                state.tx = state.startTx + dx;
                state.ty = state.startTy + dy;
                applyTransform();
            }

            function endDrag() {
                state.dragging = false;
                state.pointerId = null;
                bubbleStage.classList.remove("is-dragging");
            }

            const world = buildWorldData();
            state.worldBounds = buildBounds(world);
            renderGrid(state.worldBounds);
            renderPeriods(world);
            renderMemories(world);
            frameInitialView();
            if (selectedPeriod !== "すべて") {
                setActivePeriod(selectedPeriod);
            }

            periodZones.forEach((zone, period) => {
                zone.addEventListener("mouseenter", () => {
                    setActivePeriod(period);
                });

                zone.addEventListener("mouseleave", () => {
                    clearActivePeriod();
                });

                zone.addEventListener("click", () => {
                    jumpToPeriod(period);
                });
            });

            bubbleStage.addEventListener("wheel", (event) => {
                event.preventDefault();
                const point = svgPointFromClient(event.clientX, event.clientY);
                const factor = event.deltaY < 0 ? 1.12 : 0.9;
                zoomTo(state.scale * factor, point);
            }, { passive: false });

            bubbleStage.addEventListener("pointerdown", (event) => {
                if (event.target.closest(".memory-ball") || event.target.closest(".bubble-period-zone")) {
                    return;
                }

                const point = svgPointFromClient(event.clientX, event.clientY);
                startDrag(point, event.pointerId);
            });

            bubbleStage.addEventListener("pointermove", (event) => {
                if (!state.dragging || state.pointerId !== event.pointerId) {
                    return;
                }

                updateDrag(svgPointFromClient(event.clientX, event.clientY));
            });

            bubbleStage.addEventListener("pointerup", () => {
                endDrag();
            });

            bubbleStage.addEventListener("pointerleave", () => {
                endDrag();
            });

            bubbleStage.addEventListener("touchstart", (event) => {
                if (event.touches.length === 2) {
                    const first = svgPointFromClient(event.touches[0].clientX, event.touches[0].clientY);
                    const second = svgPointFromClient(event.touches[1].clientX, event.touches[1].clientY);
                    state.touchMode = "pinch";
                    state.pinchDistance = Math.hypot(first.x - second.x, first.y - second.y);
                    state.pinchMidpoint = {
                        x: (first.x + second.x) / 2,
                        y: (first.y + second.y) / 2,
                    };
                    return;
                }

                if (event.touches.length === 1 && !event.target.closest(".memory-ball") && !event.target.closest(".bubble-period-zone")) {
                    state.touchMode = "drag";
                    startDrag(svgPointFromClient(event.touches[0].clientX, event.touches[0].clientY));
                }
            }, { passive: true });

            bubbleStage.addEventListener("touchmove", (event) => {
                if (state.touchMode === "pinch" && event.touches.length === 2) {
                    const first = svgPointFromClient(event.touches[0].clientX, event.touches[0].clientY);
                    const second = svgPointFromClient(event.touches[1].clientX, event.touches[1].clientY);
                    const nextDistance = Math.hypot(first.x - second.x, first.y - second.y);
                    const midpoint = {
                        x: (first.x + second.x) / 2,
                        y: (first.y + second.y) / 2,
                    };

                    if (state.pinchDistance > 0) {
                        const factor = nextDistance / state.pinchDistance;
                        zoomTo(state.scale * factor, midpoint);
                    }

                    state.pinchDistance = nextDistance;
                    state.pinchMidpoint = midpoint;
                    return;
                }

                if (state.touchMode === "drag" && event.touches.length === 1) {
                    updateDrag(svgPointFromClient(event.touches[0].clientX, event.touches[0].clientY));
                }
            }, { passive: true });

            bubbleStage.addEventListener("touchend", () => {
                state.touchMode = null;
                state.pinchDistance = 0;
                endDrag();
            });
        </script>
    @endif
@endsection
