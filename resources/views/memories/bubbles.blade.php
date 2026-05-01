@extends('layouts.app')

@section('title', 'YOUの記憶 | 分身AI MVP')
@section('page_class', 'page-bubbles-full')

@section('content')
<div class="mem-universe" id="memUniverse">
    <canvas id="starCanvas" class="star-canvas" aria-hidden="true"></canvas>

    <nav class="mem-nav">
        <div class="mem-nav-left">
            <span class="mem-nav-eyebrow">PERSONAL MEMORY ARCHIVE</span>
            <h1 class="mem-nav-title">YOUの記憶</h1>
        </div>

        <div class="mem-nav-right">
            <div class="mem-count-orb">
                <span class="mem-count-num">{{ $allCount }}</span>
                <span class="mem-count-label">MEMORIES</span>
            </div>

            <div class="mem-action-stack">
                <div class="mem-session-label">{{ auth()->user()?->email }}</div>

                <details class="mem-details" id="detAction">
                    <summary class="mem-glass-pill mem-glass-pill--blue">
                        <span>今日は何をする？</span>
                        <svg class="mem-chevron" viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>
                    </summary>
                    <div class="mem-dropdown">
                        <a class="mem-drop-item" href="{{ route('memories.create') }}">
                            <span class="mem-drop-icon">＋</span>記憶を追加
                        </a>
                        <a class="mem-drop-item" href="{{ route('memories.index') }}">
                            <span class="mem-drop-icon">☰</span>記憶一覧
                        </a>
                        <a class="mem-drop-item" href="{{ route('memories.bubbles') }}">
                            <span class="mem-drop-icon">◎</span>全体俯瞰へ
                        </a>
                        <div class="mem-dropdown-divider"></div>
                        <div class="mem-dropdown-label">年代を選ぶ</div>
                        <div class="mem-dropdown mem-dropdown--filter mem-dropdown--inline">
                            <a class="mem-filter-chip {{ $selectedPeriod === 'すべて' ? 'is-on' : '' }}" href="{{ route('memories.bubbles') }}">すべて</a>
                            @foreach($periods as $period)
                                <a class="mem-filter-chip {{ $selectedPeriod === $period ? 'is-on' : '' }}" href="{{ route('memories.bubbles', ['period' => $period]) }}">{{ $period }}</a>
                            @endforeach
                        </div>
                        <div class="mem-dropdown-divider"></div>
                        @if(!$showGraveBubble)
                            <form method="POST" action="{{ route('memories.bubbles.reveal-all') }}">
                                @csrf
                                <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                                <button class="mem-drop-item mem-drop-submit" type="submit">
                                    <span class="mem-drop-icon">◌</span>全シャボンを表示
                                </button>
                            </form>
                        @else
                            <div class="mem-drop-item mem-drop-item-static">
                                <span class="mem-drop-icon">◌</span>隠しシャボンを表示中
                            </div>
                        @endif
                        <div class="mem-dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="mem-drop-item mem-drop-submit" type="submit">
                                <span class="mem-drop-icon">↗</span>ログアウト
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </nav>

    @if($bubbleMemories->isEmpty())
        <div class="mem-empty">
            <div class="mem-empty-orb"></div>
            <p>記憶がまだありません</p>
            <a href="{{ route('memories.create') }}" class="mem-glass-pill mem-glass-pill--blue" style="margin-top:20px;text-decoration:none;">記憶を追加する</a>
        </div>
    @else
        <div class="mem-stage" id="memStage">
            <div class="mem-hud">
                <div class="mem-hud-copy">
                    <span class="mem-hud-kicker" data-mode-kicker>LEVEL 0</span>
                    <strong data-mode-title>人生全体を俯瞰しています</strong>
                    <p data-mode-body>気になる年代のシャボン玉をクリックして、その中へ潜ってください。</p>
                </div>
                <div class="mem-hud-actions">
                    <button class="mem-hud-button" type="button" data-back-button hidden>ひとつ戻る</button>
                    <button class="mem-hud-button mem-hud-button-ghost" type="button" data-overview-button hidden>全体へ戻る</button>
                </div>
            </div>

            <aside class="mem-detail" data-memory-detail hidden>
                <div class="mem-detail-shell">
                    <div class="mem-detail-head">
                        <div>
                            <span class="mem-detail-kicker" data-detail-period></span>
                            <h2 data-detail-title></h2>
                        </div>
                        <button class="mem-detail-close" type="button" data-detail-close>閉じる</button>
                    </div>

                    <div class="mem-detail-meta">
                        <span class="mem-detail-chip" data-detail-emotion></span>
                        <span class="mem-detail-chip" data-detail-theme></span>
                        <span class="mem-detail-chip" data-detail-date></span>
                    </div>

                    <div class="mem-detail-visual">
                        <div class="mem-detail-visual-glow"></div>
                        <div class="mem-detail-visual-copy">
                            <strong data-detail-label></strong>
                            <small data-detail-cluster></small>
                        </div>
                    </div>

                    <div class="mem-detail-body">
                        <p data-detail-content></p>
                    </div>

                    <div class="mem-detail-comment">
                        <span>分身AIのひとこと</span>
                        <p data-detail-comment></p>
                    </div>

                    <div class="mem-detail-actions">
                        <button class="mem-hud-button" type="button" data-detail-back>年代の中へ戻る</button>
                        <a class="mem-hud-button mem-hud-button-ghost" href="#" data-detail-link>詳細ページを見る</a>
                    </div>
                </div>
            </aside>

            <aside class="mem-grave-panel" data-grave-panel hidden>
                <div class="mem-grave-panel-backdrop" data-grave-close></div>
                <div class="mem-grave-panel-shell">
                    <div class="mem-detail-head">
                        <div>
                            <span class="mem-detail-kicker">GRAVE MODE</span>
                            <h2>{{ $graveUnlocked ? '墓場まで' : '鍵のかかったシャボン' }}</h2>
                        </div>
                        <button class="mem-detail-close" type="button" data-grave-close>閉じる</button>
                    </div>

                    @if($graveUnlockError)
                        <p class="mem-grave-message mem-grave-message--error">{{ $graveUnlockError }}</p>
                    @endif

                    @if($graveUnlockSuccess)
                        <p class="mem-grave-message mem-grave-message--ok">{{ $graveUnlockSuccess }}</p>
                    @endif

                    @if($graveUnlocked)
                        <div class="mem-detail-comment">
                            <span>本人だけの保管領域</span>
                            <p>このシャボンは、表に出さない記憶をしまっておく隠しモードです。ログアウトすると表示も解錠状態も解除されます。</p>
                        </div>
                    @else
                        <p class="mem-grave-copy">このシャボンは本人専用です。4桁のパスコードを入力した時だけ中を確認できます。</p>

                        <form class="mem-grave-form" method="POST" action="{{ route('memories.bubbles.unlock-grave') }}">
                            @csrf
                            <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                            <label class="mem-grave-field">
                                <span>パスコード</span>
                                <input type="password" name="passcode" inputmode="numeric" pattern="[0-9]*" maxlength="20" placeholder="4桁" required>
                            </label>
                            <button class="mem-hud-button" type="submit">解錠する</button>
                        </form>
                    @endif
                </div>
            </aside>

            <svg id="memSvg" class="mem-svg" viewBox="0 0 1400 900" xmlns="http://www.w3.org/2000/svg" aria-label="記憶宇宙">
                <defs id="memDefs">
                    <filter id="fAura" x="-200%" y="-200%" width="500%" height="500%">
                        <feGaussianBlur stdDeviation="32"/>
                    </filter>
                    <filter id="fRimGlow" x="-80%" y="-80%" width="260%" height="260%">
                        <feGaussianBlur stdDeviation="6"/>
                    </filter>
                    <filter id="fShadow" x="-80%" y="-80%" width="260%" height="260%">
                        <feDropShadow dx="0" dy="18" stdDeviation="20" flood-color="#000008" flood-opacity="0.75"/>
                    </filter>
                    <filter id="fSpec" x="-120%" y="-120%" width="340%" height="340%">
                        <feGaussianBlur stdDeviation="9"/>
                    </filter>
                    <filter id="fBgGlow" x="-150%" y="-150%" width="400%" height="400%">
                        <feGaussianBlur stdDeviation="60"/>
                    </filter>
                </defs>

                <ellipse cx="700" cy="450" rx="520" ry="460" fill="rgba(10,20,80,0.22)" filter="url(#fBgGlow)"/>
                <ellipse cx="200" cy="780" rx="220" ry="170" fill="rgba(0,30,160,0.14)" filter="url(#fBgGlow)"/>
                <ellipse cx="1240" cy="130" rx="190" ry="150" fill="rgba(60,0,180,0.10)" filter="url(#fBgGlow)"/>

                <g id="memViewport">
                    <g id="memParallaxBack"></g>
                    <g id="memGrid"></g>
                    <g id="memOverviewNodes"></g>
                    <g id="memEraNodes"></g>
                    <g id="memClusterNodes"></g>
                </g>
            </svg>

            <p class="mem-hint">
                <span><i class="mem-dot"></i>ドラッグで移動</span>
                <span><i class="mem-dot"></i>ホイール / ピンチで拡縮</span>
                <span><i class="mem-dot"></i>記憶玉に近づくと反応</span>
            </p>
        </div>
    @endif
