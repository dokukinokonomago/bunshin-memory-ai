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
            --glass-border: rgba(255, 255, 255, 0.4);
            --glass-border-soft: rgba(255, 255, 255, 0.22);
            --glass-surface:
                linear-gradient(180deg, rgba(255, 255, 255, 0.82), rgba(255, 255, 255, 0.34) 32%, rgba(255, 255, 255, 0.18) 62%, rgba(255, 255, 255, 0.26) 100%),
                linear-gradient(135deg, rgba(255, 205, 129, 0.14), rgba(255, 255, 255, 0) 24%, rgba(110, 196, 255, 0.14) 62%, rgba(199, 180, 255, 0.12) 100%),
                rgba(255, 255, 255, 0.28);
            --glass-shadow:
                0 16px 32px rgba(8, 18, 44, 0.12),
                0 5px 12px rgba(255, 255, 255, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.92),
                inset 0 -9px 20px rgba(146, 181, 230, 0.22);
            --glass-highlight:
                linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(255, 255, 255, 0.42) 42%, rgba(255, 255, 255, 0.08));
            --glass-text: rgba(18, 31, 58, 0.88);
            --glass-primary-tint:
                radial-gradient(circle at 14% 100%, rgba(72, 154, 255, 0.48), transparent 42%),
                radial-gradient(circle at 100% 0%, rgba(71, 220, 255, 0.42), transparent 44%),
                linear-gradient(135deg, rgba(42, 124, 255, 0.64), rgba(88, 200, 255, 0.42) 52%, rgba(168, 213, 255, 0.3));
            --glass-secondary-tint:
                radial-gradient(circle at 14% 100%, rgba(255, 184, 116, 0.28), transparent 42%),
                radial-gradient(circle at 100% 0%, rgba(155, 208, 255, 0.22), transparent 44%),
                linear-gradient(135deg, rgba(255, 255, 255, 0.42), rgba(214, 233, 255, 0.18) 52%, rgba(255, 222, 175, 0.2));
            --glass-danger-tint:
                radial-gradient(circle at 14% 100%, rgba(255, 140, 164, 0.42), transparent 42%),
                radial-gradient(circle at 100% 0%, rgba(255, 211, 122, 0.28), transparent 44%),
                linear-gradient(135deg, rgba(255, 109, 132, 0.44), rgba(255, 170, 126, 0.24) 52%, rgba(255, 233, 187, 0.16));
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
            border: 1px solid rgba(255, 255, 255, 0.14);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.06)),
                rgba(8, 14, 30, 0.56);
            backdrop-filter: blur(18px) saturate(1.12);
            box-shadow:
                0 22px 42px rgba(0, 0, 0, 0.24),
                inset 0 1px 0 rgba(255, 255, 255, 0.22);
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
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: rgba(244, 248, 255, 0.96);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.06)),
                linear-gradient(135deg, rgba(112, 184, 255, 0.14), rgba(255, 225, 160, 0.08) 48%, rgba(187, 156, 255, 0.12)),
                rgba(255, 255, 255, 0.06);
            box-shadow:
                0 12px 24px rgba(0, 0, 0, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.36),
                inset 0 -8px 18px rgba(102, 155, 255, 0.12);
            backdrop-filter: blur(14px) saturate(1.08);
        }

        .app-auth-button {
            cursor: pointer;
        }

        .app-auth-link::before,
        .app-auth-button::before {
            content: "";
            position: absolute;
            left: 10%;
            right: 10%;
            top: 1px;
            height: 46%;
            border-radius: inherit;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.12) 62%, transparent);
            opacity: 0.82;
            pointer-events: none;
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
            border: 1px solid var(--glass-border);
            cursor: pointer;
            transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
            position: relative;
            overflow: hidden;
            isolation: isolate;
            background: var(--glass-surface);
            color: var(--glass-text);
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(18px) saturate(1.14);
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.28);
        }

        .btn::before, .chip-link::before, .chip-button::before {
            content: "";
            position: absolute;
            left: 10%;
            right: 10%;
            top: 1px;
            height: 46%;
            border-radius: inherit;
            background: var(--glass-highlight);
            opacity: 0.84;
            pointer-events: none;
            z-index: -1;
        }

        .btn::after, .chip-link::after, .chip-button::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background:
                radial-gradient(circle at 0% 100%, rgba(255, 194, 102, 0.2), transparent 34%),
                radial-gradient(circle at 100% 0%, rgba(110, 199, 255, 0.2), transparent 34%);
            opacity: 0.72;
            pointer-events: none;
            z-index: -2;
        }

        .btn:hover, .chip-link:hover, .chip-button:hover {
            transform: translateY(-1px);
            box-shadow:
                0 20px 36px rgba(8, 18, 44, 0.16),
                0 5px 12px rgba(255, 255, 255, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.94),
                inset 0 -10px 20px rgba(134, 169, 220, 0.24);
        }
        .btn-primary {
            background: var(--glass-primary-tint), var(--glass-surface);
            color: rgba(246, 251, 255, 0.98);
            border-color: rgba(186, 232, 255, 0.54);
            box-shadow:
                0 16px 34px rgba(26, 84, 173, 0.2),
                0 0 24px rgba(92, 196, 255, 0.22),
                inset 0 1px 0 rgba(255, 255, 255, 0.94),
                inset 0 -10px 20px rgba(78, 137, 255, 0.18);
        }
        .btn-secondary {
            background: var(--glass-secondary-tint), var(--glass-surface);
            border-color: rgba(255, 255, 255, 0.44);
            color: rgba(25, 36, 63, 0.92);
        }
        .btn-danger {
            background: var(--glass-danger-tint), var(--glass-surface);
            color: rgba(98, 22, 42, 0.9);
            border-color: rgba(255, 198, 210, 0.5);
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
            color: rgba(32, 46, 80, 0.76);
            font-size: 14px;
        }

        .chip-link.active, .chip-option input:checked + .chip-button {
            background: var(--glass-primary-tint), var(--glass-surface);
            color: rgba(243, 249, 255, 0.98);
            border-color: rgba(187, 229, 255, 0.58);
            box-shadow:
                0 16px 30px rgba(31, 77, 156, 0.18),
                0 0 22px rgba(98, 197, 255, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.94),
                inset 0 -8px 18px rgba(82, 136, 250, 0.18);
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
            color: rgba(24, 38, 70, 0.88);
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
            font-weight: 700;
            color: rgba(24, 36, 62, 0.86);
            border: 1px solid rgba(255, 255, 255, 0.52);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.84), rgba(255, 255, 255, 0.28)),
                rgba(255, 255, 255, 0.16);
            box-shadow:
                0 10px 22px rgba(8, 18, 44, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.94),
                inset 0 -8px 18px rgba(146, 181, 230, 0.18);
            backdrop-filter: blur(16px);
        }
        .badge-positive {
            background:
                radial-gradient(circle at 0% 100%, rgba(255, 186, 121, 0.36), transparent 36%),
                radial-gradient(circle at 100% 0%, rgba(255, 132, 174, 0.22), transparent 38%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(255, 244, 232, 0.42)),
                rgba(255, 255, 255, 0.18);
        }
        .badge-neutral {
            background:
                radial-gradient(circle at 0% 100%, rgba(124, 211, 255, 0.3), transparent 36%),
                radial-gradient(circle at 100% 0%, rgba(146, 170, 255, 0.24), transparent 38%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.88), rgba(233, 243, 255, 0.42)),
                rgba(255, 255, 255, 0.18);
        }
        .badge-negative {
            background:
                radial-gradient(circle at 0% 100%, rgba(199, 149, 255, 0.32), transparent 36%),
                radial-gradient(circle at 100% 0%, rgba(255, 168, 202, 0.22), transparent 38%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.88), rgba(244, 237, 255, 0.42)),
                rgba(255, 255, 255, 0.18);
        }

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
        @if (trim($__env->yieldContent('hide_auth_dock')) !== '1')
        <div class="app-auth-dock">
            <span class="app-auth-user">{{ auth()->user()->email }}</span>
            <a class="app-auth-link" href="{{ route('memories.bubbles') }}">記憶の玉</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="app-auth-button" type="submit">ログアウト</button>
            </form>
        </div>
        @endif
    @endauth
    <div class="page @yield('page_class')">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
