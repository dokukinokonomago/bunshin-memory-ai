@extends('layouts.app')

@section('title', 'ホーム | 分身AI MVP')

@section('content')
    <section class="home-panel">
        <div class="home-copy">
            <span class="eyebrow">BUNSHIN AI</span>
            <h1>あなたの記憶を、静かに残す。</h1>
            <p>
                記憶を追加して、見返して、必要なときにたどれるシンプルなホーム画面です。
            </p>
        </div>

        <div class="home-actions">
            <a class="home-action-btn is-primary" href="{{ route('memories.create') }}">記憶を追加する</a>
            <a class="home-action-btn" href="{{ route('memories.index') }}">記憶を見る</a>
            <span class="home-action-btn is-disabled" aria-disabled="true">記憶と話す（ダミー）</span>
            <span class="home-action-btn is-disabled" aria-disabled="true">友だちと共有する（ダミー）</span>
        </div>
    </section>

    <style>
        .home-panel {
            position: relative;
            min-height: min(760px, calc(100vh - 72px));
            display: grid;
            align-content: center;
            justify-items: center;
            gap: 40px;
            padding: 56px 32px;
            border-radius: 32px;
            overflow: hidden;
            background:
                radial-gradient(circle at 18% 18%, rgba(86, 132, 255, 0.18), transparent 20%),
                radial-gradient(circle at 82% 16%, rgba(126, 209, 255, 0.14), transparent 18%),
                radial-gradient(circle at 50% 72%, rgba(88, 108, 255, 0.12), transparent 26%),
                linear-gradient(160deg, #02040b 0%, #050916 48%, #0a1124 100%);
            color: rgba(238, 245, 255, 0.94);
            box-shadow: 0 30px 80px rgba(6, 10, 24, 0.36);
        }

        .home-panel::before,
        .home-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .home-panel::before {
            width: 420px;
            height: 420px;
            left: -140px;
            top: -120px;
            background: radial-gradient(circle, rgba(91, 155, 255, 0.16), transparent 68%);
        }

        .home-panel::after {
            width: 360px;
            height: 360px;
            right: -80px;
            bottom: -140px;
            background: radial-gradient(circle, rgba(120, 214, 255, 0.14), transparent 70%);
        }

        .home-copy {
            position: relative;
            z-index: 1;
            max-width: 720px;
            text-align: center;
        }

        .home-copy .eyebrow {
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

        .home-copy h1 {
            margin: 18px 0 16px;
            font-size: clamp(38px, 5.4vw, 74px);
            line-height: 1.04;
            color: rgba(247, 250, 255, 0.98);
            letter-spacing: 0.04em;
        }

        .home-copy p {
            margin: 0;
            color: rgba(198, 218, 247, 0.78);
            font-size: clamp(15px, 1.9vw, 18px);
            line-height: 1.9;
        }

        .home-actions {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 14px;
            width: min(420px, 100%);
        }

        .home-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 56px;
            padding: 0 18px;
            border-radius: 14px;
            border: 1px solid rgba(166, 204, 255, 0.16);
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.28);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.02em;
            transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .home-action-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(196, 224, 255, 0.34);
            background: linear-gradient(135deg, rgba(88, 150, 255, 0.42), rgba(53, 98, 213, 0.92));
            color: rgba(250, 252, 255, 0.98);
            box-shadow: 0 14px 28px rgba(18, 36, 78, 0.32);
        }

        .home-action-btn.is-primary {
            background: linear-gradient(135deg, rgba(142, 204, 255, 0.28), rgba(87, 132, 255, 0.78));
            border-color: rgba(180, 218, 255, 0.24);
            color: rgba(245, 249, 255, 0.96);
            box-shadow: 0 14px 28px rgba(40, 82, 168, 0.26);
        }

        .home-action-btn.is-disabled {
            opacity: 0.58;
            cursor: default;
        }

        .home-action-btn.is-disabled:hover {
            transform: none;
            border-color: rgba(166, 204, 255, 0.16);
            background: linear-gradient(135deg, rgba(20, 29, 54, 0.92), rgba(11, 19, 38, 0.96));
            color: rgba(232, 241, 255, 0.92);
            box-shadow: 0 10px 24px rgba(6, 10, 24, 0.28);
        }

        @media (max-width: 760px) {
            .home-panel {
                min-height: min(680px, calc(100vh - 40px));
                padding: 42px 18px;
                border-radius: 24px;
            }

            .home-actions {
                width: 100%;
            }

            .home-action-btn {
                min-height: 52px;
                font-size: 14px;
            }
        }
    </style>
@endsection