</div>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.page.page-bubbles-full {
    width: 100vw;
    max-width: none;
    padding: 0;
    overflow: hidden;
}

.mem-universe {
    --era-a: rgba(68, 146, 255, 0.18);
    --era-b: rgba(132, 104, 255, 0.14);
    --era-c: rgba(255, 186, 124, 0.09);
    position: relative;
    width: 100vw;
    min-height: 100vh;
    background:
        radial-gradient(circle at 18% 18%, var(--era-a), transparent 34%),
        radial-gradient(circle at 82% 14%, var(--era-b), transparent 32%),
        radial-gradient(circle at 50% 84%, var(--era-c), transparent 38%),
        radial-gradient(ellipse at 50% 38%, rgba(15, 24, 64, 0.82), transparent 62%),
        linear-gradient(180deg, #071127 0%, #030814 55%, #01040c 100%);
    color: #d4eaff;
    overflow: hidden;
    font-family: "Hiragino Sans", "Yu Gothic", sans-serif;
    transition: background 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.star-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.mem-nav {
    position: absolute;
    inset: 0 0 auto 0;
    z-index: 20;
    padding: 22px 28px 0;
    min-height: 112px;
}

body.page-bubbles-full .app-auth-dock {
    display: none;
}

.mem-nav-left {
    position: absolute;
    left: 28px;
    top: 22px;
    transform: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
    text-align: left;
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
    align-items: flex-start;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.mem-action-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.mem-session-label {
    padding: 0 6px;
    color: rgba(211, 228, 255, 0.76);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-align: right;
    text-shadow: 0 0 16px rgba(44, 126, 255, 0.22);
}

.mem-count-orb {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    position: relative;
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

.mem-count-orb::before {
    content: '';
    position: absolute;
    top: 16%;
    left: 22%;
    width: 28%;
    height: 18%;
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

.mem-glass-pill::before {
    content: '';
    position: absolute;
    top: 0;
    left: 10%;
    right: 10%;
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
}

.mem-glass-pill--purple {
    background:
        linear-gradient(180deg, rgba(255,255,255,0.22), rgba(255,255,255,0.08)),
        linear-gradient(135deg, rgba(176, 118, 255, 0.4), rgba(105, 96, 255, 0.26) 58%, rgba(255,255,255,0.08));
    color: rgba(244,236,255,0.98);
}

.mem-glass-pill:hover {
    transform: translateY(-2px) scale(1.03);
}

.mem-glass-pill::-webkit-details-marker { display: none; }

.mem-chevron {
    width: 10px;
    height: 6px;
    color: rgba(180,220,255,0.85);
    transition: transform 0.2s;
    flex-shrink: 0;
}

details[open] .mem-chevron { transform: rotate(180deg); }

.mem-details { position: relative; }

.mem-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    z-index: 30;
    min-width: 220px;
    padding: 10px;
    border-radius: 18px;
    background:
        linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.06)),
        linear-gradient(145deg, rgba(10,22,62,0.9) 0%, rgba(6,12,36,0.94) 100%);
    border: 1px solid rgba(255,255,255,0.18);
    box-shadow:
        0 30px 60px rgba(0,0,0,0.75),
        inset 0 1px 0 rgba(255,255,255,0.22);
    backdrop-filter: blur(28px) saturate(1.5);
}

.mem-drop-item {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 10px;
    padding: 11px 14px;
    border-radius: 12px;
    color: rgba(200,228,255,0.92);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.16s, transform 0.14s;
}

.mem-drop-item:hover {
    background: rgba(93, 158, 255, 0.14);
    transform: translateX(3px);
}

.mem-drop-submit {
    border: 0;
    background: transparent;
    cursor: pointer;
    text-align: left;
}

.mem-drop-item-static {
    opacity: 0.72;
    cursor: default;
}

.mem-drop-icon {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    background: rgba(0,80,200,0.28);
    border: 1px solid rgba(0,150,255,0.22);
    font-size: 14px;
    flex-shrink: 0;
}

.mem-dropdown--filter {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 280px;
}

.mem-dropdown--inline {
    position: static;
    min-width: 0;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    backdrop-filter: none;
}

.mem-dropdown-divider {
    height: 1px;
    margin: 8px 4px 10px;
    background: linear-gradient(90deg, transparent, rgba(180, 216, 255, 0.24), transparent);
}

.mem-dropdown-label {
    margin: 0 4px 10px;
    color: rgba(156, 204, 255, 0.82);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
}

.mem-filter-chip {
    display: inline-flex;
    align-items: center;
    padding: 7px 16px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.24);
    background: rgba(0,16,60,0.34);
    color: rgba(220,238,255,0.92);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.mem-filter-chip.is-on {
    border-color: rgba(208,235,255,0.48);
    background: rgba(0,60,180,0.22);
    color: #fff;
}

.mem-stage {
    position: relative;
    width: 100%;
    min-height: 100vh;
}

.mem-hud {
    position: absolute;
    right: 22px;
    bottom: 28px;
    z-index: 14;
    width: min(284px, calc(100vw - 44px));
    padding: 16px 18px 15px;
    border-radius: 26px;
    border: 1px solid rgba(173, 214, 255, 0.12);
    background:
        linear-gradient(180deg, rgba(16, 24, 52, 0.28), rgba(5, 10, 26, 0.18)),
        radial-gradient(circle at 20% 0%, rgba(255,255,255,0.14), transparent 34%),
        radial-gradient(circle at 100% 100%, rgba(113, 173, 255, 0.10), transparent 40%);
    box-shadow:
        0 16px 30px rgba(0,0,0,0.14),
        inset 0 1px 0 rgba(255,255,255,0.08);
    backdrop-filter: blur(30px) saturate(1.05);
    transition: opacity 0.28s ease, transform 0.28s ease;
}

.mem-hud.is-dormant {
    opacity: 0;
    transform: translateY(12px);
    pointer-events: none;
}

.mem-hud-copy {
    display: grid;
    gap: 6px;
}

.mem-hud-kicker {
    color: rgba(122, 204, 255, 0.88);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.mem-hud-copy strong {
    color: rgba(242, 247, 255, 0.92);
    font-size: 20px;
    font-weight: 760;
    line-height: 1.34;
    letter-spacing: 0.01em;
}

.mem-hud-copy p {
    color: rgba(209, 224, 248, 0.64);
    font-size: 12px;
    line-height: 1.7;
}

.mem-hud-actions {
    margin-top: 14px;
    display: flex;
    gap: 10px;
}

.mem-hud-button {
    min-height: 42px;
    padding: 0 16px;
    border: 1px solid rgba(176, 220, 255, 0.28);
    border-radius: 999px;
    background:
        linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05)),
        rgba(10, 26, 76, 0.64);
    color: rgba(238, 246, 255, 0.96);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    cursor: pointer;
    text-decoration: none;
    box-shadow:
        0 14px 24px rgba(0,0,0,0.14),
        inset 0 1px 0 rgba(255,255,255,0.18);
}

.mem-hud-button-ghost {
    background: rgba(255,255,255,0.04);
}

.mem-detail[hidden] {
    display: none;
}

.mem-detail {
    position: absolute;
    right: 22px;
    bottom: 164px;
    z-index: 15;
    width: min(430px, calc(100vw - 44px));
}

.mem-grave-panel[hidden] {
    display: none;
}

.mem-grave-panel {
    position: absolute;
    inset: 0;
    z-index: 18;
}

.mem-grave-panel-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 6, 18, 0.46);
    backdrop-filter: blur(8px);
}

.mem-grave-panel-shell {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: min(420px, calc(100vw - 36px));
    padding: 22px;
    border-radius: 28px;
    border: 1px solid rgba(135, 201, 255, 0.18);
    background:
        linear-gradient(180deg, rgba(8, 14, 42, 0.94), rgba(3, 6, 20, 0.94)),
        radial-gradient(circle at 18% 12%, rgba(255,255,255,0.10), transparent 34%);
    box-shadow:
        0 36px 74px rgba(0,0,0,0.46),
        inset 0 1px 0 rgba(255,255,255,0.12);
    backdrop-filter: blur(18px);
}

.mem-grave-copy {
    margin-top: 16px;
    color: rgba(223, 234, 255, 0.88);
    font-size: 14px;
    line-height: 1.8;
}

.mem-grave-form {
    margin-top: 18px;
    display: grid;
    gap: 14px;
}

.mem-grave-field {
    display: grid;
    gap: 8px;
}

.mem-grave-field span {
    color: rgba(175, 205, 245, 0.76);
    font-size: 12px;
    letter-spacing: 0.08em;
}

