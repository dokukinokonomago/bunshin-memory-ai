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
                        <button class="mem-status-modal-button" type="button" data-log-modal-open>
                            過去ログを表示
                        </button>

                        <div class="mem-status-modal" data-log-modal hidden>
                            <div class="mem-status-modal-backdrop" data-log-modal-close></div>
                            <div class="mem-status-modal-dialog" role="dialog" aria-modal="true" aria-label="過去ログ">
                                <div class="mem-status-modal-head">
                                    <strong>過去ログ</strong>
                                    <button class="mem-status-modal-x" type="button" data-log-modal-close>閉じる</button>
                                </div>
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
                            </div>
                        </div>
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
    border: 1px solid rgba(255,255,255,0.34);
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
    backdrop-filter: blur(18px) saturate(1.1);
    box-shadow:
        0 16px 30px rgba(0,0,0,0.18),
        inset 0 1px 0 rgba(255,255,255,0.48),
        inset 0 -10px 18px rgba(116, 162, 255, 0.16);
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

.mem-glass-pill::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background:
        radial-gradient(circle at 0% 100%, rgba(255, 199, 118, 0.18), transparent 36%),
        radial-gradient(circle at 100% 0%, rgba(109, 201, 255, 0.2), transparent 38%);
    pointer-events: none;
}

.mem-glass-pill--blue {
    background:
        linear-gradient(180deg, rgba(255,255,255,0.22), rgba(255,255,255,0.08)),
        linear-gradient(135deg, rgba(74, 146, 255, 0.48), rgba(88, 208, 255, 0.24) 58%, rgba(255,255,255,0.08));
    color: rgba(234,246,255,0.98);
    box-shadow:
        0 16px 30px rgba(0,0,0,0.18),
        0 0 26px rgba(90, 188, 255, 0.18),
        inset 0 1px 0 rgba(255,255,255,0.52),
        inset 0 -10px 18px rgba(88, 144, 255, 0.18);
}

.mem-glass-pill--purple {
    background:
        linear-gradient(180deg, rgba(255,255,255,0.22), rgba(255,255,255,0.08)),
        linear-gradient(135deg, rgba(176, 118, 255, 0.4), rgba(105, 96, 255, 0.26) 58%, rgba(255,255,255,0.08));
    color: rgba(244,236,255,0.98);
    box-shadow:
        0 16px 30px rgba(0,0,0,0.18),
        0 0 26px rgba(168, 118, 255, 0.16),
        inset 0 1px 0 rgba(255,255,255,0.52),
        inset 0 -10px 18px rgba(120, 104, 255, 0.16);
}

.mem-glass-pill:hover {
    transform: translateY(-2px) scale(1.03);
    box-shadow:
        0 20px 38px rgba(0,0,0,0.22),
        0 0 36px rgba(98, 194, 255, 0.22),
        inset 0 1px 0 rgba(255,255,255,0.58),
        inset 0 -10px 18px rgba(88, 144, 255, 0.18);
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
        linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.06)),
        linear-gradient(145deg, rgba(10,22,62,0.9) 0%, rgba(6,12,36,0.94) 100%);
    border: 1px solid rgba(255,255,255,0.18);
    box-shadow:
        0 0 0 1px rgba(0,100,200,0.12),
        0 30px 60px rgba(0,0,0,0.75),
        0 0 30px rgba(0,80,200,0.18),
        inset 0 1px 0 rgba(255,255,255,0.22);
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
    transition: background 0.16s, transform 0.14s, box-shadow 0.14s;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    border: 1px solid transparent;
}
.mem-drop-item:hover {
    background:
        linear-gradient(180deg, rgba(255,255,255,0.14), rgba(255,255,255,0.04)),
        rgba(93, 158, 255, 0.12);
    border-color: rgba(255,255,255,0.16);
    box-shadow:
        0 12px 24px rgba(0,0,0,0.12),
        inset 0 1px 0 rgba(255,255,255,0.2);
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
    border: 1px solid rgba(255,255,255,0.24);
    background:
        linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05)),
        radial-gradient(circle at 0% 100%, rgba(255, 199, 118, 0.14), transparent 36%),
        radial-gradient(circle at 100% 0%, rgba(109, 201, 255, 0.16), transparent 38%),
        rgba(0,16,60,0.34);
    color: rgba(220,238,255,0.92);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.16s;
    box-shadow:
        0 12px 24px rgba(0,0,0,0.12),
        inset 0 1px 0 rgba(255,255,255,0.26);
    backdrop-filter: blur(14px);
}
.mem-filter-chip:hover {
    border-color: rgba(255,255,255,0.4);
    background:
        linear-gradient(180deg, rgba(255,255,255,0.2), rgba(255,255,255,0.06)),
        radial-gradient(circle at 0% 100%, rgba(95, 176, 255, 0.22), transparent 40%),
        rgba(0,60,180,0.24);
    color: #fff;
}
.mem-filter-chip.is-on {
    border-color: rgba(208,235,255,0.48);
    background:
        linear-gradient(180deg, rgba(255,255,255,0.22), rgba(255,255,255,0.08)),
        radial-gradient(circle at 0% 100%, rgba(95, 176, 255, 0.38), transparent 40%),
        radial-gradient(circle at 100% 0%, rgba(255, 203, 124, 0.14), transparent 42%),
        rgba(0,60,180,0.22);
    color: #fff;
    box-shadow:
        0 14px 28px rgba(0,0,0,0.14),
        0 0 24px rgba(90, 188, 255, 0.18),
        inset 0 1px 0 rgba(255,255,255,0.34);
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
    border: 1px solid rgba(255,255,255,0.24);
    background:
        linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05)),
        radial-gradient(circle at 100% 0%, rgba(109, 201, 255, 0.14), transparent 38%),
        rgba(0,40,150,0.2);
    color: rgba(228,243,255,0.96);
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.16s;
    box-shadow:
        0 12px 20px rgba(0,0,0,0.12),
        inset 0 1px 0 rgba(255,255,255,0.24);
    backdrop-filter: blur(14px);
}
.mem-layer-btn:hover { border-color: rgba(255,255,255,0.42); }

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

