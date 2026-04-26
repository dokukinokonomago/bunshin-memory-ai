@extends('layouts.app')

@section('title', 'YOUの記憶 | 分身AI MVP')
@section('page_class', 'page-bubbles-full')
@section('hide_auth_dock', '1')

@section('content')
<div class="mem-universe">

    {{-- ========== 背景：深宇宙パーティクル ========== --}}
    <canvas id="starCanvas" class="star-canvas" aria-hidden="true"></canvas>

    {{-- ========== TOP NAV ========== --}}
    <nav class="mem-nav">
        <div class="mem-nav-left">
            <span class="mem-nav-eyebrow">PERSONAL MEMORY ARCHIVE</span>
            <h1 class="mem-nav-title">YOUの記憶</h1>
        </div>
        <div class="mem-nav-right">
            {{-- 記憶数 --}}
            <div class="mem-count-orb">
                <span class="mem-count-num">{{ $matchingCount }}</span>
                <span class="mem-count-label">MEMORIES</span>
            </div>

            {{-- アクション --}}
            <details class="mem-details" id="detAction">
                <summary class="mem-glass-pill mem-glass-pill--blue">
                    <span>今日は何をする？</span>
                    <svg class="mem-chevron" viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>
                </summary>
                <div class="mem-dropdown">
                    <a class="mem-drop-item" href="{{ route('memories.create') }}">
                        <span class="mem-drop-icon">＋</span>記憶を追加
                    </a>
                    <span class="mem-drop-item mem-drop-item--dim">
                        <span class="mem-drop-icon">💬</span>記憶と話す
                    </span>
                    <a class="mem-drop-item" href="{{ route('memories.index') }}">
                        <span class="mem-drop-icon">☰</span>記憶一覧
                    </a>
                </div>
            </details>

            {{-- 年代フィルター --}}
            <details class="mem-details" id="detFilter">
                <summary class="mem-glass-pill mem-glass-pill--purple">
                    <span>{{ $selectedPeriod === 'すべて' ? '年代を選ぶ' : $selectedPeriod }}</span>
                    <svg class="mem-chevron" viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>
                </summary>
                <div class="mem-dropdown mem-dropdown--filter">
                    <a class="mem-filter-chip {{ $selectedPeriod==='すべて'?'is-on':'' }}"
                       href="{{ route('memories.bubbles') }}">すべて</a>
                    @foreach($periods as $p)
                    <a class="mem-filter-chip {{ $selectedPeriod===$p?'is-on':'' }}"
                       href="{{ route('memories.bubbles',['period'=>$p]) }}">{{ $p }}</a>
                    @endforeach
                </div>
            </details>

            {{-- 階層ナビ --}}
            @if(($layerCount??1) > 1)
            @php $bubbleBaseParams = $selectedPeriod!=='すべて'?['period'=>$selectedPeriod]:[]; @endphp
            <div class="mem-layer-nav">
                <span>{{ $currentLayer }}/{{ $layerCount }}層</span>
                @if($hasNextLayer)<a class="mem-layer-btn" href="{{ route('memories.bubbles',array_merge($bubbleBaseParams,['layer'=>$currentLayer+1])) }}">次へ</a>@endif
                @if($hasPreviousLayer)<a class="mem-layer-btn" href="{{ route('memories.bubbles',array_merge($bubbleBaseParams,['layer'=>$currentLayer-1])) }}">前へ</a>@endif
            </div>
            @endif
        </div>
    </nav>

    {{-- ========== MAIN STAGE ========== --}}
    @if($bubbleMemories->isEmpty())
        <div class="mem-empty">
            <div class="mem-empty-orb"></div>
            <p>記憶がまだありません</p>
            <a href="{{ route('memories.create') }}" class="mem-glass-pill mem-glass-pill--blue" style="margin-top:20px;text-decoration:none;">記憶を追加する</a>
        </div>
    @else
    @php
        $bubbleBaseRoute = route('memories.bubbles');
    @endphp

    <div class="mem-stage" id="memStage">
        @if($selectedPeriodStatus)
        <aside class="mem-status mem-status--left" aria-label="年代ステータス左">
            <div class="mem-status-shell">
                <div class="mem-status-title">
                    <span class="mem-status-kicker">ERA STATUS</span>
                    <strong>{{ $selectedPeriodStatus['period'] }} 解析</strong>
                </div>

                <section class="mem-status-block">
                    <div class="mem-status-block-head">観測概要</div>
                    <div class="mem-status-hero">
                        <div>
                            <span>記憶総数</span>
                            <strong>{{ number_format($selectedPeriodStatus['total']) }}</strong>
                        </div>
                        <div>
                            <span>感情種別</span>
                            <strong>{{ $selectedPeriodStatus['uniqueEmotions'] }}</strong>
                        </div>
                    </div>
                </section>

                <section class="mem-status-block">
                    <div class="mem-status-block-head">期間情報</div>
                    <div class="mem-status-grid">
                        <article><span>最多感情</span><strong>{{ $selectedPeriodStatus['topEmotion'] }}</strong></article>
                        <article><span>主題語</span><strong>{{ $selectedPeriodStatus['topKeyword'] }}</strong></article>
                        <article><span>平均文字数</span><strong>{{ $selectedPeriodStatus['avgLength'] }}</strong></article>
                        <article><span>表示層</span><strong>{{ $selectedPeriodStatus['currentLayer'] }}/{{ $selectedPeriodStatus['layerCount'] }}</strong></article>
                        <article><span>最初の記録</span><strong>{{ $selectedPeriodStatus['oldestDate'] }}</strong></article>
                        <article><span>最新更新</span><strong>{{ $selectedPeriodStatus['latestDate'] }}</strong></article>
                    </div>
                </section>

                <section class="mem-status-block">
                    <div class="mem-status-block-head">最新ログ</div>
                    <div class="mem-status-timeline">
                        @foreach($selectedPeriodStatus['timeline'] as $entry)
                        @if($loop->first)
                        <article class="mem-status-log">
                            <span class="mem-status-log-date">{{ $entry['date'] }}</span>
                            <strong>{{ $entry['emotion'] }}</strong>
                            <p>{{ $entry['excerpt'] }}</p>
                        </article>
                        @endif
                        @endforeach

                        @if(count($selectedPeriodStatus['timeline']) > 1)
                        <details class="mem-status-logs-more">
                            <summary>過去ログを表示</summary>
                            <div class="mem-status-logs-stack">
                                @foreach($selectedPeriodStatus['timeline'] as $entry)
                                @unless($loop->first)
                                <article class="mem-status-log">
                                    <span class="mem-status-log-date">{{ $entry['date'] }}</span>
                                    <strong>{{ $entry['emotion'] }}</strong>
                                    <p>{{ $entry['excerpt'] }}</p>
                                </article>
                                @endunless
                                @endforeach
                            </div>
                        </details>
                        @endif
                    </div>
                </section>
            </div>
        </aside>

        <aside class="mem-status mem-status--right" aria-label="年代ステータス右">
            <div class="mem-status-shell">
                <section class="mem-status-block">
                    <div class="mem-status-block-head">感情密度 TOP</div>
                    <div class="mem-status-bars">
                        @foreach($selectedPeriodStatus['topEmotionBars'] as $bar)
                        <div class="mem-status-bar-row">
                            <span class="mem-status-bar-label">{{ $bar['label'] }}</span>
                            <div class="mem-status-bar-track">
                                <div class="mem-status-bar-fill" style="width: {{ $bar['ratio'] }}%"></div>
                            </div>
                            <span class="mem-status-bar-value">{{ $bar['count'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </section>

                <section class="mem-status-block">
                    <div class="mem-status-block-head">感情分布</div>
                    <div class="mem-status-rings">
                        @foreach($selectedPeriodStatus['toneRings'] as $ring)
                        <article class="mem-status-ring">
                            <div class="mem-status-ring-orbit" style="--pct: {{ $ring['ratio'] }};">
                                <span>{{ $ring['ratio'] }}%</span>
                            </div>
                            <strong>{{ $ring['label'] }}</strong>
                            <small>{{ $ring['count'] }}件</small>
                        </article>
                        @endforeach
                    </div>
                </section>
            </div>
        </aside>
        @endif

        <svg id="memSvg" class="mem-svg" viewBox="0 0 1400 900"
             xmlns="http://www.w3.org/2000/svg" aria-label="記憶マップ">
            <defs id="memDefs">

                {{-- ===== 共通フィルター ===== --}}

                {{-- オーラぼかし（画像2の外縁ハロー） --}}
                <filter id="fAura" x="-200%" y="-200%" width="500%" height="500%">
                    <feGaussianBlur stdDeviation="32"/>
                </filter>

                {{-- リムグロー --}}
                <filter id="fRimGlow" x="-80%" y="-80%" width="260%" height="260%">
                    <feGaussianBlur stdDeviation="6"/>
                </filter>

                {{-- ドロップシャドウ --}}
                <filter id="fShadow" x="-80%" y="-80%" width="260%" height="260%">
                    <feDropShadow dx="0" dy="18" stdDeviation="20"
                                  flood-color="#000008" flood-opacity="0.75"/>
                </filter>

                {{-- スペキュラソフト --}}
                <filter id="fSpec" x="-120%" y="-120%" width="340%" height="340%">
                    <feGaussianBlur stdDeviation="9"/>
                </filter>

                {{-- 背景大グロー --}}
                <filter id="fBgGlow" x="-150%" y="-150%" width="400%" height="400%">
                    <feGaussianBlur stdDeviation="60"/>
                </filter>

                {{-- リングブラー（画像3の多層リング） --}}
                <filter id="fRing" x="-30%" y="-30%" width="160%" height="160%">
                    <feGaussianBlur stdDeviation="5"/>
                </filter>

                {{-- メインオーブのネオンリングぼかし --}}
                <filter id="fNeon" x="-40%" y="-40%" width="180%" height="180%">
                    <feGaussianBlur stdDeviation="8"/>
                </filter>

            </defs>

            {{-- ===== 背景グロースポット ===== --}}
            <ellipse cx="700" cy="450" rx="520" ry="460"
                     fill="rgba(10,20,80,0.22)" filter="url(#fBgGlow)"/>
            <ellipse cx="200" cy="780" rx="200" ry="160"
                     fill="rgba(0,30,160,0.14)" filter="url(#fBgGlow)"/>
            <ellipse cx="1240" cy="130" rx="180" ry="140"
                     fill="rgba(60,0,180,0.10)" filter="url(#fBgGlow)"/>

            {{-- ===== バブルレイヤー（JS描画） ===== --}}
            <g id="memViewport">
                <g id="memGrid"/>
                <g id="memPeriods"/>
                <g id="memBubbles"/>
            </g>
        </svg>

        <p class="mem-hint">
            <span><i class="mem-dot"></i>ドラッグで移動</span>
            <span><i class="mem-dot"></i>ホイール/ピンチで拡縮</span>
        </p>
    </div>
    @endif

</div>

{{-- =====================================================================
     STYLES
====================================================================== --}}
<style>
/* ── リセット ───────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.page.page-bubbles-full {
    width: 100vw;
    max-width: none;
    padding: 0;
    overflow: hidden;
}

/* ── 宇宙背景 ──────────────────────────── */
.mem-universe {
    position: relative;
    width: 100vw;
    min-height: 100vh;
    background: radial-gradient(ellipse at 30% 20%, #0a1640 0%, #04091e 40%, #000208 100%);
    overflow: hidden;
    color: #d4eaff;
    font-family: system-ui, sans-serif;
}

.star-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

/* ── TOP NAV ────────────────────────────── */
.mem-nav {
    position: absolute;
    top: 0; left: 0; right: 0;
    z-index: 20;
    padding: 22px 28px 0;
    min-height: 112px;
}

.mem-nav-left {
    position: absolute;
    left: 50%;
    top: 22px;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    gap: 7px;
    align-items: center;
    text-align: center;
}

.mem-nav-eyebrow {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 999px;
    border: 1px solid rgba(80,160,255,0.30);
    background: rgba(255,255,255,0.03);
    backdrop-filter: blur(12px);
    color: rgba(110,175,255,0.90);
    font-size: 10px;
    letter-spacing: 0.24em;
    text-transform: uppercase;
}

.mem-nav-title {
    font-size: clamp(26px, 3vw, 46px);
    font-weight: 900;
    letter-spacing: 0.01em;
    color: #f0f8ff;
    text-shadow: 0 0 60px rgba(40,140,255,0.45), 0 0 20px rgba(80,180,255,0.22);
    line-height: 1;
}

.mem-nav-right {
    position: absolute;
    right: 28px;
    top: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* ── 記憶数オーブ（画像1の球体感） ─────── */
.mem-count-orb {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 72px; height: 72px;
    border-radius: 50%;
    flex-shrink: 0;
    position: relative;
    /* 画像1スタイル：ブルーガラス球 */
    background:
        radial-gradient(circle at 32% 26%, rgba(80,200,255,0.35) 0%, rgba(0,80,200,0.20) 38%, rgba(0,10,60,0.88) 100%);
    border: 1.5px solid rgba(0,200,255,0.55);
    box-shadow:
        0 0 0 1px rgba(0,160,255,0.18),
        0 0 22px rgba(0,180,255,0.35),
        0 0 50px rgba(0,100,255,0.18),
        inset 0 1.5px 0 rgba(255,255,255,0.30),
        inset 0 -1px 0 rgba(0,80,200,0.25);
    backdrop-filter: blur(10px);
}

/* スペキュラ白点 */
.mem-count-orb::before {
    content: '';
    position: absolute;
    top: 16%; left: 22%;
    width: 28%; height: 18%;
    border-radius: 50%;
    background: rgba(255,255,255,0.75);
    filter: blur(4px);
}

.mem-count-num {
    font-size: 26px;
    font-weight: 900;
    color: rgba(200,240,255,0.98);
    text-shadow: 0 0 16px rgba(0,220,255,0.60);
    line-height: 1;
    position: relative;
    z-index: 1;
}
.mem-count-label {
    font-size: 7.5px;
    letter-spacing: 0.20em;
    color: rgba(80,190,255,0.80);
    text-transform: uppercase;
    position: relative;
    z-index: 1;
}

/* ── ガラスピルボタン（画像4スタイル） ──── */
.mem-glass-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 999px;
    border: none;
    outline: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    list-style: none;
    user-select: none;
    white-space: nowrap;
    transition: transform 0.18s, box-shadow 0.18s;
    position: relative;
    overflow: hidden;
}

/* 内部の光沢ライン（画像4の上縁ハイライト） */
.mem-glass-pill::before {
    content: '';
    position: absolute;
    top: 0; left: 10%; right: 10%;
    height: 40%;
    border-radius: 0 0 50% 50%;
    background: linear-gradient(180deg, rgba(255,255,255,0.22) 0%, transparent 100%);
    pointer-events: none;
}

.mem-glass-pill--blue {
    background:
        linear-gradient(160deg, rgba(0,100,220,0.70) 0%, rgba(0,40,130,0.90) 100%);
    color: rgba(210,238,255,0.97);
    box-shadow:
        0 0 0 1.5px rgba(0,180,255,0.55),
        0 0 20px rgba(0,140,255,0.30),
        0 0 50px rgba(0,80,255,0.15),
        inset 0 1px 0 rgba(255,255,255,0.20),
        inset 0 -1px 0 rgba(0,60,180,0.40);
}

.mem-glass-pill--purple {
    background:
        linear-gradient(160deg, rgba(90,0,200,0.65) 0%, rgba(40,0,110,0.90) 100%);
    color: rgba(220,200,255,0.97);
    box-shadow:
        0 0 0 1.5px rgba(140,60,255,0.55),
        0 0 20px rgba(100,0,220,0.30),
        0 0 50px rgba(60,0,180,0.15),
        inset 0 1px 0 rgba(255,255,255,0.18),
        inset 0 -1px 0 rgba(60,0,160,0.40);
}

.mem-glass-pill:hover {
    transform: translateY(-2px) scale(1.03);
    box-shadow:
        0 0 0 1.5px rgba(80,220,255,0.80),
        0 0 32px rgba(0,180,255,0.50),
        0 0 70px rgba(0,100,255,0.25),
        inset 0 1px 0 rgba(255,255,255,0.28),
        inset 0 -1px 0 rgba(0,60,180,0.40);
}

.mem-glass-pill::-webkit-details-marker { display: none; }

.mem-chevron {
    width: 10px; height: 6px;
    color: rgba(180,220,255,0.85);
    transition: transform 0.2s;
    flex-shrink: 0;
}
details[open] .mem-chevron { transform: rotate(180deg); }

/* ── ドロップダウン ──────────────────────── */
.mem-details { position: relative; }

.mem-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    z-index: 30;
    min-width: 200px;
    padding: 10px;
    border-radius: 18px;
    /* 画像4のカードスタイル */
    background:
        linear-gradient(145deg, rgba(8,18,60,0.96) 0%, rgba(4,10,36,0.98) 100%);
    border: 1px solid rgba(0,160,255,0.28);
    box-shadow:
        0 0 0 1px rgba(0,100,200,0.12),
        0 30px 60px rgba(0,0,0,0.75),
        0 0 30px rgba(0,80,200,0.18),
        inset 0 1px 0 rgba(255,255,255,0.08);
    backdrop-filter: blur(28px) saturate(1.5);
}

.mem-drop-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    border-radius: 12px;
    color: rgba(200,228,255,0.92);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.16s, transform 0.14s;
    cursor: pointer;
}
.mem-drop-item:hover {
    background: rgba(0,100,255,0.18);
    transform: translateX(3px);
}
.mem-drop-item--dim { opacity: 0.38; pointer-events: none; }

.mem-drop-icon {
    display: grid;
    place-items: center;
    width: 30px; height: 30px;
    border-radius: 9px;
    background: rgba(0,80,200,0.28);
    border: 1px solid rgba(0,150,255,0.22);
    font-size: 14px;
    flex-shrink: 0;
}

/* フィルターチップ */
.mem-dropdown--filter {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 280px;
}

.mem-filter-chip {
    display: inline-flex;
    align-items: center;
    padding: 7px 16px;
    border-radius: 999px;
    border: 1px solid rgba(60,130,255,0.28);
    background: rgba(0,16,60,0.65);
    color: rgba(150,205,255,0.88);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.16s;
}
.mem-filter-chip:hover {
    border-color: rgba(0,200,255,0.60);
    background: rgba(0,60,180,0.55);
    color: #fff;
}
.mem-filter-chip.is-on {
    border-color: rgba(0,210,255,0.80);
    background: linear-gradient(135deg, rgba(0,100,220,0.55), rgba(0,60,180,0.65));
    color: #fff;
    box-shadow: 0 0 18px rgba(0,180,255,0.35);
}

/* ── 階層ナビ ───────────────────────────── */
.mem-layer-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 999px;
    border: 1px solid rgba(60,110,220,0.28);
    background: rgba(0,8,36,0.65);
    backdrop-filter: blur(12px);
    font-size: 11px;
    color: rgba(120,175,255,0.82);
}
.mem-layer-btn {
    padding: 3px 10px;
    border-radius: 999px;
    border: 1px solid rgba(0,150,255,0.32);
    background: rgba(0,40,150,0.48);
    color: rgba(160,215,255,0.92);
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.16s;
}
.mem-layer-btn:hover { border-color: rgba(0,220,255,0.60); }

/* ── 期間バッジ ─────────────────────────── */
.mem-period-badge {
    position: absolute;
    top: 96px; left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    padding: 8px 26px;
    border-radius: 999px;
    background: rgba(0,6,32,0.80);
    border: 1.5px solid rgba(0,190,255,0.42);
    color: rgba(160,230,255,0.96);
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.10em;
    box-shadow: 0 0 26px rgba(0,150,255,0.26);
    backdrop-filter: blur(16px);
}

/* ── STAGE ──────────────────────────────── */
.mem-stage {
    position: relative;
    width: 100%;
    min-height: 100vh;
}

.mem-status {
    position: absolute;
    top: 124px;
    z-index: 12;
    width: min(290px, calc(50vw - 360px));
    min-width: 240px;
    pointer-events: none;
}

.mem-status--left { left: 24px; }
.mem-status--right { right: 24px; }

.mem-status--left {
    top: 24px;
}

.mem-status-shell {
    display: grid;
    gap: 16px;
    padding: 18px 16px;
    border-radius: 22px;
    border: 1px solid rgba(86, 160, 255, 0.2);
    background:
        linear-gradient(180deg, rgba(10, 20, 58, 0.92), rgba(3, 8, 28, 0.96));
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.06),
        0 24px 54px rgba(0,0,0,0.38),
        0 0 24px rgba(0,120,255,0.14);
    pointer-events: auto;
}