.mem-grave-field input {
    width: 100%;
    min-height: 48px;
    padding: 0 16px;
    border-radius: 16px;
    border: 1px solid rgba(156, 193, 255, 0.14);
    background: rgba(255,255,255,0.07);
    color: rgba(247, 250, 255, 0.98);
}

.mem-grave-message {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.6;
}

.mem-grave-message--error {
    background: rgba(255, 116, 156, 0.12);
    color: rgba(255, 206, 220, 0.94);
    border: 1px solid rgba(255, 144, 182, 0.18);
}

.mem-grave-message--ok {
    background: rgba(118, 202, 255, 0.10);
    color: rgba(219, 241, 255, 0.94);
    border: 1px solid rgba(126, 198, 255, 0.18);
}

.mem-detail-shell {
    padding: 18px;
    border-radius: 28px;
    border: 1px solid rgba(135, 201, 255, 0.22);
    background:
        linear-gradient(180deg, rgba(8, 14, 42, 0.92), rgba(3, 6, 20, 0.92)),
        radial-gradient(circle at 18% 12%, rgba(255,255,255,0.12), transparent 34%);
    box-shadow:
        0 36px 74px rgba(0,0,0,0.46),
        inset 0 1px 0 rgba(255,255,255,0.12);
    backdrop-filter: blur(18px);
}

.mem-detail-head {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
}

.mem-detail-kicker {
    color: rgba(119, 198, 255, 0.82);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.mem-detail-head h2 {
    margin-top: 8px;
    color: rgba(245, 249, 255, 0.98);
    font-size: 28px;
    line-height: 1.2;
    font-weight: 900;
}

.mem-detail-close {
    min-width: 72px;
    min-height: 36px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.18);
    background: rgba(255,255,255,0.06);
    color: rgba(226, 239, 255, 0.86);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.mem-detail-meta {
    margin-top: 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.mem-detail-chip {
    padding: 7px 12px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.05);
    color: rgba(228, 238, 255, 0.88);
    font-size: 11px;
    font-weight: 700;
}

.mem-detail-visual {
    position: relative;
    margin-top: 18px;
    min-height: 180px;
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid rgba(172, 224, 255, 0.14);
    background:
        radial-gradient(circle at 30% 24%, rgba(255,255,255,0.18), transparent 20%),
        radial-gradient(circle at 72% 34%, rgba(255,255,255,0.09), transparent 18%),
        linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)),
        rgba(16, 22, 54, 0.72);
}

.mem-detail-visual-glow {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 28% 26%, rgba(255,255,255,0.28), transparent 16%),
        radial-gradient(circle at 50% 56%, rgba(114, 182, 255, 0.28), transparent 30%),
        radial-gradient(circle at 72% 32%, rgba(255, 196, 144, 0.14), transparent 18%);
    filter: blur(4px);
}

.mem-detail-visual-copy {
    position: absolute;
    left: 20px;
    right: 20px;
    bottom: 18px;
    display: grid;
    gap: 4px;
}

.mem-detail-visual-copy strong {
    color: rgba(248, 250, 255, 0.98);
    font-size: 20px;
    font-weight: 800;
}

.mem-detail-visual-copy small {
    color: rgba(209, 227, 255, 0.78);
    font-size: 12px;
}

.mem-detail-body {
    margin-top: 16px;
    color: rgba(229, 237, 255, 0.90);
    font-size: 14px;
    line-height: 1.9;
}

.mem-detail-comment {
    margin-top: 16px;
    padding: 14px 16px;
    border-radius: 18px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
}