.mem-status-modal-button {
    min-height: 42px;
    border: 1px solid rgba(255,255,255,0.24);
    border-radius: 16px;
    background:
        linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05)),
        radial-gradient(circle at 0% 100%, rgba(255, 203, 121, 0.14), transparent 36%),
        radial-gradient(circle at 100% 0%, rgba(109, 201, 255, 0.16), transparent 38%),
        rgba(255,255,255,0.03);
    color: rgba(228, 240, 255, 0.94);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    cursor: pointer;
    box-shadow:
        0 14px 24px rgba(0,0,0,0.14),
        inset 0 1px 0 rgba(255,255,255,0.24);
    backdrop-filter: blur(14px);
}

.mem-status-modal[hidden] {
    display: none;
}

.mem-status-modal {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: grid;
    place-items: center;
    padding: 24px;
}

.mem-status-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 4, 16, 0.72);
}

.mem-status-modal-dialog {
    position: relative;
    z-index: 1;
    width: min(560px, calc(100vw - 40px));
    max-height: min(72vh, 720px);
    overflow: auto;
    padding: 18px;
    border-radius: 22px;
    border: 1px solid rgba(100, 164, 255, 0.18);
    background: linear-gradient(180deg, rgba(10, 20, 58, 0.98), rgba(3, 8, 28, 0.98));
    box-shadow: 0 30px 80px rgba(0,0,0,0.46);
}

.mem-status-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.mem-status-modal-head strong {
    color: rgba(240, 247, 255, 0.96);
    font-size: 18px;
    font-weight: 800;
}

.mem-status-modal-x {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid rgba(255,255,255,0.22);
    border-radius: 999px;
    background:
        linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05)),
        rgba(255,255,255,0.04);
    color: rgba(192, 224, 255, 0.92);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    box-shadow:
        0 12px 20px rgba(0,0,0,0.12),
        inset 0 1px 0 rgba(255,255,255,0.22);
}