.mem-status-title {
    display: grid;
    gap: 4px;
}

.mem-status-kicker {
    color: rgba(114, 196, 255, 0.82);
    font-size: 11px;
    letter-spacing: 0.24em;
    text-transform: uppercase;
}

.mem-status-title strong {
    color: rgba(241, 248, 255, 0.96);
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 0.06em;
}

.mem-status-block {
    display: grid;
    gap: 12px;
}

.mem-status-block-head {
    min-height: 32px;
    display: flex;
    align-items: center;
    padding: 0 14px;
    border-radius: 999px;
    background: linear-gradient(90deg, rgba(52, 140, 255, 0.54), rgba(52, 140, 255, 0));
    color: rgba(220, 239, 255, 0.95);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.12em;
}

.mem-status-hero {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.mem-status-hero div,
.mem-status-grid article,
.mem-status-log {
    padding: 12px;
    border-radius: 16px;
    border: 1px solid rgba(100, 164, 255, 0.12);
    background: rgba(255,255,255,0.03);
}

.mem-status-hero span,
.mem-status-grid span,
.mem-status-log-date,
.mem-status-ring small {
    display: block;
    color: rgba(140, 186, 236, 0.72);
    font-size: 11px;
    letter-spacing: 0.08em;
}

.mem-status-hero strong {
    margin-top: 6px;
    display: block;
    color: #8fe8ff;
    font-size: 30px;
    font-weight: 900;
    line-height: 1;
}

.mem-status-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.mem-status-grid strong {
    margin-top: 6px;
    display: block;
    color: rgba(244, 249, 255, 0.96);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
}

.mem-status-timeline {
    display: grid;
    gap: 10px;
}

.mem-status-log {
    position: relative;
    padding-left: 18px;
}

.mem-status-log::before {
    content: "";
    position: absolute;
    left: 7px;
    top: 14px;
    bottom: -10px;
    width: 1px;
    background: linear-gradient(180deg, rgba(87, 192, 255, 0.8), rgba(87, 192, 255, 0));
}

.mem-status-log:last-child::before {
    display: none;
}

.mem-status-log strong {
    display: block;
    margin-top: 4px;
    color: #9fe8ff;
    font-size: 14px;
}

.mem-status-log p {
    margin-top: 6px;
    color: rgba(212, 231, 255, 0.78);
    font-size: 12px;
    line-height: 1.6;
}

.mem-status-logs-more {
    border-radius: 16px;
    border: 1px solid rgba(100, 164, 255, 0.12);
    background: rgba(255,255,255,0.03);
    overflow: hidden;
}

.mem-status-logs-more summary {
    list-style: none;
    cursor: pointer;
    padding: 12px 14px;
    color: rgba(169, 214, 255, 0.9);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.mem-status-logs-more summary::-webkit-details-marker {
    display: none;
}

.mem-status-logs-stack {
    display: grid;
    gap: 10px;
    padding: 0 12px 12px;
}

.mem-status-bars {
    display: grid;
    gap: 10px;
}

.mem-status-bar-row {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr) 28px;
    gap: 10px;
    align-items: center;
}

.mem-status-bar-label,
.mem-status-bar-value {
    color: rgba(213, 231, 255, 0.84);
    font-size: 12px;
}

.mem-status-bar-track {
    height: 8px;
    border-radius: 999px;
    background: rgba(255,255,255,0.07);
    overflow: hidden;
}

.mem-status-bar-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #f9c86a, #66d8ff);
    box-shadow: 0 0 14px rgba(102, 216, 255, 0.36);
}

