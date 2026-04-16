@extends('layouts.app')

@section('title', '記憶の玉 | 分身AI MVP')

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
                <span class="eyebrow">Memory Bubble View</span>
                <h1>記憶の玉</h1>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="{{ route('memories.index') }}">一覧へ戻る</a>
                    <a class="btn btn-secondary" href="{{ route('memories.create') }}">記憶を追加</a>
                </div>
            </div>

            <details class="bubble-stage-side bubble-stage-filter">
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

            <div class="bubble-stage-side bubble-stage-count">
                <span class="bubble-side-label">現在の記憶数</span>
                <strong>{{ $displayCount }}</strong>
            </div>

            <div class="bubble-stage-side bubble-stage-note">
                <p>
                    中央の玉が主役です。小さな玉をクリックすると、
                    その記憶の詳細へ移動できます。各玉には年代と感情のタグ属性を持たせています。
                </p>
            </div>

            <div class="bubble-stage-shell">
                <div class="bubble-caption">MEMORY BUBBLE / CLICK A MEMORY</div>
                @if ($selectedPeriod !== 'すべて')
                    <div class="bubble-period-banner">{{ $selectedPeriod }}</div>
                @endif
                <svg id="bubbleStage" viewBox="0 0 1400 920" xmlns="http://www.w3.org/2000/svg" aria-label="記憶の玉">
                    <defs>
                        <filter id="shellGlow" x="-80%" y="-80%" width="260%" height="260%">
                            <feGaussianBlur stdDeviation="44"></feGaussianBlur>
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
        }

        .bubble-stage-shell {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .bubble-stage-side {
            position: absolute;
            z-index: 3;
            width: min(220px, 22vw);
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(11, 18, 36, 0.62);
            border: 1px solid rgba(171, 205, 255, 0.18);
            backdrop-filter: blur(14px);
            box-shadow: 0 20px 40px rgba(3, 6, 18, 0.3);
        }

        .bubble-stage-count {
            top: 28px;
            right: 24px;
            text-align: right;
        }

        .bubble-stage-filter {
            top: 132px;
            right: 24px;
        }

        .bubble-stage-filter summary {
            list-style: none;
            width: 100%;
        }

        .bubble-stage-filter summary::-webkit-details-marker {
            display: none;
        }

        .bubble-stage-filter[open] {
            width: min(280px, 26vw);
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

        .bubble-stage-count strong {
            font-size: clamp(32px, 3vw, 44px);
            line-height: 1;
            color: rgba(245, 249, 255, 0.98);
        }

        .bubble-stage-note {
            right: 24px;
            bottom: 26px;
        }

        .bubble-stage-note p {
            margin: 0;
            color: rgba(218, 231, 255, 0.78);
            line-height: 1.75;
            font-size: 14px;
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
            transition: transform 0.42s cubic-bezier(0.2, 0.7, 0.2, 1), filter 0.42s cubic-bezier(0.2, 0.7, 0.2, 1);
        }

        .memory-ball:hover {
            transform: scale(3);
            filter: brightness(1.12) saturate(1.12) drop-shadow(0 26px 34px rgba(108, 127, 169, 0.2));
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

            .bubble-stage-side {
                width: 200px;
            }
        }

        @media (max-width: 760px) {
            .bubble-stage-panel {
                min-height: auto;
                padding-top: 156px;
            }

            .bubble-stage-copy,
            .bubble-stage-filter,
            .bubble-stage-count,
            .bubble-stage-note {
                position: static;
                width: auto;
                margin: 0 18px 14px;
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
                    "aria-label": `${memory.period}の記憶`
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
