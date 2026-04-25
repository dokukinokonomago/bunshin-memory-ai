@extends('layouts.app')

@section('title', 'ログイン | 分身AI MVP')
@section('body_class', 'body-auth-login')
@section('page_class', 'page-auth-login')

@section('content')
    <section class="auth-login-screen">
        <div class="auth-login-decor" aria-hidden="true">
            <span class="auth-login-glow glow-a"></span>
            <span class="auth-login-glow glow-b"></span>
            <span class="auth-login-grid"></span>
        </div>

        <div class="auth-login-shell">
            <section class="auth-login-copy">
                <span class="auth-login-kicker">BUNSHIN AI LOGIN</span>
                <h1>記憶の玉へ入る</h1>
                <p>
                    ログイン後、記憶の玉画面へ移動します。
                    この環境では最初のユーザーを自動で用意します。
                </p>

                <div class="auth-login-hint">
                    <span>初期メールアドレス</span>
                    <strong>{{ $defaultEmail }}</strong>
                </div>

                <div class="auth-login-hint">
                    <span>初期パスワード</span>
                    <strong>{{ $defaultPassword }}</strong>
                </div>
            </section>

            <section class="auth-login-card">
                <div class="auth-login-card-head">
                    <span class="auth-login-label">ACCESS</span>
                    <h2>ログイン</h2>
                </div>

                @if ($errors->any())
                    <div class="error-list">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="post" action="{{ route('login.store') }}" class="auth-login-form">
                    @csrf

                    <label class="auth-login-field">
                        <span>メールアドレス</span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email', $defaultEmail) }}"
                            autocomplete="email"
                            required
                        >
                    </label>

                    <label class="auth-login-field">
                        <span>パスワード</span>
                        <input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                    </label>

                    <label class="auth-login-remember">
                        <input type="checkbox" name="remember" value="1">
                        <span>ログイン状態を保持する</span>
                    </label>

                    <button class="auth-login-submit" type="submit">記憶の玉へ進む</button>
                </form>
            </section>
        </div>
    </section>

    <style>
        .body-auth-login {
            background: #000;
        }

        .page.page-auth-login {
            width: 100%;
            max-width: none;
            padding: 0;
        }

        .auth-login-screen {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            padding: 40px;
            background:
                radial-gradient(circle at 12% 16%, rgba(84, 133, 255, 0.22), transparent 22%),
                radial-gradient(circle at 84% 18%, rgba(97, 220, 255, 0.16), transparent 20%),
                radial-gradient(circle at 70% 84%, rgba(255, 171, 94, 0.12), transparent 18%),
                linear-gradient(180deg, #02050d 0%, #050a17 44%, #0a1020 100%);
            color: rgba(244, 248, 255, 0.96);
        }

        .auth-login-decor,
        .auth-login-glow,
        .auth-login-grid {
            position: absolute;
        }

        .auth-login-decor {
            inset: 0;
            pointer-events: none;
        }

        .auth-login-glow {
            border-radius: 50%;
            filter: blur(18px);
        }

        .auth-login-glow.glow-a {
            width: 340px;
            height: 340px;
            left: -120px;
            top: 18%;
            background: radial-gradient(circle, rgba(88, 136, 255, 0.28), transparent 72%);
        }

        .auth-login-glow.glow-b {
            width: 280px;
            height: 280px;
            right: -90px;
            bottom: 8%;
            background: radial-gradient(circle, rgba(115, 226, 255, 0.18), transparent 72%);
        }

        .auth-login-grid {
            inset: 0;
            opacity: 0.18;
            background-image:
                linear-gradient(rgba(118, 160, 232, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(118, 160, 232, 0.08) 1px, transparent 1px);
            background-size: 84px 84px;
            mask-image: radial-gradient(circle at center, rgba(0, 0, 0, 0.92), transparent 92%);
        }

        .auth-login-shell {
            position: relative;
            z-index: 1;
            width: min(1120px, 100%);
            margin: 0 auto;
            min-height: calc(100vh - 80px);
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(360px, 430px);
            gap: 32px;
            align-items: center;
        }

        .auth-login-copy {
            display: grid;
            gap: 18px;
            align-content: center;
        }

        .auth-login-kicker,
        .auth-login-label {
            display: inline-flex;
            width: fit-content;
            min-height: 36px;
            align-items: center;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(144, 188, 255, 0.14);
            background: rgba(255, 255, 255, 0.04);
            color: rgba(208, 226, 255, 0.82);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
        }

        .auth-login-copy h1 {
            margin: 0;
            font-size: clamp(42px, 7vw, 82px);
            line-height: 0.96;
        }

        .auth-login-copy p {
            margin: 0;
            max-width: 560px;
            color: rgba(211, 224, 245, 0.82);
            font-size: 18px;
            line-height: 1.75;
        }

        .auth-login-hint {
            display: grid;
            gap: 6px;
            width: fit-content;
            margin-top: 8px;
            padding: 16px 18px;
            border-radius: 20px;
            border: 1px solid rgba(150, 192, 255, 0.12);
            background: rgba(8, 13, 26, 0.56);
            backdrop-filter: blur(16px);
        }

        .auth-login-hint span {
            color: rgba(172, 195, 232, 0.72);
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .auth-login-hint strong {
            font-size: 18px;
            color: rgba(247, 250, 255, 0.96);
        }

        .auth-login-card {
            padding: 28px;
            border-radius: 30px;
            border: 1px solid rgba(154, 194, 255, 0.12);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
                rgba(7, 12, 24, 0.72);
            backdrop-filter: blur(18px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.24);
        }

        .auth-login-card-head {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }

        .auth-login-card-head h2 {
            margin: 0;
            font-size: 28px;
            color: rgba(248, 251, 255, 0.98);
        }

        .auth-login-form {
            display: grid;
            gap: 16px;
        }

        .auth-login-field {
            display: grid;
            gap: 8px;
        }

        .auth-login-field span,
        .auth-login-remember span {
            color: rgba(203, 220, 246, 0.84);
            font-size: 14px;
        }

        .auth-login-field input {
            width: 100%;
            min-height: 54px;
            padding: 0 16px;
            border-radius: 16px;
            border: 1px solid rgba(156, 193, 255, 0.12);
            background: rgba(255, 255, 255, 0.08);
            color: rgba(247, 250, 255, 0.98);
        }

        .auth-login-remember {
            display: inline-flex;
            gap: 10px;
            align-items: center;
        }

        .auth-login-submit {
            min-height: 56px;
            border: 0;
            border-radius: 18px;
            background: linear-gradient(135deg, #76bbff, #ffb06c);
            color: #091221;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .auth-login-screen {
                padding: 24px 18px;
            }

            .auth-login-shell {
                grid-template-columns: 1fr;
                min-height: auto;
                padding: 36px 0;
            }

            .auth-login-copy h1 {
                font-size: clamp(36px, 14vw, 58px);
            }
        }
    </style>
@endsection
