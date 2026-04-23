@extends('layouts.app')

@section('title', 'YOUの記憶 | 分身AI MVP')

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
            <div class="bubble-stage-copy">
                <span class="eyebrow">PERSONAL MEMORY ARCHIVE</span>
                <h1>YOUの記憶</h1>
                <div class="hero-actions">
                    <a class="btn btn-secondary" href="{{ route('memories.index') }}">一覧へ戻る</a>
                </div>
            </div>

            <div class="bubble-stage-rail">
                <div class="bubble-stage-side bubble-stage-rail-card">
                    <section class="bubble-rail-section bubble-stage-count">
                        <span class="bubble-side-label">全記憶数</span>
                        <strong>{{ $matchingCount }}</strong>
                    </section>

                    <section class="bubble-rail-section bubble-stage-nav">
                        <span class="bubble-side-label">記憶玉の階層</span>
                        <strong data-current-layer-order>1個目 / 全{{ $layerCount }}個</strong>
                        <div class="bubble-nav-meta">
                            <div>
                                <span>表示中の記憶</span>
                                <b data-current-range>{{ $currentRangeStart }}-{{ $currentRangeEnd }}件目</b>
                            </div>
                            <div>
                                <span>この階層の件数</span>
                                <b data-current-count>{{ $displayCount }}件</b>
                            </div>
                        </div>
                        <div class="bubble-nav-actions">
                            <button class="bubble-mini-btn" type="button" data-layer-action="back" @disabled(! $hasPreviousLayer)>手前へ戻る</button>
                            <button class="bubble-mini-btn" type="button" data-layer-action="next" @disabled(! $hasNextLayer)>奥をひらく</button>
                        </div>
                    </section>

                    <details class="bubble-rail-section bubble-stage-filter">
                        <summary class="btn btn-secondary">年代別で表示</summary>
                        <form method="get" action="{{ route('memories.bubbles') }}" class="bubble-filter-form">
                            <label for="period" class="bubble-side-label">年代を選択</label>
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
                    </details>

                    <section class="bubble-rail-section bubble-stage-status">
                        <span class="bubble-side-label">操作の状態</span>
                        <p data-gesture-status>いちばん手前の記憶玉を表示しています。</p>
                    </section>

                    <section class="bubble-rail-section">
                        <a class="btn btn-secondary bubble-rail-btn" href="{{ route('memories.create.preview') }}">記憶を追加</a>
                    </section>

                    <section class="bubble-rail-section">
                        <a class="btn btn-secondary bubble-rail-btn" href="{{ route('memories.index') }}">一覧を見る</a>
                    </section>
                </div>
            </div>

            <div class="bubble-stage-shell">
                <div class="bubble-caption">MEMORY BUBBLE / TAP TO OPEN A MEMORY</div>
                @if ($selectedPeriod !== 'すべて')
                    <div class="bubble-period-banner">{{ $selectedPeriod }}</div>
                @endif
                <svg id="bubbleStage" viewBox="0 0 1400 980" xmlns="http://www.w3.org/2000/svg" aria-label="YOUの記憶">
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

                    <g id="bubbleStackLayers"></g>

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
                    <g id="bubbleLayer"></g>
                </svg>

                <div class="bubble-gesture-guide">
                    <span class="bubble-gesture-pill">
                        <i></i>
                        2本指でひろげると奥の記憶玉へ
                    </span>
                    <span class="bubble-gesture-pill">
                        <i></i>
                        つまむ / ホイール下で手前へ戻る
                    </span>
                </div>
            </div>
        @endif
    </section>

    <style>
        .bubble-stage-panel {
            position: relative;
            min-height: min(980px, calc(100vh - 72px));
            padding: 0;
            overflow: hidden;
            background:
                radial-gradient(circle at 18% 18%, rgba(86, 132, 255, 0.18), transparent 20%),
                radial-gradient(circle at 82% 16%, rgba(126, 209, 255, 0.14), transparent 18%),
                radial-gradient(circle at 50% 72%, rgba(88, 108, 255, 0.12), transparent 26%),
                linear-gradient(160deg, #02040b 0%, #050916 48%, #0a1124 100%);
            color: rgba(238, 245, 255, 0.94);
        }

        .bubble-stage-copy {
            position: absolute;
            top: 28px;
            left: 28px;
            z-index: 3;
            max-width: 280px;
        }

        .bubble-stage-copy h1 {
            margin: 16px 0 16px;
            font-size: clamp(30px, 3.4vw, 50px);
            color: rgba(245, 249, 255, 0.96);
            letter-spacing: 0.03em;
            white-space: nowrap;
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
        .bubble-stage-panel .bubble-mini-btn:hover:not(:disabled) {
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

        .bubble-stage-panel .btn-secondary,
        .bubble-mini-btn {
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            border-color: rgba(166, 204, 255, 0.16);
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.28);
        }

        .bubble-stage-panel .btn-primary:hover,
        .bubble-stage-panel .btn-secondary:hover,
        .bubble-stage-panel .bubble-mini-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
        }

        .bubble-mini-btn:disabled {
            opacity: 0.34;
            cursor: default;
            pointer-events: none;
        }

        .bubble-stage-shell {
            position: relative;
            width: 100%;
            height: 100%;
            padding-bottom: 84px;
        }

        .bubble-stage-rail {
            position: absolute;
            top: 184px;
            right: 24px;
            z-index: 3;
            width: min(248px, 25vw);
        }

        .bubble-stage-side {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: 16px 18px;
            border-radius: 20px;
            background: transparent;
        }

        .bubble-stage-rail-card {
            display: grid;
            gap: 0;
            align-content: start;
        }

        .bubble-rail-section {
            padding: 14px 0;
        }

        .bubble-rail-section + .bubble-rail-section {
            border-top: 1px solid rgba(171, 205, 255, 0.12);
        }

        .bubble-stage-count {
            text-align: right;
            padding-top: 0;
        }

        .bubble-stage-count strong {
            display: block;
            color: rgba(245, 249, 255, 0.98);
            font-size: clamp(24px, 2.1vw, 32px);
            line-height: 1.05;
        }

        .bubble-stage-nav strong {
            display: block;
            color: rgba(245, 249, 255, 0.98);
            font-size: 17px;
            margin-bottom: 12px;
        }

        .bubble-nav-meta {
            display: grid;
            gap: 10px;
            margin-bottom: 12px;
        }

        .bubble-nav-meta div {
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(12, 20, 40, 0.52);
            border: 1px solid rgba(166, 204, 255, 0.08);
        }

        .bubble-nav-meta span {
            display: block;
            margin-bottom: 4px;
            color: rgba(188, 214, 255, 0.62);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .bubble-nav-meta b {
            color: rgba(245, 249, 255, 0.96);
            font-size: 14px;
        }

        .bubble-nav-actions {
            display: flex;
            gap: 8px;
        }

        .bubble-mini-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 10px;
            font-size: 11px;
            text-decoration: none;
            white-space: nowrap;
            border: 1px solid rgba(178, 210, 255, 0.22);
        }

        .bubble-stage-filter summary {
            list-style: none;
            width: 100%;
        }

        .bubble-stage-rail .btn {
            width: 100%;
            padding: 9px 12px;
            font-size: 11px;
            border-radius: 11px;
        }

        .bubble-stage-rail .bubble-side-label {
            font-size: 11px;
            margin-bottom: 5px;
        }

        .bubble-stage-rail .bubble-select {
            padding: 10px 12px;
            font-size: 12px;
        }

        .bubble-stage-filter summary::-webkit-details-marker {
            display: none;
        }

        .bubble-filter-form {
            display: grid;
            gap: 12px;
            margin-top: 14px;
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

        .bubble-stage-status p {
            margin: 0;
            color: rgba(225, 236, 255, 0.78);
            font-size: 13px;
            line-height: 1.7;
        }

        .bubble-caption {
            position: absolute;
            left: 28px;
            bottom: 56px;
            z-index: 2;
            color: rgba(176, 202, 245, 0.58);
            font-size: 12px;
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
            touch-action: none;
        }

        .bubble-shell-breath {
            transform-box: fill-box;
            transform-origin: center;
            animation: shellPulse var(--shell-duration, 7.8s) ease-in-out var(--shell-delay, 0s) infinite;
            will-change: transform, opacity;
        }

        .memory-ball-wrap {
            transform-box: fill-box;
            transform-origin: center;
            will-change: opacity;
            opacity: 0;
            animation: bubbleReveal 0.68s ease var(--bubble-appear-delay, 0s) forwards;
        }

        .memory-ball {
            cursor: pointer;
        }

        .memory-ball.is-hovered .memory-ball-body {
            animation: none;
            transform: scale(var(--bubble-hover-scale, 1.15));
            filter: brightness(1.12) saturate(1.12) drop-shadow(0 26px 34px rgba(108, 127, 169, 0.2));
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

        .bubble-depth-label {
            fill: rgba(236, 244, 255, 0.86);
            font-size: 12px;
            font-weight: 700;
            text-anchor: middle;
        }

        .bubble-gesture-guide {
            position: absolute;
            left: 50%;
            bottom: 14px;
            z-index: 2;
            transform: translateX(-50%);
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            width: min(760px, calc(100% - 64px));
        }

        .bubble-gesture-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(11, 18, 38, 0.74);
            border: 1px solid rgba(177, 212, 255, 0.14);
            color: rgba(228, 238, 255, 0.88);
            font-size: 11px;
            box-shadow: 0 10px 22px rgba(5, 9, 22, 0.2);
            backdrop-filter: blur(10px);
        }

        .bubble-gesture-pill i {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(170, 222, 255, 1), rgba(255, 193, 214, 0.94));
            box-shadow: 0 0 12px rgba(167, 216, 255, 0.44);
        }

        @keyframes bubblePulse {
            0% { transform: scale(var(--bubble-rest-scale, 0.96)); }
            50% { transform: scale(var(--bubble-rise-scale, 1.06)); }
            100% { transform: scale(var(--bubble-rest-scale, 0.96)); }
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
            .bubble-stage-panel {
                min-height: 820px;
            }

            .bubble-stage-rail {
                top: 170px;
                width: min(224px, 28vw);
            }
        }

        @media (max-width: 760px) {
            .bubble-stage-panel {
                min-height: auto;
                padding-top: 156px;
            }

            .bubble-stage-copy,
            .bubble-stage-rail {
                position: static;
                width: auto;
                margin: 0 18px 14px;
            }

            .bubble-stage-shell {
                padding-bottom: 112px;
            }

            .bubble-stage-count {
                text-align: left;
            }

            #bubbleStage {
                max-height: none;
            }

            .bubble-caption {
                left: 18px;
                bottom: 82px;
            }

            .bubble-period-banner {
                top: 18px;
                max-width: calc(100% - 136px);
                text-align: center;
            }

            .bubble-gesture-guide {
                width: calc(100% - 24px);
                bottom: 16px;
            }
        }
    </style>

    @if ($bubbleMemories->isNotEmpty())
        <script>
            const bubbleLayers = @json($bubbleLayers);
            const svgNS = "http://www.w3.org/2000/svg";
            const bubbleLayerNode = document.getElementById("bubbleLayer");
            const bubbleStackNode = document.getElementById("bubbleStackLayers");
            const defsNode = document.getElementById("bubbleDefs");
            const stageNode = document.getElementById("bubbleStage");
            const layerOrderNode = document.querySelector("[data-current-layer-order]");
            const currentRangeNode = document.querySelector("[data-current-range]");
            const currentCountNode = document.querySelector("[data-current-count]");
            const gestureStatusNode = document.querySelector("[data-gesture-status]");
            const layerButtons = document.querySelectorAll("[data-layer-action]");
            const stage = { x: 700, y: 470, r: 362 };
            let currentLayerIndex = 0;
            let wheelTravel = 0;
            let pinchDistance = null;

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
                const gradient = createSvg("radialGradient", {
                    id: `memGrad${currentLayerIndex + 1}_${index}`,
                    cx: "30%",
                    cy: "28%",
                    r: "70%",
                    "data-memory-gradient": "true"
                });

                gradient.appendChild(createSvg("stop", { offset: "0%", "stop-color": toRgba(colors[0], 0.96) }));
                gradient.appendChild(createSvg("stop", { offset: "55%", "stop-color": toRgba(colors[0], 0.54) }));
                gradient.appendChild(createSvg("stop", { offset: "100%", "stop-color": toRgba(colors[1], 0.74) }));
                defsNode.appendChild(gradient);

                return gradient.getAttribute("id");
            }

            function clearLayerGraphics() {
                bubbleLayerNode.replaceChildren();
                bubbleStackNode.replaceChildren();
                defsNode.querySelectorAll('[data-memory-gradient="true"]').forEach((node) => node.remove());
            }

            function getRadius(index) {
                const pattern = [110, 98, 92, 104, 88, 96, 90, 102, 94, 100];
                return pattern[index % pattern.length];
            }

            function getPosition(index, total, radius) {
                if (total === 1) {
                    return { x: stage.x, y: stage.y };
                }

                const goldenAngle = Math.PI * (3 - Math.sqrt(5));
                const t = (index + 0.5) / total;
                const spread = stage.r - radius - 20;
                const radial = Math.sqrt(t) * spread * 0.98;
                const angle = index * goldenAngle - Math.PI / 2;

                return {
                    x: stage.x + Math.cos(angle) * radial,
                    y: stage.y + Math.sin(angle) * radial
                };
            }

            function createLabel(x, y, text, radius) {
                const node = createSvg("text", {
                    x,
                    y,
                    class: "memory-label",
                    "font-size": Math.max(10, radius * 0.2)
                });

                node.textContent = text;
                return node;
            }

            function renderStackLayers() {
                const hiddenLayerCount = Math.max(0, bubbleLayers.length - currentLayerIndex - 1);
                const previewCount = Math.min(hiddenLayerCount, 4);

                for (let previewIndex = previewCount; previewIndex >= 1; previewIndex -= 1) {
                    const stackOffsetX = 28 * previewIndex;
                    const stackOffsetY = -22 * previewIndex;
                    const stackRadius = 372 - (14 * previewIndex);
                    const stackOpacity = 0.12 + (0.025 * (previewCount - previewIndex));
                    const depthLabel = createSvg("text", {
                        x: 700 + stackOffsetX,
                        y: 470 + stackOffsetY + 6,
                        class: "bubble-depth-label"
                    });

                    depthLabel.textContent = `+${hiddenLayerCount - previewIndex + 1}`;

                    bubbleStackNode.appendChild(createSvg("ellipse", {
                        cx: 714 + stackOffsetX,
                        cy: 548 + stackOffsetY,
                        rx: 176 - (12 * previewIndex),
                        ry: 52 - (4 * previewIndex),
                        fill: "rgba(20, 48, 86, 0.28)",
                        filter: "url(#stackShadow)",
                        class: "bubble-shell-breath",
                        style: `--shell-duration:${8.2 + (previewIndex * 0.6)}s;--shell-delay:-${0.6 * previewIndex}s;`
                    }));

                    const group = createSvg("g", {
                        filter: "url(#shellGlow)",
                        class: "bubble-shell-breath",
                        style: `--shell-duration:${8.2 + (previewIndex * 0.6)}s;--shell-delay:-${0.6 * previewIndex}s;`
                    });

                    group.appendChild(createSvg("circle", {
                        cx: 700 + stackOffsetX,
                        cy: 470 + stackOffsetY,
                        r: stackRadius,
                        fill: `rgba(130, 194, 255, ${stackOpacity})`
                    }));
                    group.appendChild(createSvg("ellipse", {
                        cx: 624 + stackOffsetX,
                        cy: 374 + stackOffsetY,
                        rx: 108 - (8 * previewIndex),
                        ry: 40 - (3 * previewIndex),
                        fill: "rgba(255,255,255,0.10)",
                        transform: `rotate(-18 ${624 + stackOffsetX} ${374 + stackOffsetY})`
                    }));
                    group.appendChild(createSvg("circle", {
                        cx: 700 + stackOffsetX,
                        cy: 470 + stackOffsetY,
                        r: stackRadius - 10,
                        fill: "none",
                        stroke: "rgba(214, 236, 255, 0.14)",
                        "stroke-width": "2"
                    }));
                    group.appendChild(depthLabel);

                    bubbleStackNode.appendChild(group);
                }
            }

            function renderVisibleLayer() {
                clearLayerGraphics();
                renderStackLayers();

                const layer = bubbleLayers[currentLayerIndex] || bubbleLayers[0];

                layer.memories.forEach((memory, index) => {
                    const radius = getRadius(index);
                    const position = getPosition(index, layer.memories.length, radius);
                    const gradientId = addGradient(index + 1, memory.colors);
                    const wrapper = createSvg("g", {
                        class: "memory-ball-wrap",
                        style: `--bubble-appear-delay:${(0.05 * index).toFixed(2)}s`
                    });
                    const group = createSvg("a", {
                        href: `/memories/${memory.id}`,
                        class: "memory-ball",
                        "data-sequence": memory.sequence,
                        "aria-label": `${memory.sequence}個目の記憶`,
                        style: [
                            `--bubble-rest-scale:${(0.94 + (index % 3) * 0.02).toFixed(2)}`,
                            `--bubble-rise-scale:${(1.03 + (index % 4) * 0.02).toFixed(2)}`,
                            `--bubble-hover-scale:${(1.11 + (index % 4) * 0.02).toFixed(2)}`,
                            `--bubble-duration:${(5.4 + (index % 4) * 0.5).toFixed(2)}s`,
                            `--bubble-delay:${(-index * 0.4).toFixed(2)}s`
                        ].join(";")
                    });
                    const body = createSvg("g", { class: "memory-ball-body" });

                    body.appendChild(createSvg("circle", {
                        cx: position.x,
                        cy: position.y,
                        r: radius + 20,
                        fill: toRgba(memory.colors[1], 0.2),
                        filter: "url(#ballAura)"
                    }));
                    body.appendChild(createSvg("circle", {
                        cx: position.x,
                        cy: position.y,
                        r: radius + 11,
                        fill: "rgba(255,255,255,0.1)",
                        filter: "url(#ballAura)"
                    }));
                    body.appendChild(createSvg("circle", {
                        cx: position.x,
                        cy: position.y,
                        r: radius,
                        fill: `url(#${gradientId})`,
                        filter: "url(#ballShadow)",
                        opacity: "0.88"
                    }));
                    body.appendChild(createSvg("circle", {
                        cx: position.x - radius * 0.25,
                        cy: position.y - radius * 0.28,
                        r: Math.max(10, radius * 0.26),
                        fill: "rgba(255,255,255,0.30)"
                    }));
                    body.appendChild(createSvg("circle", {
                        cx: position.x + radius * 0.2,
                        cy: position.y + radius * 0.14,
                        r: Math.max(10, radius * 0.42),
                        fill: toRgba(memory.colors[0], 0.12)
                    }));
                    body.appendChild(createSvg("circle", {
                        cx: position.x,
                        cy: position.y,
                        r: radius - 1,
                        fill: "none",
                        stroke: "rgba(255,255,255,0.1)",
                        "stroke-width": "0.9",
                        filter: "url(#ballAura)"
                    }));
                    body.appendChild(createLabel(position.x, position.y, memory.label, radius));

                    group.appendChild(createSvg("circle", {
                        cx: position.x,
                        cy: position.y,
                        r: radius + 18,
                        fill: "rgba(255,255,255,0.001)",
                        "pointer-events": "all"
                    }));
                    group.appendChild(body);
                    group.appendChild(createSvg("title")).textContent = `${memory.sequence}個目 / ${memory.period} / ${memory.emotion}\n${memory.content}`;
                    wrapper.appendChild(group);

                    wrapper.addEventListener("mouseenter", () => group.classList.add("is-hovered"));
                    wrapper.addEventListener("mouseleave", () => group.classList.remove("is-hovered"));
                    bubbleLayerNode.appendChild(wrapper);
                });

                updateRailStatus();
            }

            function updateRailStatus() {
                const layer = bubbleLayers[currentLayerIndex] || bubbleLayers[0];
                const isFirst = currentLayerIndex === 0;
                const isLast = currentLayerIndex === bubbleLayers.length - 1;

                if (layerOrderNode) {
                    layerOrderNode.textContent = `${layer.number}個目 / 全${bubbleLayers.length}個`;
                }

                if (currentRangeNode) {
                    currentRangeNode.textContent = `${layer.startIndex}-${layer.endIndex}件目`;
                }

                if (currentCountNode) {
                    currentCountNode.textContent = `${layer.memories.length}件`;
                }

                if (gestureStatusNode) {
                    if (isFirst && isLast) {
                        gestureStatusNode.textContent = "この記憶玉だけで全件を表示しています。";
                    } else if (isFirst) {
                        gestureStatusNode.textContent = "いちばん手前の記憶玉です。ひろげると奥の階層が見えます。";
                    } else if (isLast) {
                        gestureStatusNode.textContent = "いちばん奥まで到達しました。つまむと手前へ戻れます。";
                    } else {
                        gestureStatusNode.textContent = `奥から ${bubbleLayers.length - layer.number} 個ぶん残っています。`;
                    }
                }

                layerButtons.forEach((button) => {
                    if (!(button instanceof HTMLButtonElement)) {
                        return;
                    }

                    if (button.dataset.layerAction === "back") {
                        button.disabled = isFirst;
                    }

                    if (button.dataset.layerAction === "next") {
                        button.disabled = isLast;
                    }
                });
            }

            function changeLayer(direction) {
                const nextIndex = Math.max(0, Math.min(bubbleLayers.length - 1, currentLayerIndex + direction));

                if (nextIndex === currentLayerIndex) {
                    return;
                }

                currentLayerIndex = nextIndex;
                renderVisibleLayer();
            }

            function handleWheel(event) {
                event.preventDefault();

                if ((wheelTravel > 0 && event.deltaY < 0) || (wheelTravel < 0 && event.deltaY > 0)) {
                    wheelTravel = 0;
                }

                wheelTravel += event.deltaY;

                if (wheelTravel <= -140) {
                    changeLayer(1);
                    wheelTravel = 0;
                }

                if (wheelTravel >= 140) {
                    changeLayer(-1);
                    wheelTravel = 0;
                }
            }

            function getTouchDistance(touches) {
                const dx = touches[0].clientX - touches[1].clientX;
                const dy = touches[0].clientY - touches[1].clientY;
                return Math.hypot(dx, dy);
            }

            stageNode.addEventListener("wheel", handleWheel, { passive: false });

            stageNode.addEventListener("touchstart", (event) => {
                if (event.touches.length === 2) {
                    pinchDistance = getTouchDistance(event.touches);
                }
            }, { passive: true });

            stageNode.addEventListener("touchmove", (event) => {
                if (event.touches.length !== 2 || pinchDistance === null) {
                    return;
                }

                const nextDistance = getTouchDistance(event.touches);
                const delta = nextDistance - pinchDistance;

                if (delta > 26) {
                    changeLayer(1);
                    pinchDistance = nextDistance;
                }

                if (delta < -26) {
                    changeLayer(-1);
                    pinchDistance = nextDistance;
                }
            }, { passive: true });

            stageNode.addEventListener("touchend", () => {
                pinchDistance = null;
            });

            layerButtons.forEach((button) => {
                button.addEventListener("click", () => {
                    if (button.dataset.layerAction === "next") {
                        changeLayer(1);
                    }

                    if (button.dataset.layerAction === "back") {
                        changeLayer(-1);
                    }
                });
            });

            renderVisibleLayer();
        </script>
    @endif
@endsection