.mem-status-rings {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 14px;
}

.mem-status-ring {
    display: grid;
    justify-items: center;
    gap: 6px;
}

.mem-status-ring-orbit {
    --deg: calc(var(--pct) * 3.6deg);
    width: 82px;
    height: 82px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background:
        radial-gradient(circle at center, rgba(2, 8, 26, 0.98) 56%, transparent 57%),
        conic-gradient(from -90deg, #66d8ff var(--deg), rgba(255,255,255,0.08) 0);
    box-shadow:
        inset 0 0 0 1px rgba(118, 202, 255, 0.16),
        0 0 20px rgba(102, 216, 255, 0.18);
}

.mem-status-ring-orbit span {
    color: rgba(245, 250, 255, 0.96);
    font-size: 18px;
    font-weight: 800;
}

.mem-status-ring strong {
    color: rgba(228, 239, 255, 0.92);
    font-size: 12px;
    font-weight: 700;
}

.mem-svg {
    display: block;
    width: 100%;
    height: auto;
    max-height: 100vh;
    cursor: grab;
    touch-action: none;
}
.mem-svg.dragging { cursor: grabbing; }

/* ── ヒントチップ ───────────────────────── */
.mem-hint {
    position: absolute;
    bottom: 16px; left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 12px;
    z-index: 10;
}
.mem-hint span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 16px;
    border-radius: 999px;
    background: rgba(0,5,24,0.82);
    border: 1px solid rgba(0,110,220,0.22);
    backdrop-filter: blur(14px);
    color: rgba(130,190,255,0.85);
    font-size: 11px;
    box-shadow: 0 10px 26px rgba(0,0,0,0.40);
}
.mem-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #5060ff);
    box-shadow: 0 0 10px rgba(0,200,255,0.60);
}