.mem-status-logs-stack {
    display: grid;
    gap: 10px;
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

/* 年代クラスタ */
.mg-period-wrap {
    opacity: 0;
    animation: mgReveal 0.82s cubic-bezier(0.22,0.85,0.32,1) var(--d,0s) forwards;
    transition: transform 0.52s cubic-bezier(0.2,0.8,0.2,1), opacity 0.34s ease, filter 0.34s ease;
}

.mg-period-wrap.is-focused {
    opacity: 1;
    filter: none;
}

.mg-period-wrap.is-muted {
    opacity: 0.28;
    filter: saturate(0.72) brightness(0.82);
}

.mg-period-anchor {
    cursor: pointer;
    text-decoration: none;
}

.mg-period-shell {
    transition: transform 0.42s cubic-bezier(0.2,0.8,0.2,1), opacity 0.32s ease;
}

.mg-period-wrap:hover .mg-period-shell {
    transform: scale(1.03);
}

.mg-period-shell-aura {
    opacity: 0.52;
}

.mg-period-shell-fill {
    fill: rgba(255,255,255,0.02);
    stroke: rgba(206, 235, 255, 0.28);
    stroke-width: 1.8;
}

.mg-period-shell-rim {
    fill: none;
    stroke: rgba(235, 246, 255, 0.48);
    stroke-width: 1.2;
    stroke-dasharray: 7 11;
    opacity: 0.62;
}

.mg-period-shell-inner {
    fill: rgba(130, 198, 255, 0.04);
    stroke: rgba(214, 236, 255, 0.16);
    stroke-width: 1;
}

.mg-period-wrap.is-focused .mg-period-shell-fill {
    fill: rgba(255,255,255,0.045);
    stroke: rgba(234, 246, 255, 0.44);
}

.mg-period-wrap.is-focused .mg-period-shell-rim {
    opacity: 0.88;
}

.mg-period-title,
.mg-period-caption,
.mg-period-count,
.mg-period-count-unit,
.mg-memory-label,
.mg-memory-subline {
    text-anchor: middle;
    dominant-baseline: middle;
    paint-order: stroke;
    stroke: rgba(5, 10, 24, 0.72);
    stroke-width: 3px;
    stroke-linejoin: round;
    pointer-events: none;
}

.mg-period-title {
    fill: rgba(245, 250, 255, 0.98);
    font-size: 26px;
    font-weight: 800;
    letter-spacing: 0.06em;
}

.mg-period-count {
    fill: rgba(255,255,255,0.98);
    font-size: 72px;
    font-weight: 900;
}

.mg-period-count-unit {
    fill: rgba(216, 232, 255, 0.82);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.18em;
}

.mg-period-caption {
    fill: rgba(196, 221, 255, 0.78);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.1em;
}

.mg-period-empty {
    fill: rgba(206, 225, 255, 0.72);
    font-size: 14px;
    font-weight: 700;
}

.mg-period-action {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
    border-radius: 999px;
    border: 1px solid rgba(182, 227, 255, 0.42);
    background:
        linear-gradient(180deg, rgba(14, 25, 66, 0.92), rgba(8, 14, 36, 0.92)),
        radial-gradient(circle at 20% 20%, rgba(255,255,255,0.18), transparent 42%);
    color: rgba(244, 249, 255, 0.98);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-decoration: none;
    box-shadow:
        0 0 0 1px rgba(255,255,255,0.05) inset,
        0 0 24px rgba(95, 193, 255, 0.18);
    backdrop-filter: blur(10px);
}

.mg-memory-orb-wrap {
    transition: transform 0.24s ease, opacity 0.24s ease, filter 0.24s ease;
}

.mg-memory-orb-wrap:hover {
    transform: scale(1.06);
    filter: brightness(1.08) saturate(1.08);
}

.mg-memory-orb-link {
    cursor: pointer;
    text-decoration: none;
}

.mg-memory-core {
    opacity: 0.95;
}

.mg-memory-rim {
    fill: none;
    stroke-width: 1.5;
    opacity: 0.84;
}

.mg-memory-label {
    fill: rgba(255,255,255,0.98);
    font-weight: 800;
}

.mg-memory-subline {
    fill: rgba(232, 241, 255, 0.82);
    font-weight: 600;
    stroke-width: 2px;
}

/* ── キーフレーム ────────────────────────── */
@keyframes mgReveal {
    0%   { opacity:0; filter:blur(14px) saturate(0.2); }
    70%  { opacity:0.90; }
    100% { opacity:1;  filter:blur(0)   saturate(1); }
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

const memories = @json($bubbleMemories);
const periods = @json($periods);
const periodCounts = @json($periodBubbleCounts);
const selPeriod = @json($selectedPeriod);
const bubblesRoute = @json($bubbleBaseRoute);
const memoriesRoute = @json(route('memories.index'));
const NS = "http://www.w3.org/2000/svg";
const VP = { w: 1400, h: 900 };

const svg = document.getElementById("memSvg");
const defs = document.getElementById("memDefs");
const viewport = document.getElementById("memViewport");
const gridG = document.getElementById("memGrid");
const periodsG = document.getElementById("memPeriods");

const PERIOD_ACCENTS = {
    "幼少期":["#ff6b6b", "#ff4757"],
    "小学生":["#ffa94d", "#ff7c1f"],
    "中学生":["#ffe066", "#ffbc00"],
    "高校生":["#69db7c", "#2dbe4e"],
    "大学生":["#4dabf7", "#1c7ed6"],
    "成人期":["#9775fa", "#6741d9"],
    "不明":["#f06595", "#c2255c"],
};

const HOME_ANCHORS = {
    "幼少期": { x: 240, y: 285 },
    "小学生": { x: 330, y: 690 },
    "中学生": { x: 650, y: 620 },
    "高校生": { x: 930, y: 635 },
    "大学生": { x: 1080, y: 250 },
    "成人期": { x: 1210, y: 470 },
    "不明":   { x: 690, y: 210 },
};

const FOCUS_CENTER = { x: 700, y: 470 };
const FOCUS_SLOTS = [
    { x: 225, y: 170 },
    { x: 1115, y: 170 },
    { x: 1225, y: 430 },
    { x: 1115, y: 730 },
    { x: 285, y: 730 },
    { x: 150, y: 435 },
];

const ORB_LAYOUTS = [
    { x: -0.24, y: -0.16, r: 0.19, z: 2 },
    { x:  0.01, y: -0.24, r: 0.18, z: 5 },
    { x:  0.24, y: -0.12, r: 0.17, z: 1 },
    { x: -0.36, y:  0.03, r: 0.16, z: 0 },
    { x: -0.05, y:  0.01, r: 0.22, z: 6 },
    { x:  0.28, y:  0.11, r: 0.16, z: 2 },
    { x: -0.20, y:  0.23, r: 0.18, z: 4 },
    { x:  0.08, y:  0.25, r: 0.17, z: 5 },
    { x:  0.33, y:  0.28, r: 0.15, z: 1 },
    { x: -0.08, y: -0.02, r: 0.17, z: 3 },
];

const st = {
    scale: 1,
    tx: 0,
    ty: 0,
    minS: 0.76,
    maxS: 1.58,
    drag: false,
    pid: null,
    sx: 0,
    sy: 0,
    stx: 0,
    sty: 0,
    touch: null,
    pinchD: 0,
    bounds: null,
};

const periodEls = new Map();
let activeFocus = selPeriod !== "すべて" ? selPeriod : null;

function el(tag, attrs = {}) {
    const node = document.createElementNS(NS, tag);
    for (const [key, value] of Object.entries(attrs)) {
        node.setAttribute(key, value);
    }
    return node;
}

function rgba(hex, alpha) {
    if (!hex || !hex.startsWith("#")) {
        return `rgba(80,140,255,${alpha})`;
    }

    const raw = hex.length === 4
        ? hex.slice(1).split("").map((char) => char + char).join("")
        : hex.slice(1);

    const r = parseInt(raw.slice(0, 2), 16);
    const g = parseInt(raw.slice(2, 4), 16);
    const b = parseInt(raw.slice(4, 6), 16);

    return `rgba(${r},${g},${b},${alpha})`;
}

function seededRand(seed) {
    let value = seed >>> 0;

    return function nextRand() {
        value = (value * 1664525 + 1013904223) >>> 0;
        return value / 0xffffffff;
    };
}

function makeGlowDefs(prefix, colors, shell = false) {
    const [c0, c1] = colors?.length ? colors : ["#7db8ff", "#4d7fff"];
    const bodyId = `${prefix}-body`;
    const rimId = `${prefix}-rim`;
    const auraId = `${prefix}-aura`;

    const body = el("radialGradient", { id: bodyId, cx: "32%", cy: "26%", r: "76%" });
    body.append(
        el("stop", { offset: "0%", "stop-color": rgba("#ffffff", shell ? 0.30 : 0.42) }),
        el("stop", { offset: "18%", "stop-color": rgba(c0, shell ? 0.16 : 0.74) }),
        el("stop", { offset: "58%", "stop-color": rgba(c0, shell ? 0.08 : 0.86) }),
        el("stop", { offset: "100%", "stop-color": rgba(c1, shell ? 0.03 : 0.94) }),
    );

    const rim = el("linearGradient", { id: rimId, x1: "10%", y1: "0%", x2: "100%", y2: "100%" });
    rim.append(
        el("stop", { offset: "0%", "stop-color": rgba("#ffffff", shell ? 0.9 : 0.8) }),
        el("stop", { offset: "45%", "stop-color": rgba(c0, shell ? 0.52 : 0.72) }),
        el("stop", { offset: "100%", "stop-color": rgba(c1, 0.92) }),
    );

    const aura = el("radialGradient", { id: auraId, cx: "50%", cy: "50%", r: "50%" });
    aura.append(
        el("stop", { offset: "0%", "stop-color": rgba(c0, 0) }),
        el("stop", { offset: "62%", "stop-color": rgba(c0, shell ? 0.10 : 0.18) }),
        el("stop", { offset: "100%", "stop-color": rgba(c1, shell ? 0.20 : 0.36) }),
    );

    defs.append(body, rim, aura);

    return { bodyId, rimId, auraId };
}

function buildWorld() {
    const buckets = new Map();
    memories.forEach((memory) => {
        if (!buckets.has(memory.period)) {
            buckets.set(memory.period, []);
        }
        buckets.get(memory.period).push(memory);
    });

    const visiblePeriods = selPeriod === "すべて"
        ? periods
        : periods.filter((period) => period === selPeriod);

    const nodes = visiblePeriods.map((period) => {
        const bucket = (buckets.get(period) ?? []).slice(0, 10);
        const count = periodCounts[period] ?? bucket.length;
        const accent = bucket[0]?.periodColors ?? PERIOD_ACCENTS[period] ?? ["#7db8ff", "#4d7fff"];
        const home = HOME_ANCHORS[period] ?? { x: 700, y: 450 };
        const shellRadius = selPeriod === "すべて"
            ? Math.max(150, Math.min(205, 148 + Math.min(count, 20) * 3.2))
            : 245;

        const orbNodes = bucket
            .map((memory, index) => {
                const layout = ORB_LAYOUTS[index] ?? ORB_LAYOUTS[ORB_LAYOUTS.length - 1];
                const rand = seededRand(memory.id * 7919 + index * 193);
                const jitterX = (rand() - 0.5) * shellRadius * 0.05;
                const jitterY = (rand() - 0.5) * shellRadius * 0.05;
                const radius = shellRadius * layout.r * (0.94 + rand() * 0.1);
                const tagline = (memory.tags ?? []).find((tag) => tag !== period && tag !== memory.emotion) ?? memory.emotion;

                return {
                    ...memory,
                    x: layout.x * shellRadius + jitterX,
                    y: layout.y * shellRadius + jitterY,
                    r: radius,
                    z: layout.z + rand() * 0.2,
                    tagline,
                };
            })
            .sort((left, right) => left.z - right.z);

        return {
            period,
            count,
            visibleCount: bucket.length,
            homeX: home.x,
            homeY: home.y,
            r: shellRadius,
            accent,
            items: orbNodes,
        };
    });

    return { nodes };
}

function buildBounds(world) {
    let x0 = Infinity;
    let x1 = -Infinity;
    let y0 = Infinity;
    let y1 = -Infinity;

    world.nodes.forEach((node) => {
        x0 = Math.min(x0, node.homeX - node.r - 80);
        x1 = Math.max(x1, node.homeX + node.r + 80);
        y0 = Math.min(y0, node.homeY - node.r - 100);
        y1 = Math.max(y1, node.homeY + node.r + 100);
    });

    return { x0, y0, x1, y1, w: x1 - x0, h: y1 - y0 };
}

function drawGrid(bounds) {
    const step = 220;

    for (let x = Math.floor(bounds.x0 / step) * step; x <= bounds.x1; x += step) {
        gridG.append(el("line", {
            x1: x,
            y1: bounds.y0 - 120,
            x2: x,
            y2: bounds.y1 + 120,
            class: "mg-grid-line",
        }));
    }

    for (let y = Math.floor(bounds.y0 / step) * step; y <= bounds.y1; y += step) {
        gridG.append(el("line", {
            x1: bounds.x0 - 120,
            y1: y,
            x2: bounds.x1 + 120,
            y2: y,
            class: "mg-grid-line",
        }));
    }
}

function buildPeriodUrl(period) {
    const url = new URL(bubblesRoute, location.origin);
    url.searchParams.set("period", period);
    return url.toString();
}

function buildListUrl(period) {
    const url = new URL(memoriesRoute, location.origin);
    if (period !== "すべて") {
        url.searchParams.set("period", period);
    }
    return url.toString();
}

function drawMemoryOrb(memory, period, parent, index) {
    const defsRef = makeGlowDefs(`memory-${period}-${memory.id}-${index}`, memory.colors ?? ["#dce9ff", "#63a6ff"]);
    const wrap = el("g", {
        class: "mg-memory-orb-wrap",
        transform: `translate(${memory.x.toFixed(2)} ${memory.y.toFixed(2)})`,
    });
    const link = el("a", {
        href: memory.url,
        class: "mg-memory-orb-link",
        "aria-label": `${period}の記憶 ${memory.label}`,
    });

    link.append(
        el("circle", {
            cx: 0,
            cy: 0,
            r: (memory.r * 1.38).toFixed(2),
            fill: `url(#${defsRef.auraId})`,
            filter: "url(#fAura)",
            opacity: "0.78",
        })
    );

    const body = el("g", { class: "mg-memory-core" });
    body.append(
        el("circle", {
            cx: 0,
            cy: 0,
            r: memory.r.toFixed(2),
            fill: `url(#${defsRef.bodyId})`,
            filter: "url(#fShadow)",
        }),
        el("circle", {
            cx: 0,
            cy: 0,
            r: (memory.r - 1.5).toFixed(2),
            class: "mg-memory-rim",
            stroke: `url(#${defsRef.rimId})`,
        }),
        el("ellipse", {
            cx: (-memory.r * 0.24).toFixed(2),
            cy: (-memory.r * 0.25).toFixed(2),
            rx: Math.max(6, memory.r * 0.28).toFixed(2),
            ry: Math.max(3, memory.r * 0.14).toFixed(2),
            fill: "rgba(255,255,255,0.48)",
            transform: `rotate(-22 ${(-memory.r * 0.24).toFixed(2)} ${(-memory.r * 0.25).toFixed(2)})`,
        }),
        el("circle", {
            cx: (-memory.r * 0.28).toFixed(2),
            cy: (-memory.r * 0.3).toFixed(2),
            r: Math.max(3, memory.r * 0.12).toFixed(2),
            fill: "rgba(255,255,255,0.9)",
            filter: "url(#fSpec)",
        }),
        el("circle", {
            cx: (memory.r * 0.24).toFixed(2),
            cy: (memory.r * 0.26).toFixed(2),
            r: Math.max(2, memory.r * 0.08).toFixed(2),
            fill: "rgba(255,255,255,0.18)",
        })
    );

    const label = el("text", {
        x: "0",
        y: (memory.r > 28 ? "-2" : "0"),
        class: "mg-memory-label",
        "font-size": Math.max(10, memory.r * 0.34).toFixed(2),
    });
    label.textContent = memory.label;
    body.append(label);

    if (memory.r > 28) {
        const subline = el("text", {
            x: "0",
            y: (memory.r * 0.28).toFixed(2),
            class: "mg-memory-subline",
            "font-size": Math.max(8, memory.r * 0.18).toFixed(2),
        });
        subline.textContent = memory.tagline;
        body.append(subline);
    }

    const title = el("title");
    title.textContent = `${memory.emotion} | ${memory.content}`;
    body.append(title);

    link.append(body);
    wrap.append(link);
    parent.append(wrap);
}

function drawPeriodNode(node, index) {
    const shellDefs = makeGlowDefs(`period-${node.period}-${index}`, node.accent, true);
    const wrap = el("g", {
        class: "mg-period-wrap",
        "data-period": node.period,
        style: `--d:${(index * 0.06).toFixed(2)}s`,
        transform: `translate(${node.homeX} ${node.homeY}) scale(1)`,
    });

    const anchorHref = selPeriod === node.period ? buildListUrl(node.period) : buildPeriodUrl(node.period);
    const shellLink = el("a", {
        href: anchorHref,
        class: "mg-period-anchor",
        "aria-label": `${node.period}の記憶 ${node.count}件`,
    });
    const shell = el("g", { class: "mg-period-shell" });

    shell.append(
        el("circle", {
            cx: 0,
            cy: 0,
            r: (node.r + 28).toFixed(2),
            class: "mg-period-shell-aura",
            fill: `url(#${shellDefs.auraId})`,
            filter: "url(#fAura)",
        }),
        el("circle", {
            cx: 0,
            cy: 0,
            r: node.r.toFixed(2),
            class: "mg-period-shell-fill",
            fill: `url(#${shellDefs.bodyId})`,
        }),
        el("circle", {
            cx: 0,
            cy: 0,
            r: (node.r - 7).toFixed(2),
            class: "mg-period-shell-rim",
            stroke: `url(#${shellDefs.rimId})`,
        }),
        el("circle", {
            cx: 0,
            cy: 0,
            r: (node.r * 0.76).toFixed(2),
            class: "mg-period-shell-inner",
        }),
        el("ellipse", {
            cx: (-node.r * 0.24).toFixed(2),
            cy: (-node.r * 0.28).toFixed(2),
            rx: (node.r * 0.24).toFixed(2),
            ry: (node.r * 0.11).toFixed(2),
            fill: "rgba(255,255,255,0.16)",
            transform: `rotate(-18 ${(-node.r * 0.24).toFixed(2)} ${(-node.r * 0.28).toFixed(2)})`,
        }),
        el("circle", {
            cx: (-node.r * 0.3).toFixed(2),
            cy: (-node.r * 0.29).toFixed(2),
            r: Math.max(10, node.r * 0.08).toFixed(2),
            fill: "rgba(255,255,255,0.32)",
            filter: "url(#fSpec)",
        })
    );

    const periodTitle = el("text", {
        x: "0",
        y: (-node.r * 0.4).toFixed(2),
        class: "mg-period-title",
    });
    periodTitle.textContent = node.period;
    shell.append(periodTitle);

    const count = el("text", {
        x: "0",
        y: (-node.r * 0.03).toFixed(2),
        class: "mg-period-count",
    });
    count.textContent = String(node.count);
    shell.append(count);

    const unit = el("text", {
        x: "0",
        y: (node.r * 0.18).toFixed(2),
        class: "mg-period-count-unit",
    });
    unit.textContent = "MEMORIES";
    shell.append(unit);

    const caption = el("text", {
        x: "0",
        y: (node.r * 0.34).toFixed(2),
        class: `mg-period-caption${node.visibleCount === 0 ? " mg-period-empty" : ""}`,
    });
    caption.textContent = node.visibleCount > 0
        ? `この層に ${node.visibleCount} / 最大10個を表示`
        : "まだ記憶がありません";
    shell.append(caption);

    shellLink.append(shell);
    wrap.append(shellLink);

    const orbGroup = el("g", { class: "mg-memory-cluster" });
    node.items.forEach((memory, itemIndex) => drawMemoryOrb(memory, node.period, orbGroup, itemIndex));
    wrap.append(orbGroup);

    if (selPeriod === node.period) {
        const actionFo = el("foreignObject", {
            x: (-72).toString(),
            y: (node.r * 0.49).toFixed(2),
            width: "144",
            height: "36",
        });
        const actionLink = document.createElement("a");
        actionLink.setAttribute("href", buildListUrl(node.period));
        actionLink.setAttribute("class", "mg-period-action");
        actionLink.textContent = "一覧を見る";
        actionFo.append(actionLink);
        wrap.append(actionFo);
    }

    periodsG.append(wrap);
    periodEls.set(node.period, wrap);
}

function layoutFocusedPeriod(world, focusPeriod) {
    const secondaryNodes = world.nodes.filter((node) => node.period !== focusPeriod);
    let slotIndex = 0;

    world.nodes.forEach((node) => {
        const wrap = periodEls.get(node.period);
        if (!wrap) {
            return;
        }

        if (!focusPeriod) {
            wrap.setAttribute("transform", `translate(${node.homeX} ${node.homeY}) scale(1)`);
            wrap.classList.remove("is-focused", "is-muted");
            return;
        }

        if (node.period === focusPeriod) {
            wrap.setAttribute("transform", `translate(${FOCUS_CENTER.x} ${FOCUS_CENTER.y}) scale(1.24)`);
            wrap.classList.add("is-focused");
            wrap.classList.remove("is-muted");
            return;
        }

        const slot = FOCUS_SLOTS[slotIndex] ?? FOCUS_SLOTS[FOCUS_SLOTS.length - 1];
        slotIndex += 1;
        wrap.setAttribute("transform", `translate(${slot.x} ${slot.y}) scale(0.58)`);
        wrap.classList.add("is-muted");
        wrap.classList.remove("is-focused");
    });

    if (secondaryNodes.length === 0 && focusPeriod) {
        const target = periodEls.get(focusPeriod);
        target?.classList.add("is-focused");
    }
}

function svgPt(clientX, clientY) {
    const rect = svg.getBoundingClientRect();
    return {
        x: ((clientX - rect.left) / rect.width) * VP.w,
        y: ((clientY - rect.top) / rect.height) * VP.h,
    };
}

function clamp() {
    const bounds = st.bounds;
    const margin = 120;
    const scaledWidth = bounds.w * st.scale;
    const scaledHeight = bounds.h * st.scale;

    if (scaledWidth <= VP.w - margin * 2) {
        st.tx = (VP.w - scaledWidth) / 2 - bounds.x0 * st.scale;
    } else {
        st.tx = Math.min(margin - bounds.x0 * st.scale, Math.max(VP.w - (bounds.x1 * st.scale + margin), st.tx));
    }

    if (scaledHeight <= VP.h - margin * 2) {
        st.ty = (VP.h - scaledHeight) / 2 - bounds.y0 * st.scale;
    } else {
        st.ty = Math.min(margin - bounds.y0 * st.scale, Math.max(VP.h - (bounds.y1 * st.scale + margin), st.ty));
    }
}

function applyTransform() {
    clamp();
    viewport.setAttribute("transform", `matrix(${st.scale} 0 0 ${st.scale} ${st.tx} ${st.ty})`);
}

function frameWorld() {
    const bounds = st.bounds;
    const padX = 220;
    const padY = 180;
    st.scale = Math.min(st.maxS, Math.max(st.minS, Math.min(VP.w / (bounds.w + padX), VP.h / (bounds.h + padY))));
    st.tx = (VP.w / 2) - (((bounds.x0 + bounds.x1) / 2) * st.scale);
    st.ty = (VP.h / 2) - (((bounds.y0 + bounds.y1) / 2) * st.scale);
    applyTransform();
}

function zoom(nextScale, point) {
    const scale = Math.min(st.maxS, Math.max(st.minS, nextScale));
    const worldX = (point.x - st.tx) / st.scale;
    const worldY = (point.y - st.ty) / st.scale;
    st.tx = point.x - worldX * scale;
    st.ty = point.y - worldY * scale;
    st.scale = scale;
    applyTransform();
}

function dragStart(point, pointerId = null) {
    st.drag = true;
    st.pid = pointerId;
    st.sx = point.x;
    st.sy = point.y;
    st.stx = st.tx;
    st.sty = st.ty;
    svg.classList.add("dragging");
}

function dragMove(point) {
    if (!st.drag) {
        return;
    }

    st.tx = st.stx + (point.x - st.sx);
    st.ty = st.sty + (point.y - st.sy);
    applyTransform();
}

function dragEnd() {
    st.drag = false;
    st.pid = null;
    svg.classList.remove("dragging");
}

const world = buildWorld();
st.bounds = buildBounds(world);
drawGrid(st.bounds);
world.nodes.forEach((node, index) => drawPeriodNode(node, index));
layoutFocusedPeriod(world, activeFocus);
frameWorld();

world.nodes.forEach((node) => {
    const wrap = periodEls.get(node.period);
    if (!wrap) {
        return;
    }

    wrap.addEventListener("mouseenter", () => {
        activeFocus = node.period;
        layoutFocusedPeriod(world, activeFocus);
    });

    wrap.addEventListener("mouseleave", () => {
        activeFocus = selPeriod !== "すべて" ? selPeriod : null;
        layoutFocusedPeriod(world, activeFocus);
    });
});

svg.addEventListener("wheel",e=>{
    e.preventDefault();
    zoom(st.scale*(e.deltaY<0?1.12:0.90),svgPt(e.clientX,e.clientY));
},{passive:false});

svg.addEventListener("pointerdown",e=>{
    if(e.target.closest(".mg-period-anchor")||e.target.closest(".mg-memory-orb-link")||e.target.closest(".mg-period-action")) return;
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
    if(e.touches.length===1&&!e.target.closest(".mg-period-anchor")&&!e.target.closest(".mg-memory-orb-link")&&!e.target.closest(".mg-period-action")){
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

const logModal = document.querySelector("[data-log-modal]");
const logModalOpen = document.querySelector("[data-log-modal-open]");
const logModalClosers = document.querySelectorAll("[data-log-modal-close]");

if(logModal && logModalOpen){
    logModalOpen.addEventListener("click",()=>{ logModal.hidden = false; });
    logModalClosers.forEach(btn=>{
        btn.addEventListener("click",()=>{ logModal.hidden = true; });
    });
    document.addEventListener("keydown",e=>{
        if(e.key==="Escape") logModal.hidden = true;
    });
}

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