.mem-detail-comment span {
    display: block;
    color: rgba(137, 211, 255, 0.82);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.mem-detail-comment p {
    margin-top: 8px;
    color: rgba(233, 241, 255, 0.92);
    font-size: 13px;
    line-height: 1.8;
}

.mem-detail-actions {
    margin-top: 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.mem-svg {
    display: block;
    width: 100%;
    height: 100vh;
    cursor: grab;
    touch-action: none;
}

.mem-svg.dragging { cursor: grabbing; }

.mem-hint {
    position: absolute;
    left: 50%;
    bottom: 16px;
    transform: translateX(-50%);
    display: flex;
    gap: 12px;
    z-index: 12;
    flex-wrap: wrap;
    justify-content: center;
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
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #5060ff);
    box-shadow: 0 0 10px rgba(0,200,255,0.60);
}

.mem-empty {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
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
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 28%, rgba(80,180,255,0.30), rgba(0,40,140,0.20) 60%, rgba(0,5,30,0.80));
    border: 1.5px solid rgba(0,200,255,0.40);
    box-shadow: 0 0 50px rgba(0,140,255,0.25);
    animation: breathe 4s ease-in-out infinite;
}

.mg-grid-line {
    stroke: rgba(92, 144, 255, 0.08);
    stroke-width: 1;
    stroke-dasharray: 6 16;
}

.mg-overview-node,
.mg-era-node,
.mg-cluster-node,
.mg-memory-node {
    opacity: 0;
    animation: mgReveal 0.88s cubic-bezier(0.22, 0.85, 0.32, 1) var(--delay, 0s) forwards;
}

.mg-overview-node {
    transition: opacity 0.45s ease;
}

.mg-era-node,
.mg-cluster-node {
    transition: opacity 0.45s ease, filter 0.45s ease;
}

.mg-era-node.is-muted,
.mg-cluster-node.is-muted,
.mg-overview-node.is-muted {
    opacity: 0.18;
    filter: saturate(0.64);
}

.mg-era-node.is-focused {
    filter: brightness(1.08) saturate(1.08);
}

.mg-era-anchor,
.mg-memory-anchor,
.mg-overview-anchor {
    cursor: pointer;
    text-decoration: none;
}

.mg-era-shell,
.mg-overview-shell,
.mg-cluster-shell {
    transform-box: fill-box;
    transform-origin: center;
}

.mg-era-body,
.mg-overview-body,
.mg-memory-core {
    transition: transform 0.28s ease, filter 0.28s ease, opacity 0.28s ease;
}

.mg-era-anchor:hover .mg-era-body,
.mg-overview-anchor:hover .mg-overview-body {
    transform: scale(1.03);
    filter: brightness(1.08);
}

.mg-era-shell-fill {
    fill: rgba(255,255,255,0.02);
}

.mg-era-shell-rim {
    fill: rgba(232, 243, 255, 0.06);
    opacity: 0.56;
}

.mg-era-title,
.mg-era-count,
.mg-era-count-unit,
.mg-era-caption,
.mg-overview-title,
.mg-overview-copy,
.mg-memory-label,
.mg-memory-meta,
.mg-cluster-label {
    text-anchor: middle;
    dominant-baseline: middle;
    paint-order: stroke;
    stroke: rgba(4, 10, 24, 0.74);
    stroke-width: 3px;
    stroke-linejoin: round;
    pointer-events: none;
}

.mg-era-title,
.mg-overview-title {
    fill: rgba(241, 246, 255, 0.94);
    font-weight: 760;
    letter-spacing: 0.16em;
    font-family: "Avenir Next", "SF Pro Display", "Hiragino Sans", sans-serif;
}

.mg-era-count {
    fill: rgba(242, 248, 255, 0.88);
    font-weight: 700;
    font-family: "Avenir Next", "SF Pro Display", sans-serif;
}

.mg-era-count-unit {
    fill: rgba(201, 218, 244, 0.64);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.18em;
    font-family: "Avenir Next", "SF Pro Display", sans-serif;
    stroke-width: 2px;
}

.mg-era-caption,
.mg-overview-copy {
    fill: rgba(214, 228, 250, 0.74);
    font-size: 12px;
    line-height: 1.6;
}

.mg-memory-label {
    fill: rgba(255,255,255,0.98);
    font-weight: 800;
    stroke-width: 2.4px;
}

.mg-memory-meta,
.mg-cluster-label {
    fill: rgba(220, 233, 255, 0.80);
    font-weight: 700;
    stroke-width: 2px;
}

.mg-memory-core.is-near {
    filter: brightness(1.18) saturate(1.22);
}

.mg-cluster-halo {
    fill: rgba(120, 176, 255, 0.05);
    opacity: 0.8;
}

.mg-memory-rim {
    fill: rgba(255,255,255,0.06);
    opacity: 0.56;
}

.mg-memory-core circle[data-hit="true"] {
    fill: rgba(255,255,255,0.001);
}

.mg-fo-wrap {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
}

.mg-fo-actions {
    display: flex;
    gap: 8px;
}

.mg-fo-button {
    min-height: 34px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid rgba(192, 226, 255, 0.28);
    background:
        linear-gradient(180deg, rgba(255,255,255,0.16), rgba(255,255,255,0.05)),
        rgba(10, 22, 62, 0.84);
    color: rgba(246, 249, 255, 0.96);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-decoration: none;
    box-shadow: 0 14px 30px rgba(0,0,0,0.18);
}

@keyframes mgReveal {
    0% { opacity: 0; filter: blur(12px) saturate(0.2); }
    70% { opacity: 0.92; }
    100% { opacity: 1; filter: blur(0) saturate(1); }
}

@keyframes breathe {
    0%, 100% { transform: scale(0.94); }
    50% { transform: scale(1.06); }
}

@media (max-width: 980px) {
    body.page-bubbles-full .app-auth-dock {
        top: 154px;
        right: 16px;
        left: auto;
    }

    .mem-nav {
        padding: 16px 16px 0;
        min-height: 154px;
    }

    .mem-nav-left {
        top: 16px;
        left: 16px;
        width: calc(100% - 32px);
        align-items: flex-start;
        text-align: left;
    }

    .mem-nav-right {
        top: 88px;
        left: 16px;
        right: 16px;
        justify-content: center;
        align-items: center;
    }

    .mem-action-stack {
        align-items: center;
    }

    .mem-session-label {
        text-align: center;
    }

    .mem-hud {
        right: 16px;
        width: auto;
        bottom: 74px;
    }

    .mem-detail {
        left: 16px;
        right: 16px;
        bottom: 184px;
        width: auto;
    }
}

@media (max-width: 640px) {
    body.page-bubbles-full .app-auth-dock {
        top: auto;
        bottom: 20px;
        left: 12px;
        right: 12px;
    }

    .mem-nav-title {
        font-size: clamp(22px, 8vw, 34px);
    }

    .mem-glass-pill {
        padding: 8px 14px;
        font-size: 12px;
    }

    .mem-detail-head h2 {
        font-size: 23px;
    }

    .mem-hint {
        width: calc(100vw - 24px);
    }
}
</style>

@if($bubbleMemories->isNotEmpty())
<script>
(function () {
"use strict";

const memories = @json($bubbleMemories);
const periods = @json($periods);
const periodCounts = @json($periodBubbleCounts);
const selectedPeriod = @json($selectedPeriod);
const allCount = @json($allCount);
const graveMode = @json($graveMode);
const shouldOpenGravePanel = @json((bool) $graveUnlockError || (bool) $graveUnlockSuccess);
const createUrl = @json(route('memories.create'));
const listUrl = @json(route('memories.index'));
const overviewUrl = @json(route('memories.bubbles'));
const NS = "http://www.w3.org/2000/svg";
const VP = { w: 1400, h: 900 };
const EASE = 0.12;
const OVERVIEW_SCALE = 1;
const ERA_SCALE = 2.35;
const MEMORY_SCALE = 4.8;

const ERA_ANCHORS = {
    "幼少期": { x: 220, y: 320, r: 138 },
    "小学生": { x: 220, y: 670, r: 146 },
    "中学生": { x: 510, y: 790, r: 148 },
    "高校生": { x: 850, y: 760, r: 152 },
    "大学生": { x: 1115, y: 620, r: 136 },
    "成人期": { x: 1185, y: 330, r: 140 },
    "不明": { x: 950, y: 150, r: 124 }
};

const ERA_PALETTES = {
    "幼少期": ["rgba(255,122,173,0.18)", "rgba(173,126,255,0.14)", "rgba(255,210,155,0.10)"],
    "小学生": ["rgba(255,166,94,0.20)", "rgba(139,203,255,0.14)", "rgba(255,220,173,0.10)"],
    "中学生": ["rgba(116,180,255,0.20)", "rgba(118,120,255,0.13)", "rgba(188,224,255,0.08)"],
    "高校生": ["rgba(148,113,255,0.20)", "rgba(114,182,255,0.14)", "rgba(255,200,130,0.08)"],
    "大学生": ["rgba(114,204,255,0.20)", "rgba(186,132,255,0.12)", "rgba(255,231,183,0.09)"],
    "成人期": ["rgba(138,118,255,0.18)", "rgba(255,176,116,0.13)", "rgba(163,220,255,0.08)"],
    "不明": ["rgba(255,133,168,0.20)", "rgba(116,204,255,0.10)", "rgba(255,214,170,0.08)"]
};

const CLUSTER_SLOT_OFFSETS = [
    { x: -0.26, y: -0.22 },
    { x: 0.22, y: -0.24 },
    { x: 0.30, y: 0.02 },
    { x: 0.08, y: 0.24 },
    { x: -0.24, y: 0.20 },
    { x: -0.08, y: -0.32 },
    { x: 0.22, y: 0.28 },
    { x: -0.34, y: -0.02 }
];

const universe = document.getElementById("memUniverse");
const svg = document.getElementById("memSvg");
const defs = document.getElementById("memDefs");
const viewport = document.getElementById("memViewport");
const parallaxBackG = document.getElementById("memParallaxBack");
const gridG = document.getElementById("memGrid");
const overviewG = document.getElementById("memOverviewNodes");
const eraG = document.getElementById("memEraNodes");
const clusterG = document.getElementById("memClusterNodes");
const stage = document.getElementById("memStage");
const hud = stage.querySelector(".mem-hud");
const modeKicker = stage.querySelector("[data-mode-kicker]");
const modeTitle = stage.querySelector("[data-mode-title]");
const modeBody = stage.querySelector("[data-mode-body]");
const backButton = stage.querySelector("[data-back-button]");
const overviewButton = stage.querySelector("[data-overview-button]");
const detail = stage.querySelector("[data-memory-detail]");
const detailClose = stage.querySelector("[data-detail-close]");
const detailBack = stage.querySelector("[data-detail-back]");
const detailLink = stage.querySelector("[data-detail-link]");
const gravePanel = stage.querySelector("[data-grave-panel]");
const graveCloseButtons = stage.querySelectorAll("[data-grave-close]");

const detailFields = {
    period: stage.querySelector("[data-detail-period]"),
    title: stage.querySelector("[data-detail-title]"),
    emotion: stage.querySelector("[data-detail-emotion]"),
    theme: stage.querySelector("[data-detail-theme]"),
    date: stage.querySelector("[data-detail-date]"),
    label: stage.querySelector("[data-detail-label]"),
    cluster: stage.querySelector("[data-detail-cluster]"),
    content: stage.querySelector("[data-detail-content]"),
    comment: stage.querySelector("[data-detail-comment]")
};

const state = {
    zoomLevel: selectedPeriod === "すべて" ? 0 : 1,
    selectedEra: selectedPeriod === "すべて" ? null : selectedPeriod,
    selectedMemory: null,
    camera: {
        x: 700,
        y: 450,
        scale: selectedPeriod === "すべて" ? OVERVIEW_SCALE : ERA_SCALE
    },
    targetCamera: {
        x: selectedPeriod === "すべて" ? 700 : (ERA_ANCHORS[selectedPeriod]?.x ?? 700),
        y: selectedPeriod === "すべて" ? 450 : (ERA_ANCHORS[selectedPeriod]?.y ?? 450),
        scale: selectedPeriod === "すべて" ? OVERVIEW_SCALE : ERA_SCALE
    },
    pointer: {
        x: 700,
        y: 450,
        active: false
    },
    drag: {
        active: false,
        pointerId: null,
        startX: 0,
        startY: 0,
        startCameraX: 0,
        startCameraY: 0
    },
    touch: {
        mode: null,
        pinchDistance: 0
    },
    memoryTransition: {
        active: false,
        targetId: null
    }
};

const runtime = {
    eras: [],
    memoryRefs: [],
    clusterRefs: [],
    overviewRefs: [],
    graveRef: null
};

function el(tag, attrs = {}) {
    const node = document.createElementNS(NS, tag);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
    return node;
}

function rgba(hex, alpha) {
    const raw = hex.length === 4
        ? hex.slice(1).split("").map((char) => char + char).join("")
        : hex.slice(1);
    const r = parseInt(raw.slice(0, 2), 16);
    const g = parseInt(raw.slice(2, 4), 16);
    const b = parseInt(raw.slice(4, 6), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

function seeded(seed) {
    let current = seed >>> 0;
    return function next() {
        current = (current * 1664525 + 1013904223) >>> 0;
        return current / 0xffffffff;
    };
}

function makeGradientSet(prefix, colors, shell = false) {
    const [c0, c1] = colors;
    const bodyId = `${prefix}-body`;
    const rimId = `${prefix}-rim`;
    const auraId = `${prefix}-aura`;

    const body = el("radialGradient", { id: bodyId, cx: "32%", cy: "26%", r: "76%" });
    body.append(
        el("stop", { offset: "0%", "stop-color": rgba("#ffffff", shell ? 0.26 : 0.44) }),
        el("stop", { offset: "18%", "stop-color": rgba(c0, shell ? 0.12 : 0.76) }),
        el("stop", { offset: "54%", "stop-color": rgba("#ffffff", shell ? 0.045 : 0.32) }),
        el("stop", { offset: "100%", "stop-color": rgba(c1, shell ? 0.05 : 0.94) })
    );

    const rim = el("linearGradient", { id: rimId, x1: "0%", y1: "0%", x2: "100%", y2: "100%" });
    rim.append(
        el("stop", { offset: "0%", "stop-color": rgba("#ffffff", 0.86) }),
        el("stop", { offset: "44%", "stop-color": rgba(c0, shell ? 0.46 : 0.74) }),
        el("stop", { offset: "100%", "stop-color": rgba(c1, 0.92) })
    );

    const aura = el("radialGradient", { id: auraId, cx: "50%", cy: "50%", r: "50%" });
    aura.append(
        el("stop", { offset: "0%", "stop-color": rgba(c0, 0) }),
        el("stop", { offset: "62%", "stop-color": rgba(c0, shell ? 0.08 : 0.18) }),
        el("stop", { offset: "100%", "stop-color": rgba(c1, shell ? 0.16 : 0.30) })
    );

    defs.append(body, rim, aura);
    return { bodyId, rimId, auraId };
}

function buildWorld() {
    const grouped = new Map();

    periods.forEach((period) => grouped.set(period, []));
    memories.forEach((memory) => {
        if (!grouped.has(memory.period)) {
            grouped.set(memory.period, []);
        }
        grouped.get(memory.period).push(memory);
    });

    runtime.eras = periods.map((period, eraIndex) => {
        const anchor = ERA_ANCHORS[period] ?? { x: 700, y: 450, r: 110 };
        const list = grouped.get(period) ?? [];
        const count = periodCounts[period] ?? list.length;
        const preview = list.slice(0, 4);
        const clusterMap = new Map();

        list.forEach((memory) => {
            const key = memory.cluster || memory.emotion;
            if (!clusterMap.has(key)) {
                clusterMap.set(key, []);
            }
            clusterMap.get(key).push(memory);
        });

        const clusters = Array.from(clusterMap.entries()).slice(0, CLUSTER_SLOT_OFFSETS.length).map(([key, clusterMemories], clusterIndex) => {
            const slot = CLUSTER_SLOT_OFFSETS[clusterIndex] ?? CLUSTER_SLOT_OFFSETS[clusterIndex % CLUSTER_SLOT_OFFSETS.length];
            const clusterSeed = seeded((eraIndex + 1) * 901 + clusterIndex * 37);
            const jitterX = (clusterSeed() - 0.5) * 12;
            const jitterY = (clusterSeed() - 0.5) * 10;
            const centerX = anchor.x + slot.x * anchor.r * 0.9 + jitterX;
            const centerY = anchor.y + slot.y * anchor.r * 0.9 + jitterY;

            const items = clusterMemories.map((memory, memoryIndex) => {
                const rand = seeded(memory.id * 761 + memoryIndex * 97);
                const angle = (Math.PI * 2 * memoryIndex) / Math.max(clusterMemories.length, 1) + rand() * 0.42;
                const orbit = 14 + Math.floor(memoryIndex / 3) * 16 + rand() * 5;
                const radius = Math.max(16, 20 + (memory.tags?.length ?? 0) * 1.4 + rand() * 7);

                return {
                    ...memory,
                    baseX: centerX + Math.cos(angle) * orbit,
                    baseY: centerY + Math.sin(angle) * orbit,
                    radius,
                    driftX: 6 + rand() * 9,
                    driftY: 5 + rand() * 8,
                    driftSpeed: 0.6 + rand() * 0.55,
                    driftPhase: rand() * Math.PI * 2
                };
            });

            return {
                key,
                centerX,
                centerY,
                items
            };
        });

        return {
            period,
            x: anchor.x,
            y: anchor.y,
            r: anchor.r,
            count,
            preview,
            clusters
        };
    });
}

function drawGrid() {
    for (let x = 40; x <= 1360; x += 140) {
        gridG.append(el("line", { x1: x, y1: 20, x2: x, y2: 880, class: "mg-grid-line" }));
    }

    for (let y = 40; y <= 860; y += 120) {
        gridG.append(el("line", { x1: 20, y1: y, x2: 1380, y2: y, class: "mg-grid-line" }));
    }
}

function drawParallaxBack() {
    for (let index = 0; index < 18; index += 1) {
        const rand = seeded((index + 1) * 1213);
        const circle = el("circle", {
            cx: (90 + rand() * 1220).toFixed(2),
            cy: (70 + rand() * 760).toFixed(2),
            r: (18 + rand() * 46).toFixed(2),
            fill: index % 3 === 0 ? "rgba(126, 168, 255, 0.08)" : (index % 3 === 1 ? "rgba(216, 166, 255, 0.06)" : "rgba(255, 204, 140, 0.05)"),
            filter: "url(#fAura)"
        });
        parallaxBackG.append(circle);
    }
}

function drawOverviewNodes() {
    const nodes = [
        {
            id: "cta",
            x: 700,
            y: 418,
            r: 108,
            title: "今日は\n何をする？"
        }
    ];

    nodes.forEach((node, index) => {
        const gradients = makeGradientSet(`overview-${node.id}`, ["#dfeeff", node.id === "cta" ? "#8fb9ff" : "#4a8dff"], true);
        const wrap = el("g", {
            class: "mg-overview-node",
            style: `--delay:${(index * 0.08).toFixed(2)}s`
        });
        const link = el("g", { class: "mg-overview-anchor" });
        const body = el("g", { class: "mg-overview-body" });

        body.append(
            el("circle", { cx: node.x, cy: node.y, r: node.r + 26, fill: `url(#${gradients.auraId})`, filter: "url(#fAura)" }),
            el("circle", { cx: node.x, cy: node.y, r: node.r + 8, fill: "rgba(228,241,255,0.08)", filter: "url(#fAura)" }),
            el("circle", { cx: node.x, cy: node.y, r: node.r, class: "mg-era-shell-fill", fill: `url(#${gradients.bodyId})`, opacity: "0.94" }),
            el("circle", { cx: node.x, cy: node.y, r: node.r - 8, class: "mg-era-shell-rim", filter: "url(#fSpec)" }),
            el("ellipse", {
                cx: node.x,
                cy: (node.y + node.r * 0.38).toFixed(2),
                rx: (node.r * 0.54).toFixed(2),
                ry: (node.r * 0.14).toFixed(2),
                fill: "rgba(255, 206, 176, 0.09)",
                filter: "url(#fSpec)"
            }),
            el("ellipse", {
                cx: (node.x - node.r * 0.22).toFixed(2),
                cy: (node.y - node.r * 0.28).toFixed(2),
                rx: (node.r * 0.24).toFixed(2),
                ry: (node.r * 0.11).toFixed(2),
                fill: "rgba(255,255,255,0.18)",
                transform: `rotate(-20 ${node.x - node.r * 0.22} ${node.y - node.r * 0.28})`
            }),
            el("circle", {
                cx: (node.x - node.r * 0.28).toFixed(2),
                cy: (node.y - node.r * 0.3).toFixed(2),
                r: Math.max(10, node.r * 0.1).toFixed(2),
                fill: "rgba(255,255,255,0.34)",
                filter: "url(#fSpec)"
            })
        );

        const title = el("text", {
            x: node.x,
            y: node.id === "cta" ? node.y + 4 : node.y - 10,
            class: "mg-overview-title",
            "font-size": node.id === "cta" ? "34" : "68"
        });
        title.textContent = node.title;
        body.append(title);

        link.append(body);
        wrap.append(link);

        if (node.id === "cta") {
            wrap.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                openActionMenu();
            });
        }

        overviewG.append(wrap);
        runtime.overviewRefs.push(wrap);
    });
}

function drawGraveModeBubble() {
    if (!graveMode) {
        return;
    }

    const gradients = makeGradientSet(
        "grave-mode",
        graveMode.locked ? ["#efe4ff", "#8d6bff"] : ["#ffe8d2", "#8f7cff"],
        true
    );
    const wrap = el("g", {
        class: "mg-overview-node",
        style: "--delay:0.12s"
    });
    const anchor = el("g", { class: "mg-overview-anchor" });
    const body = el("g", { class: "mg-overview-body" });

    body.append(
        el("circle", { cx: graveMode.x, cy: graveMode.y, r: graveMode.r + 24, fill: `url(#${gradients.auraId})`, filter: "url(#fAura)" }),
        el("circle", { cx: graveMode.x, cy: graveMode.y, r: graveMode.r + 6, fill: "rgba(233,241,255,0.08)", filter: "url(#fAura)" }),
        el("circle", { cx: graveMode.x, cy: graveMode.y, r: graveMode.r, class: "mg-era-shell-fill", fill: `url(#${gradients.bodyId})`, opacity: "0.92" }),
        el("circle", { cx: graveMode.x, cy: graveMode.y, r: graveMode.r - 8, class: "mg-era-shell-rim", filter: "url(#fSpec)" }),
        el("ellipse", {
            cx: (graveMode.x - graveMode.r * 0.22).toFixed(2),
            cy: (graveMode.y - graveMode.r * 0.28).toFixed(2),
            rx: (graveMode.r * 0.22).toFixed(2),
            ry: (graveMode.r * 0.1).toFixed(2),
            fill: "rgba(255,255,255,0.18)",
            transform: `rotate(-20 ${graveMode.x - graveMode.r * 0.22} ${graveMode.y - graveMode.r * 0.28})`
        })
    );

    const title = el("text", {
        x: graveMode.x,
        y: graveMode.y - 8,
        class: "mg-overview-title",
        "font-size": "28"
    });
    title.textContent = graveMode.label;
    body.append(title);

    const copy = el("text", {
        x: graveMode.x,
        y: graveMode.y + graveMode.r * 0.44,
        class: "mg-overview-copy"
    });
    copy.textContent = graveMode.locked ? "鍵付き / 本人だけが見られる" : "解錠済み / 墓場までの記憶";
    body.append(copy);

    anchor.append(body);
    wrap.append(anchor);
    wrap.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        openGravePanel();
    });

    overviewG.append(wrap);
    runtime.overviewRefs.push(wrap);
    runtime.graveRef = wrap;
}

function drawEraNodes() {
    runtime.eras.forEach((era, eraIndex) => {
        const gradients = makeGradientSet(`era-${era.period}-${eraIndex}`, memories.find((memory) => memory.period === era.period)?.periodColors ?? ["#dce9ff", "#63a6ff"], true);
        const wrap = el("g", {
            class: "mg-era-node",
            "data-era": era.period,
            style: `--delay:${(eraIndex * 0.05).toFixed(2)}s`
        });
        const anchor = el("a", {
            class: "mg-era-anchor",
            "data-era-anchor": era.period
        });
        const body = el("g", { class: "mg-era-body" });
        const clipId = `era-clip-${eraIndex}`;
        const clipPath = el("clipPath", { id: clipId });
        clipPath.append(el("circle", { cx: era.x, cy: era.y, r: era.r - 14 }));
        defs.append(clipPath);

        body.append(
            el("circle", { cx: era.x, cy: era.y, r: era.r + 34, fill: `url(#${gradients.auraId})`, filter: "url(#fAura)", opacity: "0.94" }),
            el("circle", { cx: era.x, cy: era.y, r: era.r + 14, fill: "rgba(218,235,255,0.08)", filter: "url(#fAura)", opacity: "0.76" }),
            el("circle", { cx: era.x, cy: era.y, r: era.r, class: "mg-era-shell-fill", fill: `url(#${gradients.bodyId})`, opacity: "0.92" }),
            el("circle", { cx: era.x, cy: era.y, r: era.r - 10, class: "mg-era-shell-rim", filter: "url(#fSpec)" }),
            el("ellipse", {
                cx: era.x,
                cy: (era.y + era.r * 0.42).toFixed(2),
                rx: (era.r * 0.56).toFixed(2),
                ry: (era.r * 0.14).toFixed(2),
                fill: "rgba(255, 203, 160, 0.08)",
                filter: "url(#fSpec)"
            }),
            el("ellipse", {
                cx: (era.x + era.r * 0.28).toFixed(2),
                cy: (era.y - era.r * 0.24).toFixed(2),
                rx: (era.r * 0.18).toFixed(2),
                ry: (era.r * 0.09).toFixed(2),
                fill: "rgba(110, 208, 255, 0.10)",
                filter: "url(#fSpec)",
                transform: `rotate(18 ${era.x + era.r * 0.28} ${era.y - era.r * 0.24})`
            })
        );

        era.preview.forEach((memory, previewIndex) => {
            const previewGradients = makeGradientSet(`era-preview-${memory.id}`, memory.colors, false);
            const offsetAngle = ((Math.PI * 2) / Math.max(era.preview.length, 1)) * previewIndex - Math.PI / 2;
            const previewX = era.x + Math.cos(offsetAngle) * era.r * 0.32;
            const previewY = era.y + Math.sin(offsetAngle) * era.r * 0.32;
            const previewR = Math.max(18, era.r * 0.18 - previewIndex * 2);
            body.append(
                el("circle", { cx: previewX, cy: previewY, r: previewR + 10, fill: `url(#${previewGradients.auraId})`, filter: "url(#fAura)", opacity: "0.55" }),
                el("circle", { cx: previewX, cy: previewY, r: previewR, fill: `url(#${previewGradients.bodyId})`, opacity: "0.92" }),
                el("circle", { cx: previewX, cy: previewY, r: previewR - 2.2, class: "mg-memory-rim", filter: "url(#fSpec)" }),
                el("circle", { cx: previewX - previewR * 0.28, cy: previewY - previewR * 0.30, r: Math.max(3, previewR * 0.16), fill: "rgba(255,255,255,0.82)", filter: "url(#fSpec)" })
            );
        });

        const title = el("text", {
            x: era.x,
            y: era.y - era.r - 18,
            class: "mg-era-title",
            "font-size": Math.max(18, era.r * 0.14)
        });
        title.textContent = era.period;
        body.append(title);

        const count = el("text", {
            x: era.x,
            y: era.y + era.r + 18,
            class: "mg-era-count",
            "font-size": Math.max(18, era.r * 0.20)
        });
        count.textContent = String(era.count);
        body.append(count);

        const countUnit = el("text", {
            x: era.x,
            y: era.y + era.r + 38,
            class: "mg-era-count-unit"
        });
        countUnit.textContent = "MEMORY ORBS";
        body.append(countUnit);

        const caption = el("text", {
            x: era.x,
            y: era.y + 4,
            class: "mg-era-caption"
        });
        caption.textContent = era.count > 0 ? "クリックして潜る" : "まだ記憶はありません";
        body.append(caption);

        anchor.append(body);
        wrap.append(anchor);
        eraG.append(wrap);

        wrap.addEventListener("click", (event) => {
            event.preventDefault();
            zoomToEra(era.period);
        });

        era.wrap = wrap;
        era.body = body;
        era.clipId = clipId;
    });
}

function drawClusterNodes() {
    runtime.eras.forEach((era) => {
        era.clusters.forEach((cluster, clusterIndex) => {
            const wrap = el("g", {
                class: "mg-cluster-node",
                "data-era-cluster": era.period,
                style: `--delay:${(clusterIndex * 0.05).toFixed(2)}s`
            });
            const clipped = el("g", {
                "clip-path": `url(#${era.clipId})`
            });

            clipped.append(
                el("circle", {
                    cx: cluster.centerX,
                    cy: cluster.centerY,
                    r: Math.max(54, cluster.items.length * 15),
                    class: "mg-cluster-halo",
                    filter: "url(#fAura)"
                })
            );

            const clusterLabel = el("text", {
                x: cluster.centerX,
                y: cluster.centerY - Math.max(56, cluster.items.length * 14),
                class: "mg-cluster-label",
                "font-size": "13"
            });
            clusterLabel.textContent = cluster.key;
                wrap.append(clusterLabel);

            cluster.items.forEach((memory, memoryIndex) => {
                const gradients = makeGradientSet(`memory-${memory.id}`, memory.colors, false);
                const node = el("g", {
                    class: "mg-memory-node",
                    "data-memory-id": String(memory.id),
                    "data-era-memory": era.period
                });
                const anchor = el("a", {
                    class: "mg-memory-anchor",
                    "data-memory-anchor": String(memory.id)
                });
                const body = el("g", { class: "mg-memory-core" });

                body.append(
                    el("circle", { cx: memory.baseX, cy: memory.baseY, r: memory.radius + 13, fill: `url(#${gradients.auraId})`, filter: "url(#fAura)", opacity: "0.78" }),
                    el("circle", { cx: memory.baseX, cy: memory.baseY, r: memory.radius, fill: `url(#${gradients.bodyId})`, filter: "url(#fShadow)", opacity: "0.95" }),
                    el("circle", { cx: memory.baseX, cy: memory.baseY, r: memory.radius - 2.2, class: "mg-memory-rim", filter: "url(#fSpec)" }),
                    el("ellipse", {
                        cx: (memory.baseX - memory.radius * 0.24).toFixed(2),
                        cy: (memory.baseY - memory.radius * 0.24).toFixed(2),
                        rx: Math.max(6, memory.radius * 0.26).toFixed(2),
                        ry: Math.max(3, memory.radius * 0.12).toFixed(2),
                        fill: "rgba(255,255,255,0.32)",
                        transform: `rotate(-20 ${memory.baseX - memory.radius * 0.24} ${memory.baseY - memory.radius * 0.24})`
                    }),
                    el("circle", {
                        cx: (memory.baseX - memory.radius * 0.28).toFixed(2),
                        cy: (memory.baseY - memory.radius * 0.30).toFixed(2),
                        r: Math.max(3, memory.radius * 0.16).toFixed(2),
                        fill: "rgba(255,255,255,0.88)",
                        filter: "url(#fSpec)"
                    }),
                    el("circle", { cx: memory.baseX, cy: memory.baseY, r: memory.radius + 16, "data-hit": "true" })
                );

                const label = el("text", {
                    x: memory.baseX,
                    y: memory.baseY - 2,
                    class: "mg-memory-label",
                    "font-size": Math.max(10, memory.radius * 0.3)
                });
                label.textContent = memory.label;
                body.append(label);

                const meta = el("text", {
                    x: memory.baseX,
                    y: memory.baseY + memory.radius * 0.28,
                    class: "mg-memory-meta",
                    "font-size": Math.max(8, memory.radius * 0.17)
                });
                meta.textContent = memory.emotion;
                body.append(meta);

                anchor.append(body);
                node.append(anchor);
                clipped.append(node);

                node.addEventListener("click", (event) => {
                    event.preventDefault();
                    zoomToMemory(memory.id);
                });

                runtime.memoryRefs.push({
                    id: memory.id,
                    era: era.period,
                    memory,
                    node,
                    body,
                    label,
                    meta
                });
            });

            wrap.append(clipped);
            clusterG.append(wrap);
            runtime.clusterRefs.push({ era: era.period, wrap, cluster });
        });
    });
}

function setUniversePalette(period) {
    const palette = period ? (ERA_PALETTES[period] ?? ERA_PALETTES["高校生"]) : ["rgba(68, 146, 255, 0.18)", "rgba(132, 104, 255, 0.14)", "rgba(255, 186, 124, 0.09)"];
    universe.style.setProperty("--era-a", palette[0]);
    universe.style.setProperty("--era-b", palette[1]);
    universe.style.setProperty("--era-c", palette[2]);
}

function updateHud() {
    if (state.zoomLevel === 0) {
        hud.classList.remove("is-dormant");
        modeKicker.textContent = "LEVEL 0";
        modeTitle.textContent = "人生全体を俯瞰しています";
        modeBody.textContent = "気になる年代のシャボン玉をクリックして、その中へ潜ってください。";
        backButton.hidden = true;
        overviewButton.hidden = true;
        detail.hidden = true;
        return;
    }

    if (state.zoomLevel === 1 && state.selectedEra) {
        hud.classList.remove("is-dormant");
        const currentEra = runtime.eras.find((era) => era.period === state.selectedEra);
        const clusterCount = currentEra?.clusters.length ?? 0;
        modeKicker.textContent = "LEVEL 1";
        modeTitle.textContent = `${state.selectedEra}の記憶群を漂っています`;
        modeBody.textContent = `${clusterCount}つの気配のまとまりが見えます。光る記憶玉に近づいてください。`;
        backButton.hidden = false;
        overviewButton.hidden = false;
        detail.hidden = true;
        return;
    }

    const memory = getSelectedMemory();
    if (!memory) {
        return;
    }

    hud.classList.toggle("is-dormant", state.memoryTransition.active);
    modeKicker.textContent = "LEVEL 2";
    modeTitle.textContent = `${memory.period}の記憶へ入っています`;
    modeBody.textContent = "他の記憶は静かに退き、選んだ記憶だけが前景に残っています。";
    backButton.hidden = false;
    overviewButton.hidden = false;
}

function updateDetail(memory) {
    if (!memory) {
        detail.hidden = true;
        return;
    }

    detail.hidden = false;
    detailFields.period.textContent = memory.period;
    detailFields.title.textContent = memory.theme;
    detailFields.emotion.textContent = memory.emotion;
    detailFields.theme.textContent = memory.cluster;
    detailFields.date.textContent = memory.createdAt;
    detailFields.label.textContent = memory.label;
    detailFields.cluster.textContent = `記憶群: ${memory.cluster}`;
    detailFields.content.textContent = memory.content;
    detailFields.comment.textContent = memory.comment;
    detailLink.href = memory.url;
}

function getSelectedMemory() {
    return memories.find((memory) => memory.id === state.selectedMemory) ?? null;
}

function updateEraVisibility() {
    runtime.eras.forEach((era) => {
        if (!era.wrap) {
            return;
        }

        const isFocused = state.selectedEra === era.period;
        era.wrap.classList.toggle("is-focused", isFocused);
        era.wrap.classList.toggle("is-muted", Boolean(state.selectedEra) && !isFocused);
    });

    runtime.clusterRefs.forEach(({ era, wrap }) => {
        const visible = state.selectedEra === era;
        const clusterOpacity = state.zoomLevel === 2
            ? (state.memoryTransition.active ? "0" : "0.18")
            : "1";
        wrap.style.opacity = visible ? clusterOpacity : "0";
        wrap.style.pointerEvents = visible ? "auto" : "none";
        wrap.classList.toggle("is-muted", state.zoomLevel === 2 && visible);
    });

    runtime.memoryRefs.forEach((ref) => {
        const visible = state.selectedEra === ref.era;
        const keepOnlySelected = state.zoomLevel === 2 && state.memoryTransition.active;
        const nodeVisible = visible && (!keepOnlySelected || state.selectedMemory === ref.id);
        ref.node.style.opacity = nodeVisible ? "1" : "0";
        ref.node.style.pointerEvents = nodeVisible ? "auto" : "none";
        ref.body.style.opacity = state.zoomLevel === 2
            ? (state.selectedMemory !== ref.id ? (state.memoryTransition.active ? "0" : "0.42") : "1")
            : "1";
    });

    runtime.overviewRefs.forEach((wrap) => {
        wrap.style.opacity = state.zoomLevel === 0 ? "1" : "0.14";
        wrap.style.pointerEvents = state.zoomLevel === 0 ? "auto" : "none";
    });
}

function zoomToOverview() {
    state.zoomLevel = 0;
    state.selectedEra = null;
    state.selectedMemory = null;
    state.memoryTransition.active = false;
    state.memoryTransition.targetId = null;
    state.targetCamera = { x: 700, y: 450, scale: OVERVIEW_SCALE };
    setUniversePalette(null);
    updateEraVisibility();
    updateHud();
}

function zoomToEra(period) {
    const era = runtime.eras.find((entry) => entry.period === period);
    if (!era) {
        return;
    }

    state.zoomLevel = 1;
    state.selectedEra = period;
    state.selectedMemory = null;
    state.memoryTransition.active = false;
    state.memoryTransition.targetId = null;
    state.targetCamera = { x: era.x, y: era.y, scale: ERA_SCALE };
    setUniversePalette(period);
    updateEraVisibility();
    updateHud();
}

function zoomToMemory(memoryId) {
    const entry = runtime.memoryRefs.find((ref) => ref.id === memoryId);
    if (!entry) {
        return;
    }

    state.zoomLevel = 2;
    state.selectedEra = entry.era;
    state.selectedMemory = memoryId;
    state.memoryTransition.active = true;
    state.memoryTransition.targetId = memoryId;
    const time = performance.now() * 0.001;
    const worldX = entry.memory.baseX + Math.cos(time * entry.memory.driftSpeed + entry.memory.driftPhase) * entry.memory.driftX;
    const worldY = entry.memory.baseY + Math.sin(time * entry.memory.driftSpeed * 1.06 + entry.memory.driftPhase) * entry.memory.driftY;
    state.targetCamera = { x: worldX, y: worldY, scale: MEMORY_SCALE };
    setUniversePalette(entry.era);
    updateEraVisibility();
    updateHud();
    updateDetail(null);
}

function zoomBack() {
    if (state.zoomLevel === 2 && state.selectedEra) {
        zoomToEra(state.selectedEra);
        return;
    }

    zoomToOverview();
}

function svgPoint(clientX, clientY) {
    const rect = svg.getBoundingClientRect();
    return {
        x: ((clientX - rect.left) / rect.width) * VP.w,
        y: ((clientY - rect.top) / rect.height) * VP.h
    };
}

function screenToWorld(point) {
    return {
        x: (point.x - VP.w / 2) / state.camera.scale + state.camera.x,
        y: (point.y - VP.h / 2) / state.camera.scale + state.camera.y
    };
}

function setCameraTargetFromZoom(nextScale, point) {
    const clamped = Math.min(MEMORY_SCALE + 0.8, Math.max(0.8, nextScale));
    const world = screenToWorld(point);
    state.targetCamera.scale = clamped;
    state.targetCamera.x = world.x - (point.x - VP.w / 2) / clamped;
    state.targetCamera.y = world.y - (point.y - VP.h / 2) / clamped;
}

function applyCamera() {
    const tx = VP.w / 2 - state.camera.x * state.camera.scale;
    const ty = VP.h / 2 - state.camera.y * state.camera.scale;
    viewport.setAttribute("transform", `matrix(${state.camera.scale} 0 0 ${state.camera.scale} ${tx} ${ty})`);
}

function updatePointerEffects(time) {
    const pointerWorld = state.pointer.active ? screenToWorld(state.pointer) : null;

    runtime.memoryRefs.forEach((ref) => {
        if (state.selectedEra !== ref.era) {
            return;
        }

        const driftX = Math.cos(time * ref.memory.driftSpeed + ref.memory.driftPhase) * ref.memory.driftX;
        const driftY = Math.sin(time * ref.memory.driftSpeed * 1.08 + ref.memory.driftPhase) * ref.memory.driftY;
        const x = ref.memory.baseX + driftX;
        const y = ref.memory.baseY + driftY;

        let scale = 1;
        let glow = false;

        if (pointerWorld && !state.memoryTransition.active) {
            const dx = pointerWorld.x - x;
            const dy = pointerWorld.y - y;
            const distance = Math.hypot(dx, dy);
            if (distance < ref.memory.radius * 1.6) {
                const ratio = 1 - distance / (ref.memory.radius * 1.6);
                scale = 1 + ratio * 0.14;
                glow = true;
            }
        }

        if (state.zoomLevel === 2 && state.selectedMemory !== ref.id) {
            scale *= 0.92;
        }

        ref.body.setAttribute("transform", `translate(${x - ref.memory.baseX} ${y - ref.memory.baseY}) scale(${scale.toFixed(3)} ${scale.toFixed(3)} ${ref.memory.baseX} ${ref.memory.baseY})`);
        ref.body.classList.toggle("is-near", glow || state.selectedMemory === ref.id);
    });
}

function tick(timeMs) {
    const time = timeMs * 0.001;
    state.camera.x += (state.targetCamera.x - state.camera.x) * EASE;
    state.camera.y += (state.targetCamera.y - state.camera.y) * EASE;
    state.camera.scale += (state.targetCamera.scale - state.camera.scale) * EASE;

    if (state.memoryTransition.active) {
        const settledX = Math.abs(state.targetCamera.x - state.camera.x) < 1.4;
        const settledY = Math.abs(state.targetCamera.y - state.camera.y) < 1.4;
        const settledScale = Math.abs(state.targetCamera.scale - state.camera.scale) < 0.02;

        if (settledX && settledY && settledScale) {
            state.memoryTransition.active = false;
            const memory = getSelectedMemory();
            updateEraVisibility();
            updateHud();
            updateDetail(memory);
        }
    }

    applyCamera();
    updatePointerEffects(time);
    requestAnimationFrame(tick);
}

svg.addEventListener("wheel", (event) => {
    event.preventDefault();
    const point = svgPoint(event.clientX, event.clientY);
    const factor = event.deltaY < 0 ? 1.12 : 0.9;
    setCameraTargetFromZoom(state.targetCamera.scale * factor, point);
}, { passive: false });

svg.addEventListener("pointerdown", (event) => {
    if (event.target.closest(".mg-era-anchor") || event.target.closest(".mg-memory-anchor") || event.target.closest(".mg-fo-button")) {
        return;
    }

    const point = svgPoint(event.clientX, event.clientY);
    state.drag.active = true;
    state.drag.pointerId = event.pointerId;
    state.drag.startX = point.x;
    state.drag.startY = point.y;
    state.drag.startCameraX = state.targetCamera.x;
    state.drag.startCameraY = state.targetCamera.y;
    svg.classList.add("dragging");
});

svg.addEventListener("pointermove", (event) => {
    const point = svgPoint(event.clientX, event.clientY);
    state.pointer.x = point.x;
    state.pointer.y = point.y;
    state.pointer.active = true;

    if (!state.drag.active || state.drag.pointerId !== event.pointerId) {
        return;
    }

    const dx = (point.x - state.drag.startX) / state.targetCamera.scale;
    const dy = (point.y - state.drag.startY) / state.targetCamera.scale;
    state.targetCamera.x = state.drag.startCameraX - dx;
    state.targetCamera.y = state.drag.startCameraY - dy;
});

function endPointerDrag() {
    state.drag.active = false;
    state.drag.pointerId = null;
    svg.classList.remove("dragging");
}

svg.addEventListener("pointerup", endPointerDrag);
svg.addEventListener("pointerleave", () => {
    state.pointer.active = false;
    endPointerDrag();
});

svg.addEventListener("touchstart", (event) => {
    if (event.touches.length === 2) {
        const a = svgPoint(event.touches[0].clientX, event.touches[0].clientY);
        const b = svgPoint(event.touches[1].clientX, event.touches[1].clientY);
        state.touch.mode = "pinch";
        state.touch.pinchDistance = Math.hypot(a.x - b.x, a.y - b.y);
        return;
    }

    if (event.touches.length === 1 && !event.target.closest(".mg-era-anchor") && !event.target.closest(".mg-memory-anchor")) {
        const point = svgPoint(event.touches[0].clientX, event.touches[0].clientY);
        state.touch.mode = "drag";
        state.drag.active = true;
        state.drag.startX = point.x;
        state.drag.startY = point.y;
        state.drag.startCameraX = state.targetCamera.x;
        state.drag.startCameraY = state.targetCamera.y;
    }
}, { passive: true });

svg.addEventListener("touchmove", (event) => {
    if (state.touch.mode === "pinch" && event.touches.length === 2) {
        const a = svgPoint(event.touches[0].clientX, event.touches[0].clientY);
        const b = svgPoint(event.touches[1].clientX, event.touches[1].clientY);
        const distance = Math.hypot(a.x - b.x, a.y - b.y);
        const center = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        if (state.touch.pinchDistance > 0) {
            const nextScale = state.targetCamera.scale * (distance / state.touch.pinchDistance);
            setCameraTargetFromZoom(nextScale, center);
        }
        state.touch.pinchDistance = distance;
        return;
    }

    if (state.touch.mode === "drag" && event.touches.length === 1) {
        const point = svgPoint(event.touches[0].clientX, event.touches[0].clientY);
        const dx = (point.x - state.drag.startX) / state.targetCamera.scale;
        const dy = (point.y - state.drag.startY) / state.targetCamera.scale;
        state.targetCamera.x = state.drag.startCameraX - dx;
        state.targetCamera.y = state.drag.startCameraY - dy;
    }
}, { passive: true });

svg.addEventListener("touchend", () => {
    state.touch.mode = null;
    state.touch.pinchDistance = 0;
    endPointerDrag();
});

backButton.addEventListener("click", zoomBack);
overviewButton.addEventListener("click", zoomToOverview);
detailClose.addEventListener("click", zoomBack);
detailBack.addEventListener("click", zoomBack);

function openActionMenu() {
    const details = document.getElementById("detAction");
    if (!details) {
        return;
    }

    details.setAttribute("open", "open");
}

function openGravePanel() {
    if (!gravePanel) {
        return;
    }

    gravePanel.hidden = false;
}

function closeGravePanel() {
    if (!gravePanel) {
        return;
    }

    gravePanel.hidden = true;
}

graveCloseButtons.forEach((button) => {
    button.addEventListener("click", closeGravePanel);
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeGravePanel();
        zoomBack();
    }
});