/* ── Empty ──────────────────────────────── */
.mem-empty {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    z-index: 10;
    color: rgba(140,190,255,0.80);
    font-size: 15px;
    text-align: center;
}
.mem-empty-orb {
    width: 120px; height: 120px;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 28%, rgba(80,180,255,0.30), rgba(0,40,140,0.20) 60%, rgba(0,5,30,0.80));
    border: 1.5px solid rgba(0,200,255,0.40);
    box-shadow: 0 0 50px rgba(0,140,255,0.25);
    animation: breathe 4s ease-in-out infinite;
}

/* ── SVG要素CSS ─────────────────────────── */

/* グリッド線 */
.mg-grid-line {
    stroke: rgba(0,70,200,0.07);
    stroke-width: 1;
    stroke-dasharray: 5 18;
}

/* 年代ゾーン */
.mg-zone { cursor: pointer; }
.mg-zone-halo {
    fill: rgba(0,60,180,0.04);
    stroke: rgba(0,150,255,0.13);
    stroke-width: 1.5;
    stroke-dasharray: 5 12;
    transition: fill 0.25s, stroke 0.25s;
}
.mg-zone.is-active .mg-zone-halo {
    fill: rgba(0,100,240,0.08);
    stroke: rgba(0,220,255,0.40);
}
.mg-zone-dot {
    fill: rgba(0,200,255,0.80);
    filter: drop-shadow(0 0 6px rgba(0,200,255,0.65));
}
.mg-zone-name {
    fill: rgba(140,210,255,0.90);
    font-size: 17px;
    font-weight: 800;
    text-anchor: middle;
    letter-spacing: 0.05em;
    transition: fill 0.25s;
}
.mg-zone.is-active .mg-zone-name { fill: rgba(180,240,255,1); }
.mg-zone-count {
    fill: rgba(80,150,230,0.72);
    font-size: 11px;
    text-anchor: middle;
}

/* バブルラッパー（出現アニメ） */
.mg-bubble-wrap {
    opacity: 0;
    animation: mgReveal 0.80s cubic-bezier(0.22,0.85,0.32,1) var(--d,0s) forwards;
}

/* バブル本体（呼吸アニメ） */
.mg-bubble-body {
    transform-box: fill-box;
    transform-origin: center;
    animation: mgPulse var(--dur,6s) ease-in-out var(--delay,0s) infinite;
    transition: transform 0.42s cubic-bezier(0.2,0.8,0.2,1), filter 0.42s;
}
.mg-bubble-wrap:hover .mg-bubble-body {
    animation: none;
    transform: scale(var(--hs,1.16));
    filter: brightness(1.25) saturate(1.35)
            drop-shadow(0 0 36px rgba(0,180,255,0.60));
}
.mg-bubble-wrap.period-hi .mg-bubble-body {
    animation: none;
    transform: scale(1.10);
    filter: brightness(1.15) saturate(1.20)
            drop-shadow(0 0 22px rgba(0,150,255,0.42));
}

