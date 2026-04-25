<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '分身AI MVP')</title>
    <style>
        :root {
            --ink: #1f2430;
            --subtle: #5b6475;
            --line: rgba(31, 36, 48, 0.1);
            --paper: rgba(255, 255, 255, 0.82);
            --accent: #f06f5a;
            --positive: #ffe2c8;
            --neutral: #dce9ff;
            --negative: #eadfff;
            --danger: #a93535;
            --shadow: 0 24px 60px rgba(37, 32, 52, 0.12);
        }

        * { box-sizing: border-box; }

        html {
            min-height: 100%;
            background:
                radial-gradient(circle at 12% 12%, rgba(86, 132, 255, 0.2), transparent 22%),
                radial-gradient(circle at 88% 10%, rgba(126, 209, 255, 0.16), transparent 20%),
                radial-gradient(circle at 70% 100%, rgba(88, 108, 255, 0.14), transparent 24%),
                linear-gradient(160deg, #02040b 0%, #050916 48%, #0a1124 100%);
            background-color: #02040b;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 12%, rgba(86, 132, 255, 0.2), transparent 22%),
                radial-gradient(circle at 88% 10%, rgba(126, 209, 255, 0.16), transparent 20%),
                radial-gradient(circle at 70% 100%, rgba(88, 108, 255, 0.14), transparent 24%),
                linear-gradient(160deg, #02040b 0%, #050916 48%, #0a1124 100%);
            background-color: #02040b;
            background-attachment: fixed;
            font-family: "Avenir Next", "Hiragino Sans", "Yu Gothic", sans-serif;
        }

        a { color: inherit; text-decoration: none; }
        button, input, textarea { font: inherit; }

        .page {
            width: min(1180px, calc(100vw - 32px));
            margin: 0 auto;
            padding: 28px 0 40px;
        }

        .page.page-home {
            width: 100%;
            max-width: none;
            padding: 0;
        }

        .app-auth-dock {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px 12px;
            border-radius: 999px;
            border: 1px solid rgba(148, 186, 255, 0.16);
            background: rgba(7, 12, 25, 0.7);
            backdrop-filter: blur(14px);
            box-shadow: 0 16px 34px rgba(0, 0, 0, 0.24);
        }

        .app-auth-user {
            color: rgba(235, 243, 255, 0.78);
            font-size: 12px;
            letter-spacing: 0.06em;
        }

        .app-auth-link,
        .app-auth-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(148, 186, 255, 0.16);
            color: rgba(244, 248, 255, 0.94);
            background: rgba(255, 255, 255, 0.06);
        }

        .app-auth-button {
            cursor: pointer;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(280px, 0.95fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .hero-card, .side-card, .panel, .memory-card {
            background: var(--paper);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
        }

        .hero-card {
            padding: 28px;
            position: relative;
            overflow: hidden;
        }

        .hero-card::after {
            content: "";
            position: absolute;
            inset: auto -80px -110px auto;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(240, 111, 90, 0.26), transparent 68%);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.7);
            color: var(--subtle);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 10px;
            font-size: clamp(32px, 5vw, 52px);
            line-height: 1.05;
        }

        .hero-copy, .panel-subtitle, .note-card p, .memory-content, .detail-content {
            color: var(--subtle);
            line-height: 1.8;
        }

        .hero-copy {
            max-width: 720px;
            margin: 0 0 22px;
            font-size: 16px;
        }

        .hero-actions, .chip-row, .form-actions, .detail-actions, .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn, .chip-link, .chip-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease;
        }

        .btn:hover, .chip-link:hover, .chip-button:hover { transform: translateY(-1px); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #f39a63);
            color: white;
            box-shadow: 0 12px 28px rgba(240, 111, 90, 0.28);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--line);
            color: var(--ink);
        }
        .btn-danger {
            background: #fff1f1;
            color: var(--danger);
            border-color: rgba(169, 53, 53, 0.2);
        }

        .side-card, .panel { padding: 22px; }
        .side-card { display: grid; gap: 16px; align-content: start; }
        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(320px, 0.9fr);
            gap: 20px;
            align-items: start;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .panel-header h2, .panel-header h3 {
            margin: 0 0 6px;
            font-size: 24px;
        }

        .panel-subtitle { margin: 0; font-size: 14px; }
        .stat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .stat, .recent-item, .detail-box, .note-card {
            padding: 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid var(--line);
        }

        .stat-label, .detail-label {
            display: block;
            margin-bottom: 10px;
            color: var(--subtle);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .stat-value { font-size: 26px; font-weight: 700; }
        .recent-list, .memory-grid, .detail-stack, .form-grid, .field, .emotion-section { display: grid; gap: 16px; }
        .recent-item strong { display: block; margin-bottom: 6px; font-size: 14px; }

        .flash, .error-list, .empty-state {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.6;
        }

        .flash {
            background: #edf8ef;
            border: 1px solid rgba(60, 140, 80, 0.22);
            color: #25633a;
        }
        .error-list {
            background: #fff0ee;
            border: 1px solid rgba(169, 53, 53, 0.18);
            color: #7d2828;
        }
        .empty-state {
            border: 1px dashed rgba(31, 36, 48, 0.2);
            background: rgba(255, 255, 255, 0.6);
            text-align: center;
            color: var(--subtle);
        }

        .chip-link {
            background: rgba(255, 255, 255, 0.86);
            border-color: var(--line);
            color: var(--subtle);
            font-size: 14px;
        }

        .chip-link.active, .chip-option input:checked + .chip-button {
            background: var(--ink);
            color: white;
            border-color: var(--ink);
        }

        .memory-grid { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .memory-card { padding: 18px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .memory-card:hover { transform: translateY(-3px); box-shadow: 0 28px 52px rgba(37, 32, 52, 0.14); }
        .memory-meta, .memory-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            color: var(--subtle);
            font-size: 13px;
        }

        .memory-content, .detail-content { margin: 0; font-size: 15px; }
        .detail-content { white-space: pre-wrap; }
        .field label { font-weight: 700; font-size: 14px; color: var(--ink); }
        .chip-option { position: relative; }
        .chip-option input { position: absolute; opacity: 0; pointer-events: none; }
        .chip-button {
            min-height: 44px;
            padding: 11px 16px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid var(--line);
            color: var(--ink);
            font-size: 14px;
        }

        .emotion-section h4 { margin: 0; font-size: 14px; color: var(--subtle); }

        textarea {
            width: 100%;
            min-height: 180px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.94);
            color: var(--ink);
            resize: vertical;
            line-height: 1.8;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }
        .badge-positive { background: var(--positive); }
        .badge-neutral { background: var(--neutral); }
        .badge-negative { background: var(--negative); }

        @media (max-width: 920px) {
            .hero, .layout { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .page { width: min(100vw - 18px, 1180px); padding-top: 12px; }
            .hero-card, .side-card, .panel, .memory-card { border-radius: 22px; }
            .hero-card, .side-card, .panel { padding: 18px; }
            .panel-header { flex-direction: column; }
            h1 { font-size: 30px; }
            .app-auth-dock {
                left: 12px;
                right: 12px;
                top: 12px;
                justify-content: space-between;
            }
        }
    </style>
    @stack('head')
    @stack('styles')
</head>
<body class="@yield('body_class')">
    @auth
        <div class="app-auth-dock">
            <span class="app-auth-user">{{ auth()->user()->email }}</span>
            <a class="app-auth-link" href="{{ route('memories.bubbles') }}">記憶の玉</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="app-auth-button" type="submit">ログアウト</button>
            </form>
        </div>
    @endauth
    <div class="page @yield('page_class')">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