["detAction"].forEach((id) => {
    const details = document.getElementById(id);
    if (!details) {
        return;
    }

    details.addEventListener("toggle", () => {
        if (!details.open) {
            return;
        }

        ["detAction"].forEach((otherId) => {
            const other = document.getElementById(otherId);
            if (other && other !== details) {
                other.removeAttribute("open");
            }
        });
    });
});

document.addEventListener("click", (event) => {
    ["detAction"].forEach((id) => {
        const details = document.getElementById(id);
        if (details && !details.contains(event.target)) {
            details.removeAttribute("open");
        }
    });
});

(function initStars() {
    const canvas = document.getElementById("starCanvas");
    if (!canvas) {
        return;
    }

    const context = canvas.getContext("2d");
    const stars = Array.from({ length: 180 }, (_, index) => {
        const rand = seeded((index + 1) * 1889);
        return {
            x: rand(),
            y: rand(),
            radius: 0.4 + rand() * 1.8,
            alpha: 0.14 + rand() * 0.64,
            depth: 0.16 + rand() * 1.2,
            speed: 0.2 + rand() * 0.8,
            phase: rand() * Math.PI * 2
        };
    });

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    function draw(time) {
        context.clearRect(0, 0, canvas.width, canvas.height);
        const driftX = (state.camera.x - 700) * 0.018;
        const driftY = (state.camera.y - 450) * 0.018;

        stars.forEach((star) => {
            const px = star.x * canvas.width - driftX * star.depth;
            const py = star.y * canvas.height - driftY * star.depth;
            const pulse = 0.65 + Math.sin(time * star.speed + star.phase) * 0.28;
            context.beginPath();
            context.fillStyle = `rgba(223, 239, 255, ${star.alpha * pulse})`;
            context.arc(px, py, star.radius * pulse, 0, Math.PI * 2);
            context.fill();
        });

        requestAnimationFrame(draw);
    }

    resize();
    window.addEventListener("resize", resize);
    requestAnimationFrame(draw);
})();

buildWorld();
drawParallaxBack();
drawGrid();
drawOverviewNodes();
drawGraveModeBubble();
drawEraNodes();
drawClusterNodes();

if (selectedPeriod !== "すべて") {
    setUniversePalette(selectedPeriod);
    updateEraVisibility();
    updateHud();
} else {
    zoomToOverview();
}

if (shouldOpenGravePanel) {
    openGravePanel();
}

requestAnimationFrame(tick);
})();
</script>
@endif
@endsection