/* テキストラベル */
.mg-label {
    fill: rgba(255,255,255,0.97);
    font-weight: 700;
    text-anchor: middle;
    dominant-baseline: middle;
    paint-order: stroke;
    stroke: rgba(0,0,0,0.58);
    stroke-width: 3.5px;
    stroke-linejoin: round;
    pointer-events: none;
}

/* 衛星小バブル */
.mg-sat {
    transform-box: fill-box;
    transform-origin: center;
    animation: mgReveal 0.90s cubic-bezier(0.22,0.85,0.32,1) var(--d,0s) both,
               satFloat var(--sd,7s) ease-in-out var(--sdly,0s) infinite;
}
@keyframes satFloat {
    0%,100% { transform: translate(0px, 0px) scale(0.97); }
    33%     { transform: translate(calc(var(--amp,6)*0.4px), calc(var(--amp,6)*-1px)) scale(1.03); }
    66%     { transform: translate(calc(var(--amp,6)*-0.3px), calc(var(--amp,6)*-0.6px)) scale(1.01); }
}

/* シェル呼吸 */
.mg-shell-breath {
    transform-box: fill-box;
    transform-origin: center;
    animation: shellBreath var(--sd,8s) ease-in-out var(--sdelay,0s) infinite;
}

/* ── キーフレーム ────────────────────────── */
@keyframes mgReveal {
    0%   { opacity:0; filter:blur(14px) saturate(0.2); }
    70%  { opacity:0.90; }
    100% { opacity:1;  filter:blur(0)   saturate(1); }
}
@keyframes mgPulse {
    0%,100% { transform: scale(var(--rs,0.95)); }
    50%     { transform: scale(var(--rise,1.05)); }
}
@keyframes shellBreath {
    0%,100% { transform:scale(0.982); opacity:0.85; }
    50%     { transform:scale(1.018); opacity:1; }
}
@keyframes breathe {
    0%,100% { transform:scale(0.94); }
    50%     { transform:scale(1.06); }
}

/* ── レスポンシブ ────────────────────────── */
@media(max-width:900px){
    .mem-nav { padding:16px 16px 0; min-height: 160px; }
    .mem-nav-left {
        top: 16px;
        width: calc(100% - 32px);
    }
    .mem-nav-right {
        top: 92px;
        left: 16px;
        right: 16px;
        justify-content: center;
    }
    .mem-nav-right { gap:8px; }
    .mem-count-orb { width:60px; height:60px; }
    .mem-count-num { font-size:20px; }
    .mem-nav-title { font-size:clamp(22px,5vw,34px); }
    .mem-status {
        position: static;
        width: auto;
        min-width: 0;
        margin: 168px 16px 0;
    }
    .mem-stage {
        display: grid;
        gap: 16px;
        padding-bottom: 28px;
    }
}
@media(max-width:640px){
    .mem-glass-pill { padding:8px 14px; font-size:12px; }
    .mem-dropdown { right:-8px; }
    .mem-count-orb { width:52px; height:52px; }
    .mem-status-hero,
    .mem-status-grid,
    .mem-status-rings {
        grid-template-columns: 1fr;
    }
    .mem-status-bar-row {
        grid-template-columns: 62px minmax(0, 1fr) 24px;
    }
}
</style>

