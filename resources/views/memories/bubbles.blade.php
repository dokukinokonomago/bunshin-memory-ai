@extends('layouts.app')

@section('title', 'あなたの記憶 | 分身AI MVP')

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
            @endphp
            <div class="bubble-stage-copy">
                <span class="eyebrow">Memory Bubble View</span>
                <h1>あなたの記憶</h1>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="{{ route('memories.index') }}">一覧へ戻る</a>
                    <a class="btn btn-secondary" href="{{ route('memories.create') }}">記憶を追加</a>
                </div>
            </div>

            <div class="bubble-stage-rail">
                <div class="bubble-stage-side bubble-stage-rail-card">
                    <section class="bubble-rail-section bubble-stage-count">
                        <span class="bubble-side-label">全記憶数</span>
                        <strong>{{ $matchingCount }}</strong>
                    </section>

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

                    <section class="bubble-rail-section">
                        <a class="btn btn-secondary bubble-rail-btn" href="#" aria-disabled="true">記憶を追加</a>
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

                    <section class="bubble-rail-section">
                        <a class="btn btn-secondary bubble-rail-btn" href="#" aria-disabled="true">記憶と話す</a>
                    </section>
                </div>
            </div>

            <div class="bubble-stage-shell">
                <div class="bubble-caption">MEMORY BUBBLE / CLICK A MEMORY</div>
                @if ($selectedPeriod !== 'すべて')
                    <div class="bubble-period-banner">{{ $selectedPeriod }}</div>
                @endif
                <svg id="bubbleStage" viewBox="0 0 1400 920" xmlns="http://www.w3.org/2000/svg" aria-label="あなたの記憶">
                    <defs>
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
                                class="bubble-stack-shadow"
                            ></ellipse>
                            <g filter="url(#shellGlow)" class="bubble-stack-layer">
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

                    <g filter="url(#shellGlow)">
                        <circle cx="700" cy="470" r="372" fill="rgba(124, 187, 255, 0.22)"></circle>
                    </g>

                    <ellipse cx="578" cy="340" rx="120" ry="58" fill="rgba(255,255,255,0.16)" transform="rotate(-20 578 340)"></ellipse>
                    <ellipse cx="820" cy="585" rx="38" ry="18" fill="rgba(255,255,255,0.10)" transform="rotate(14 820 585)"></ellipse>

                    <circle cx="700" cy="470" r="324" fill="rgba(202,228,255,0.04)"></circle>
                    <g id="bubbleLayer"></g>
                </svg>
            </div>
        @endif
    </section>

    <style>
        .bubble-stage-panel {
            position: relative;
            min-height: min(920px, calc(100vh - 72px));
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
            font-size: clamp(34px, 4vw, 58px);
            color: rgba(245, 249, 255, 0.96);
            letter-spacing: 0.03em;
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

        .bubble-stage-shell {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .bubble-stage-rail {
            position: absolute;
            top: 184px;
            right: 24px;
            z-index: 3;
            width: min(236px, 24vw);
            height: min(430px, calc(100% - 240px));
        }

        .bubble-stage-side {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(11, 18, 36, 0.48);
            border: 1px solid rgba(171, 205, 255, 0.14);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 36px rgba(3, 6, 18, 0.22);
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
            text-align: right;
            padding-top: 0;
        }

        .bubble-stage-count strong {
            display: block;
            color: rgba(245, 249, 255, 0.98);
            font-size: clamp(24px, 2.1vw, 32px);
            line-height: 1.05;
        }

        .bubble-stage-filter {
            padding-bottom: 0;
        }

        .bubble-rail-btn {
            width: 100%;
        }

        .bubble-stage-nav {
            padding-bottom: 2px;
        }

        .bubble-stage-nav strong {
            display: block;
            color: rgba(245, 249, 255, 0.98);
            font-size: 17px;
            margin-bottom: 10px;
        }

        .bubble-nav-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .bubble-mini-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 12px;
            border: 1px solid rgba(178, 210, 255, 0.22);
            background: linear-gradient(135deg, rgba(19, 30, 57, 0.96), rgba(10, 17, 35, 0.96));
            color: rgba(240, 246, 255, 0.92);
            font-size: 12px;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 10px 22px rgba(6, 10, 24, 0.22);
        }

        .bubble-mini-btn.is-disabled {
            opacity: 0.34;
            pointer-events: none;
        }

        .bubble-stage-filter summary {
            list-style: none;
            width: 100%;
        }

        .bubble-stage-filter summary::-webkit-details-marker {
            display: none;
        }

        .bubble-stage-filter[open] {
            width: 100%;
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

        .bubble-caption {
            position: absolute;
            left: 28px;
            bottom: 22px;
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
        }

        .memory-ball {
            cursor: pointer;
            transform-box: fill-box;
            transform-origin: center;
            will-change: transform, filter;
            animation: bubblePulse var(--bubble-duration, 6.8s) ease-in-out var(--bubble-delay, 0s) infinite;
            transition: transform 0.42s cubic-bezier(0.2, 0.7, 0.2, 1), filter 0.42s cubic-bezier(0.2, 0.7, 0.2, 1);
        }

        .memory-ball:hover {
            animation-play-state: paused;
            transform: scale(3);
            filter: brightness(1.12) saturate(1.12) drop-shadow(0 26px 34px rgba(108, 127, 169, 0.2));
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

        @media (max-width: 980px) {
            .bubble-stage-panel {
                min-height: 760px;
            }

            .bubble-stage-rail {
                top: 170px;
                width: min(220px, 27vw);
                height: min(408px, calc(100% - 224px));
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

            .bubble-stage-side {
                width: auto;
            }

            .bubble-stage-count {
                text-align: left;
            }

            .bubble-stage-filter[open] {
                width: auto;
            }

            .bubble-stage-shell {
                padding-bottom: 16px;
            }

            #bubbleStage {
                max-height: none;
            }

            .bubble-caption {
                left: 18px;
                bottom: 18px;
            }

            .bubble-period-banner {
                top: 18px;
                max-width: calc(100% - 136px);
                text-align: center;
            }
        }
    </style>

    @if ($bubbleMemories->isNotEmpty())
        <script>
            const memories = @json($bubbleMemories);
            const svgNS = "http://www.w3.org/2000/svg";
            const bubbleLayer = document.getElementById("bubbleLayer");
            const stage = {
                x: 700,
                y: 470,
                r: 362
            };

            function createSvg(tag, attrs = {}) {
                const element = document.createElementNS(svgNS, tag);
                Object.entries(attrs).forEach(([key, value]) => element.setAttribute(key, value));
                return element;
            }

            function addGradient(index, colors) {
                const defs = document.querySelector("#bubbleStage defs");
                const id = `memGrad${index}`;
                const gradient = createSvg("radialGradient", {
                    id,
                    cx: "30%",
                    cy: "28%",
                    r: "70%"
                });

                gradient.appendChild(createSvg("stop", { offset: "0%", "stop-color": toRgba(colors[0], 0.96) }));
                gradient.appendChild(createSvg("stop", { offset: "55%", "stop-color": toRgba(colors[0], 0.54) }));
                gradient.appendChild(createSvg("stop", { offset: "100%", "stop-color": toRgba(colors[1], 0.74) }));
                defs.appendChild(gradient);

                return id;
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

            function getPosition(index, total, radius) {
                const goldenAngle = Math.PI * (3 - Math.sqrt(5));
                const t = (index + 0.5) / total;
                const spread = stage.r - radius - 16;
                const radial = Math.sqrt(t) * spread * 0.99;
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

            memories.forEach((memory, index) => {
                const radiusPattern = [108, 96, 92, 112, 86, 98, 90, 104, 88, 100, 91, 95];
                const radius = radiusPattern[index % radiusPattern.length];
                const position = getPosition(index, memories.length, radius);
                const gradientId = addGradient(index + 1, memory.colors);

                const group = createSvg("a", {
                    href: `/memories/${memory.id}`,
                    class: "memory-ball",
                    "data-period": memory.period,
                    "data-emotion": memory.emotion,
                    "data-tags": memory.tags.join(","),
                    "aria-label": `${memory.period}の記憶`,
                    style: [
                        `--bubble-rest-scale:${(0.93 + (index % 4) * 0.02).toFixed(2)}`,
                        `--bubble-rise-scale:${(1.02 + (index % 5) * 0.025).toFixed(2)}`,
                        `--bubble-duration:${(5.2 + (index % 5) * 0.55).toFixed(2)}s`,
                        `--bubble-delay:${(-index * 0.45).toFixed(2)}s`
                    ].join(";")
                });

                const aura = createSvg("circle", {
                    cx: position.x,
                    cy: position.y,
                    r: radius + 20,
                    fill: toRgba(memory.colors[1], 0.2),
                    filter: "url(#ballAura)"
                });

                const glow = createSvg("circle", {
                    cx: position.x,
                    cy: position.y,
                    r: radius + 11,
                    fill: "rgba(255,255,255,0.1)",
                    filter: "url(#ballAura)"
                });

                const circle = createSvg("circle", {
                    cx: position.x,
                    cy: position.y,
                    r: radius,
                    fill: `url(#${gradientId})`,
                    filter: "url(#ballShadow)",
                    opacity: "0.88"
                });

                const inner = createSvg("circle", {
                    cx: position.x - radius * 0.25,
                    cy: position.y - radius * 0.28,
                    r: Math.max(10, radius * 0.26),
                    fill: "rgba(255,255,255,0.30)"
                });

                const rim = createSvg("circle", {
                    cx: position.x,
                    cy: position.y,
                    r: radius - 1,
                    fill: "none",
                    stroke: "rgba(255,255,255,0.1)",
                    "stroke-width": "0.9",
                    filter: "url(#ballAura)"
                });

                const core = createSvg("circle", {
                    cx: position.x + radius * 0.2,
                    cy: position.y + radius * 0.14,
                    r: Math.max(10, radius * 0.42),
                    fill: toRgba(memory.colors[0], 0.12)
                });

                group.appendChild(aura);
                group.appendChild(glow);
                group.appendChild(circle);
                group.appendChild(core);
                group.appendChild(inner);
                group.appendChild(rim);
                group.appendChild(createLabel(position.x, position.y, memory.label, radius));
                group.appendChild(createSvg("title", {})).textContent = `${memory.period} / ${memory.emotion}\n${memory.content}`;

                group.addEventListener("mouseenter", () => bubbleLayer.appendChild(group));
                bubbleLayer.appendChild(group);
            });
        </script>
    @endif
@endsection
