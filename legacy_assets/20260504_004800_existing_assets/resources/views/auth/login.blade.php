@extends('layouts.app')

@section('title', 'ログイン | 分身AI MVP')
@section('body_class', 'body-auth-login')
@section('page_class', 'page-auth-login')
@section('hide_auth_dock', '1')

@section('content')
    <section class="auth-login-screen">
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
            min-height: 100vh;
            padding: 40px;
            background:
                radial-gradient(circle at 12% 16%, rgba(84, 133, 255, 0.22), transparent 22%),
                radial-gradient(circle at 84% 18%, rgba(97, 220, 255, 0.16), transparent 20%),
                radial-gradient(circle at 70% 84%, rgba(255, 171, 94, 0.12), transparent 18%),
                linear-gradient(180deg, #02050d 0%, #050a17 44%, #0a1020 100%);
            color: rgba(244, 248, 255, 0.96);
        }

        .auth-login-shell {
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
            border: 1px solid rgba(255, 255, 255, 0.48);
            border-radius: 999px;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 0% 100%, rgba(74, 157, 255, 0.48), transparent 42%),
                radial-gradient(circle at 100% 0%, rgba(255, 182, 100, 0.42), transparent 44%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.88), rgba(255, 255, 255, 0.28) 38%, rgba(255, 255, 255, 0.18) 72%, rgba(255, 255, 255, 0.26)),
                rgba(255, 255, 255, 0.2);
            color: rgba(18, 31, 58, 0.94);
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow:
                0 18px 36px rgba(8, 18, 44, 0.18),
                0 0 26px rgba(122, 190, 255, 0.18),
                inset 0 1px 0 rgba(255, 255, 255, 0.96),
                inset 0 -10px 20px rgba(147, 182, 226, 0.24);
            backdrop-filter: blur(18px) saturate(1.08);
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.32);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .auth-login-submit::before {
            content: "";
            position: absolute;
            left: 10%;
            right: 10%;
            top: 1px;
            height: 46%;
            border-radius: inherit;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.88), rgba(255, 255, 255, 0.18) 62%, transparent);
            pointer-events: none;
        }

        .auth-login-submit:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.62);
            box-shadow:
                0 22px 40px rgba(8, 18, 44, 0.22),
                0 0 32px rgba(122, 190, 255, 0.22),
                inset 0 1px 0 rgba(255, 255, 255, 0.98),
                inset 0 -10px 20px rgba(147, 182, 226, 0.26);
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