{{-- =====================================================================
     SCRIPT
====================================================================== --}}
@if($bubbleMemories->isNotEmpty())
<script>
(function(){
"use strict";

/* ===== 定数 ===== */
const memories       = @json($bubbleMemories);
const periods        = @json($periods);
const selPeriod      = @json($selectedPeriod);
const bubblesRoute   = @json($bubbleBaseRoute);
const NS = "http://www.w3.org/2000/svg";
const VP = { w:1400, h:900 };

/* ===== DOM refs ===== */
const svg      = document.getElementById("memSvg");
const defs     = document.getElementById("memDefs");
const viewport = document.getElementById("memViewport");
const gridG    = document.getElementById("memGrid");
const periodsG = document.getElementById("memPeriods");
const bubblesG = document.getElementById("memBubbles");

/* ===== 年代座標（幼少期・小学生→左寄り、高校生・大学生→右寄り） ===== */
const ANCHORS = {
    "幼少期":{ x: -320, y: 320 },
    "小学生":{ x:  200, y: 820 },
    "中学生":{ x:  960, y: 560 },
    "高校生":{ x: 1880, y: 880 },
    "大学生":{ x: 1680, y:-160 },
    "成人期":{ x: 2380, y: 220 },
    "不明":  { x:  820, y:-120 },
};

/* ===== パン/ズーム状態 ===== */
const st = {
    scale:1, tx:0, ty:0,
    minS:0.65, maxS:1.65,
    drag:false, started:false, pid:null,
    sx:0, sy:0, stx:0, sty:0,
    touch:null, pinchD:0,
    bounds:null,
};

/* ===== SVGユーティリティ ===== */
function el(tag, a={}){
    const e=document.createElementNS(NS,tag);
    for(const[k,v] of Object.entries(a)) e.setAttribute(k,v);
    return e;
}

/* 16進→rgba */
function rgba(hex,a){
    if(!hex||!hex.startsWith("#")) return `rgba(80,140,255,${a})`;
    const h=hex.length===4
        ? hex.slice(1).split("").map(c=>c+c).join("")
        : hex.slice(1);
    const r=parseInt(h.slice(0,2),16);
    const g=parseInt(h.slice(2,4),16);
    const b=parseInt(h.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
}

/* ===== グラデーション定義 =====
   ▼ 画像1・2・3を参考に3パターン作成
============================================= */
function mkGrads(idx, colors){
    const c0=colors?.[0]??"#2a6fff";
    const c1=colors?.[1]??"#00d4ff";
    const gId=`g${idx}`, rId=`r${idx}`, aId=`a${idx}`;

    /* ① 球体グラデ（画像1・3：左上→右下の深いガラス） */
    const grd=el("radialGradient",{id:gId,cx:"30%",cy:"26%",r:"76%",gradientUnits:"objectBoundingBox"});
    /* 左上ハイライト〜深いブルー〜右下シアン */
    grd.append(
        el("stop",{offset:"0%",  "stop-color":rgba(c0,0.15)}),
        el("stop",{offset:"22%", "stop-color":rgba(c0,0.65)}),
        el("stop",{offset:"55%", "stop-color":rgba(c0,0.80)}),
        el("stop",{offset:"82%", "stop-color":rgba(c1,0.78)}),
        el("stop",{offset:"100%","stop-color":rgba(c1,0.92)}),
    );
    defs.append(grd);

    /* ② リムライトグラデ（画像1・2のネオンリム） */
    const rim=el("linearGradient",{id:rId,x1:"0%",y1:"0%",x2:"100%",y2:"100%"});
    rim.append(
        el("stop",{offset:"0%",  "stop-color":rgba(c1,1.0)}),
        el("stop",{offset:"40%", "stop-color":rgba(c0,0.50)}),
        el("stop",{offset:"100%","stop-color":rgba(c1,0.90)}),
    );
    defs.append(rim);

    /* ③ オーラグラデ（画像2：外縁の発光ハロー） */
    const aur=el("radialGradient",{id:aId,cx:"50%",cy:"50%",r:"50%",gradientUnits:"objectBoundingBox"});
    aur.append(
        el("stop",{offset:"0%",  "stop-color":rgba(c0,0.0)}),
        el("stop",{offset:"55%", "stop-color":rgba(c1,0.22)}),
        el("stop",{offset:"100%","stop-color":rgba(c1,0.50)}),
    );
    defs.append(aur);

    return {gId,rId,aId};
}

/* ===== バブル半径 ===== */
function ballR(i){ return [116,106,102,120,98,110,100,114,98,112,104,108][i%12]; }
function anchor(p){ return ANCHORS[p]??{x:760,y:390}; }

/* ===== 擬似乱数（シード付き再現性あり） ===== */
function seededRand(seed){
    let s=seed;
    return function(){
        s=(s*1664525+1013904223)&0xffffffff;
        return (s>>>0)/0xffffffff;
    };
}

/* ===== 衛星小バブル生成（記憶玉ごとにランダム） ===== */
/* 各衛星のサイズ倍率（バリエーション豊かに） */
const SAT_SIZE_RATIOS = [0.32, 0.18, 0.42, 0.14, 0.28, 0.38, 0.12, 0.24, 0.20, 0.35];
const SAT_COUNT = 10;

/* ===== 年代ごとの記憶数マップ ===== */
const periodCount = new Map();
memories.forEach(m=>periodCount.set(m.period,(periodCount.get(m.period)??0)+1));

/* ===== ワールドデータ構築 ===== */
function buildWorld(){
    const buckets=new Map();
    memories.forEach(m=>{
        if(!buckets.has(m.period)) buckets.set(m.period,[]);
        buckets.get(m.period).push(m);
    });
    const pNodes=[], bNodes=[], sNodes=[];
    periods.forEach(p=>{
        const anc=anchor(p);
        const items=buckets.get(p)??[];
        if(selPeriod!=="すべて"&&selPeriod!==p) return;
        const zoneR=Math.max(260,280+items.length*12);
        pNodes.push({ p, count:items.length, x:anc.x, y:anc.y, r:zoneR });
        if(items.length===0) return;

        const representative = items[0];
        const r = Math.min(148, Math.max(92, 92 + Math.sqrt(items.length) * 14));
        const bx = anc.x;
        const by = anc.y;
        bNodes.push({
            m: representative,
            r,
            x: bx,
            y: by,
            count: items.length,
            period: p,
        });

        const rng = seededRand(representative.id * 7919 + items.length * 1327);
        for(let si=0; si<SAT_COUNT; si++){
            const sizeRatio = SAT_SIZE_RATIOS[si % SAT_SIZE_RATIOS.length];
            const sr = Math.max(5, Math.round(r * sizeRatio * (0.75 + rng() * 0.55)));
            const baseAngle = (si / SAT_COUNT) * Math.PI * 2;
            const angleJitter = (rng() - 0.5) * 0.9;
            const angle = baseAngle + angleJitter;
            const orbitBase = r * 1.48 + sr * 1.8;
            const orbitDist = orbitBase + rng() * r * 0.55;
            sNodes.push({
                x: bx + Math.cos(angle) * orbitDist,
                y: by + Math.sin(angle) * orbitDist,
                r: sr,
                op: 0.35 + rng() * 0.35,
                dur: 5.5 + rng() * 4.0,
                dly: -(rng() * 6.0),
                floatAmp: 4 + rng() * 8,
                colors: representative.colors,
            });
        }
    });
    return {pNodes,bNodes,sNodes};
}

function buildBounds(world){
    let x0=1e9,x1=-1e9,y0=1e9,y1=-1e9;
    world.pNodes.forEach(n=>{
        x0=Math.min(x0,n.x-n.r-60); x1=Math.max(x1,n.x+n.r+60);
        y0=Math.min(y0,n.y-n.r-80); y1=Math.max(y1,n.y+n.r+60);
    });
    world.bNodes.forEach(n=>{
        x0=Math.min(x0,n.x-n.r-50); x1=Math.max(x1,n.x+n.r+50);
        y0=Math.min(y0,n.y-n.r-50); y1=Math.max(y1,n.y+n.r+50);
    });
    world.sNodes.forEach(n=>{
        x0=Math.min(x0,n.x-n.r-20); x1=Math.max(x1,n.x+n.r+20);
        y0=Math.min(y0,n.y-n.r-20); y1=Math.max(y1,n.y+n.r+20);
    });
    return {x0,y0,x1,y1,w:x1-x0,h:y1-y0};
}

/* ===== グリッド描画 ===== */
function drawGrid(b){
    const step=260;
    for(let x=Math.floor(b.x0/step)*step;x<=b.x1;x+=step)
        gridG.append(el("line",{x1:x,y1:b.y0-150,x2:x,y2:b.y1+150,class:"mg-grid-line"}));
    for(let y=Math.floor(b.y0/step)*step;y<=b.y1;y+=step)
        gridG.append(el("line",{x1:b.x0-150,y1:y,x2:b.x1+150,y2:y,class:"mg-grid-line"}));
}

/* ===== 年代ゾーン描画 ===== */
const zoneEls=new Map(), ballByPeriod=new Map();

function drawPeriods(world){
    world.pNodes.forEach(n=>{
        const g=el("g",{class:"mg-zone","data-p":n.p});
        g.append(
            el("circle",{cx:n.x,cy:n.y,r:n.r,class:"mg-zone-halo"}),
            el("circle",{cx:n.x,cy:n.y,r:5,class:"mg-zone-dot"}),
        );
        periodsG.append(g);
        zoneEls.set(n.p,g);
    });
}

/* ===== バブル描画 =====
   ▼ 画像1〜3の多層構造を完全再現
================================================ */
function drawBubbles(world){
    world.bNodes.forEach((node,i)=>{
        const {gId,rId,aId}=mkGrads(i+1,node.m.colors);
        const {x:cx,y:cy,r}=node;

        const wrap=el("g",{
            class:"mg-bubble-wrap",
            "data-period":node.period,
            style:`--d:${(i*0.04).toFixed(2)}s`,
        });

        const periodUrl = new URL(bubblesRoute,location.origin);
        periodUrl.searchParams.set("period", node.period);
        const link=el("a",{
            href:periodUrl.toString(),
            class:"mg-bubble-link",
            "data-period":node.period,
            "aria-label":`${node.period}の記憶 ${node.count}件`,
            style:[
                `--rs:${(0.94+i%4*0.018).toFixed(3)}`,
                `--rise:${(1.03+i%5*0.020).toFixed(3)}`,
                `--hs:${(1.15+i%4*0.022).toFixed(3)}`,
                `--dur:${(5.4+i%6*0.50).toFixed(2)}s`,
                `--delay:${(-i*0.40).toFixed(2)}s`,
            ].join(";"),
        });

        const body=el("g",{class:"mg-bubble-body"});

        /* --- レイヤー1：外側オーラグロー（画像2のハロー） --- */
        body.append(el("circle",{
            cx,cy,r:r+36,
            fill:`url(#${aId})`,
            filter:"url(#fAura)",
        }));

        /* --- レイヤー2：多層リングシェル（画像3のシアンリング群） --- */
        /* 外→内の6層リング */
        [
            {rr:r+2,  sw:14, op:0.60},
            {rr:r-6,  sw:10, op:0.50},
            {rr:r*0.88,sw:7, op:0.40},
            {rr:r*0.76,sw:5, op:0.28},
            {rr:r*0.64,sw:4, op:0.18},
            {rr:r*0.52,sw:3, op:0.10},
        ].forEach(({rr,sw,op})=>{
            body.append(el("circle",{
                cx,cy,r:rr,fill:"none",
                stroke:`url(#${rId})`,
                "stroke-width":sw,
                opacity:op,
                filter:"url(#fRimGlow)",
            }));
        });

        /* --- レイヤー3：ガラス球本体（画像1・3の深いブルーガラス） --- */
        body.append(el("circle",{
            cx,cy,r,
            fill:`url(#${gId})`,
            filter:"url(#fShadow)",
            opacity:"0.95",
        }));

        /* --- レイヤー4：内部暗領域（画像3中央の黒い虚空感） --- */
        body.append(el("circle",{
            cx,cy,r:r*0.58,
            fill:"rgba(0,2,20,0.08)",
        }));

        /* --- レイヤー5：メインスペキュラ（画像1の強い白光点） --- */
        body.append(el("circle",{
            cx:cx-r*0.27, cy:cy-r*0.28,
            r:Math.max(6,r*0.20),
            fill:"rgba(255,255,255,0.88)",
            filter:"url(#fSpec)",
        }));

        /* --- レイヤー6：サブスペキュラ楕円（表面の光の伸び） --- */
        body.append(el("ellipse",{
            cx:cx-r*0.15, cy:cy-r*0.21,
            rx:Math.max(9,r*0.30), ry:Math.max(4,r*0.12),
            fill:"rgba(255,255,255,0.28)",
            transform:`rotate(-26 ${cx-r*0.15} ${cy-r*0.21})`,
        }));

        /* --- レイヤー7：右下副反射（画像1のガラスの厚み感） --- */
        body.append(el("circle",{
            cx:cx+r*0.28, cy:cy+r*0.30,
            r:Math.max(4,r*0.11),
            fill:"rgba(255,255,255,0.18)",
        }));

        /* --- レイヤー8：ネオンリムアーク（画像2の輝くリング縁） --- */
        const arcR=r+4;
        const a1={x:cx+arcR*Math.cos(Math.PI*1.25),y:cy+arcR*Math.sin(Math.PI*1.25)};
        const a2={x:cx+arcR*Math.cos(Math.PI*1.80),y:cy+arcR*Math.sin(Math.PI*1.80)};
        body.append(el("path",{
            d:`M${a1.x} ${a1.y} A${arcR} ${arcR} 0 0 1 ${a2.x} ${a2.y}`,
            fill:"none",stroke:`url(#${rId})`,
            "stroke-width":"4","stroke-linecap":"round",opacity:"0.80",
        }));

        /* --- ヒットエリア --- */
        body.append(el("circle",{
            cx,cy,r:r+20,
            fill:"rgba(255,255,255,0.001)",
            "pointer-events":"all",
        }));

        /* --- ラベル（年代名 + 件数） --- */
        const periodLabel=el("text",{x:cx,y:cy-r*0.34,class:"mg-label","font-size":Math.max(14,r*0.20),"font-weight":"800"});
        periodLabel.textContent=node.period;
        body.append(periodLabel);

        const lbl=el("text",{x:cx,y:cy-r*0.02,class:"mg-label","font-size":Math.max(22,r*0.38),"font-weight":"800"});
        lbl.textContent=node.count;
        body.append(lbl);

        const lbl2=el("text",{x:cx,y:cy+r*0.28,class:"mg-label","font-size":Math.max(9,r*0.14),"font-weight":"400",opacity:"0.72"});
        lbl2.textContent="memories";
        body.append(lbl2);

        /* --- title（アクセシビリティ） --- */
        const ttl=el("title",{});
        ttl.textContent=`${node.period} / ${node.count} memories`;
        link.append(ttl);

        link.append(body);
        wrap.append(link);
        bubblesG.append(wrap);

        if(!ballByPeriod.has(node.period)) ballByPeriod.set(node.period,[]);
        ballByPeriod.get(node.period).push(wrap);
    });
}

/* ===== ホバー連動 ===== */
function setActive(p){
    zoneEls.forEach((z,k)=>z.classList.toggle("is-active",k===p));
    ballByPeriod.forEach((ws,k)=>ws.forEach(w=>w.classList.toggle("period-hi",k===p)));
}
function clearActive(){
    zoneEls.forEach(z=>z.classList.remove("is-active"));
    ballByPeriod.forEach(ws=>ws.forEach(w=>w.classList.remove("period-hi")));
}

/* ===== ズーム/パン ===== */
function svgPt(cx,cy){
    const rc=svg.getBoundingClientRect();
    return{x:(cx-rc.left)/rc.width*VP.w,y:(cy-rc.top)/rc.height*VP.h};
}
function clamp(){
    const b=st.bounds,m=140;
    const sw=b.w*st.scale,sh=b.h*st.scale;
    if(sw<=VP.w-m*2) st.tx=(VP.w-sw)/2-b.x0*st.scale;
    else st.tx=Math.min(m-b.x0*st.scale,Math.max(VP.w-(b.x1*st.scale+m),st.tx));
    if(sh<=VP.h-m*2) st.ty=(VP.h-sh)/2-b.y0*st.scale;
    else st.ty=Math.min(m-b.y0*st.scale,Math.max(VP.h-(b.y1*st.scale+m),st.ty));
}
function apply(){
    clamp();
    viewport.setAttribute("transform",`matrix(${st.scale} 0 0 ${st.scale} ${st.tx} ${st.ty})`);
}
function frame(){
    const b=st.bounds,px=200,py=200;
    st.scale=Math.min(st.maxS,Math.max(0.45,Math.min(VP.w/(b.w+px),VP.h/(b.h+py))));
    st.tx=(VP.w/2)-((b.x0+b.x1)/2*st.scale);
    st.ty=(VP.h/2)-((b.y0+b.y1)/2*st.scale);
    apply();
}
function zoom(ns,pt){
    const s=Math.min(st.maxS,Math.max(st.minS,ns));
    const wx=(pt.x-st.tx)/st.scale,wy=(pt.y-st.ty)/st.scale;
    st.tx=pt.x-wx*s; st.ty=pt.y-wy*s; st.scale=s;
    apply();
}
function dragStart(pt,pid=null){
    st.drag=true;st.started=false;st.pid=pid;
    st.sx=pt.x;st.sy=pt.y;st.stx=st.tx;st.sty=st.ty;
    svg.classList.add("dragging");
}
function dragMove(pt){
    if(!st.drag) return;
    const dx=pt.x-st.sx,dy=pt.y-st.sy;
    if(Math.abs(dx)>2||Math.abs(dy)>2) st.started=true;
    st.tx=st.stx+dx; st.ty=st.sty+dy; apply();
}
function dragEnd(){ st.drag=false;st.pid=null;svg.classList.remove("dragging"); }

/* ===== 衛星小バブル描画 ===== */
function drawSatellites(world){
    world.sNodes.forEach((s,i)=>{
        const sid=`sat${i}`;
        const c0=s.colors?.[0]??"#2a6fff";
        const c1=s.colors?.[1]??"#00d4ff";

        /* サイズに応じてグラデの鮮やかさを変える */
        const vividness = Math.min(1, s.r / 30);
        const sg=el("radialGradient",{id:sid,cx:"32%",cy:"28%",r:"72%",gradientUnits:"objectBoundingBox"});
        sg.append(
            el("stop",{offset:"0%",  "stop-color":rgba(c0, 0.05 + vividness*0.12)}),
            el("stop",{offset:"40%", "stop-color":rgba(c0, 0.40 + vividness*0.25)}),
            el("stop",{offset:"100%","stop-color":rgba(c1, 0.60 + vividness*0.22)}),
        );
        defs.append(sg);

        const g=el("g",{
            class:"mg-sat",
            style:[
                `--sd:${s.dur.toFixed(1)}s`,
                `--sdly:${s.dly.toFixed(1)}s`,
                `--amp:${s.floatAmp.toFixed(1)}`,
                `--d:${(i*0.018).toFixed(2)}s`,
            ].join(";"),
            opacity:s.op,
        });

        /* オーラ（大きな玉は強め） */
        if(s.r > 12){
            g.append(el("circle",{
                cx:s.x,cy:s.y,r:s.r+s.r*0.9,
                fill:rgba(c1, 0.12 + vividness*0.10),
                filter:"url(#fAura)",
            }));
        }
        /* リム */
        g.append(el("circle",{
            cx:s.x,cy:s.y,r:s.r,fill:"none",
            stroke:rgba(c1,0.65+vividness*0.25),
            "stroke-width": s.r > 20 ? 2.0 : s.r > 12 ? 1.5 : 1.0,
            filter:"url(#fRimGlow)",
        }));
        /* 球体 */
        g.append(el("circle",{
            cx:s.x,cy:s.y,r:s.r,
            fill:`url(#${sid})`,opacity:"0.92",
        }));
        /* スペキュラ（大きめの玉のみ） */
        if(s.r > 10){
            g.append(el("circle",{
                cx:s.x-s.r*0.28, cy:s.y-s.r*0.28,
                r:Math.max(1.5, s.r*0.24),
                fill:"rgba(255,255,255,0.85)",
                filter:"url(#fSpec)",
            }));
        }

        bubblesG.insertBefore(g, bubblesG.firstChild);
    });
}

/* ===== 初期化 ===== */
const world=buildWorld();
st.bounds=buildBounds(world);
drawGrid(st.bounds);
drawPeriods(world);
drawSatellites(world);   /* 先に描画（記憶玉の下に重なるように） */
drawBubbles(world);
frame();
if(selPeriod!=="すべて") setActive(selPeriod);

/* ===== イベント ===== */
/* ===== ダブルクリック：年代フィルター ===== */
function goToPeriod(p){
    const url=new URL(bubblesRoute,location.origin);
    url.searchParams.set("period",p);
    location.href=url.toString();
}

zoneEls.forEach((z,p)=>{
    z.addEventListener("mouseenter",()=>setActive(p));
    z.addEventListener("mouseleave",()=>clearActive());
    z.addEventListener("dblclick",()=>goToPeriod(p));
});

svg.addEventListener("wheel",e=>{
    e.preventDefault();
    zoom(st.scale*(e.deltaY<0?1.12:0.90),svgPt(e.clientX,e.clientY));
},{passive:false});

svg.addEventListener("pointerdown",e=>{
    if(e.target.closest(".mg-bubble-link")||e.target.closest(".mg-zone")) return;
    dragStart(svgPt(e.clientX,e.clientY),e.pointerId);
});
svg.addEventListener("pointermove",e=>{
    if(!st.drag||st.pid!==e.pointerId) return;
    dragMove(svgPt(e.clientX,e.clientY));
});
svg.addEventListener("pointerup",()=>dragEnd());
svg.addEventListener("pointerleave",()=>dragEnd());

svg.addEventListener("touchstart",e=>{
    if(e.touches.length===2){
        const a=svgPt(e.touches[0].clientX,e.touches[0].clientY);
        const b=svgPt(e.touches[1].clientX,e.touches[1].clientY);
        st.touch="pinch"; st.pinchD=Math.hypot(a.x-b.x,a.y-b.y); return;
    }
    if(e.touches.length===1&&!e.target.closest(".mg-bubble-link")&&!e.target.closest(".mg-zone")){
        st.touch="drag"; dragStart(svgPt(e.touches[0].clientX,e.touches[0].clientY));
    }
},{passive:true});

svg.addEventListener("touchmove",e=>{
    if(st.touch==="pinch"&&e.touches.length===2){
        const a=svgPt(e.touches[0].clientX,e.touches[0].clientY);
        const b=svgPt(e.touches[1].clientX,e.touches[1].clientY);
        const nd=Math.hypot(a.x-b.x,a.y-b.y);
        if(st.pinchD>0) zoom(st.scale*nd/st.pinchD,{x:(a.x+b.x)/2,y:(a.y+b.y)/2});
        st.pinchD=nd; return;
    }
    if(st.touch==="drag"&&e.touches.length===1)
        dragMove(svgPt(e.touches[0].clientX,e.touches[0].clientY));
},{passive:true});

svg.addEventListener("touchend",()=>{ st.touch=null;st.pinchD=0;dragEnd(); });

/* ===== Details 排他 ===== */
["detAction","detFilter"].forEach(id=>{
    const d=document.getElementById(id);
    if(!d) return;
    d.addEventListener("toggle",()=>{
        if(!d.open) return;
        ["detAction","detFilter"].forEach(oid=>{
            const o=document.getElementById(oid);
            if(o&&o!==d) o.removeAttribute("open");
        });
    });
});
document.addEventListener("click",e=>{
    ["detAction","detFilter"].forEach(id=>{
        const el=document.getElementById(id);
        if(el&&!el.contains(e.target)) el.removeAttribute("open");
    });
});

/* ===== 星パーティクル（Canvas） ===== */
(function(){
    const c=document.getElementById("starCanvas");
    if(!c) return;
    const ctx=c.getContext("2d");
    function resize(){ c.width=window.innerWidth; c.height=window.innerHeight; }
    resize();
    window.addEventListener("resize",resize);
    const stars=Array.from({length:160},()=>({
        x:Math.random(),y:Math.random(),
        r:Math.random()*1.5+0.3,
        a:Math.random()*0.8+0.2,
        speed:Math.random()*0.0004+0.0001,
        phase:Math.random()*Math.PI*2,
    }));
    function draw(t){
        ctx.clearRect(0,0,c.width,c.height);
        stars.forEach(s=>{
            const alpha=s.a*(0.6+0.4*Math.sin(t*s.speed*1000+s.phase));
            ctx.beginPath();
            ctx.arc(s.x*c.width,s.y*c.height,s.r,0,Math.PI*2);
            ctx.fillStyle=`rgba(200,225,255,${alpha})`;
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    requestAnimationFrame(draw);
})();

})();
</script>
@endif
@endsection
