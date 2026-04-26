<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン | 分身AI MVP</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            font-family: "Avenir Next", "Hiragino Sans", "Yu Gothic", sans-serif;
            background: #f3f6fb;
            color: #182033;
        }

        body {
            min-height: 100vh;
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f3f6fb;
        }

        .login-card {
            width: min(100%, 440px);
            padding: 28px;
            border-radius: 24px;
            border: 1px solid #d7deeb;
            background: #ffffff;
            box-shadow: 0 18px 48px rgba(24, 32, 51, 0.12);
        }

        .login-kicker {
            display: inline-block;
            margin-bottom: 12px;
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid #d7deeb;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            color: #4d6086;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 34px;
            line-height: 1.08;
        }

        p {
            margin: 0 0 22px;
            color: #5c6a84;
            line-height: 1.7;
        }

        .login-hint {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            background: #f7f9fc;
            border: 1px solid #e0e6f0;
        }

        .login-hint strong,
        .login-hint span {
            display: block;
        }

        .login-hint span {
            margin-bottom: 4px;
            font-size: 12px;
            letter-spacing: 0.08em;
            color: #60769f;
        }

        .login-hint strong {
            font-size: 16px;
            color: #182033;
        }

        .error-list {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid #f0c7c7;
            background: #fff3f1;
            color: #8c2c2c;
            line-height: 1.6;
        }

        .login-form {
            display: grid;
            gap: 14px;
        }

        .login-field {
            display: grid;
            gap: 8px;
        }

        .login-field span,
        .login-remember span {
            font-size: 14px;
            color: #5c6a84;
        }

        .login-field input {
            width: 100%;
            min-height: 52px;
            padding: 0 14px;
            border: 1px solid #ccd5e4;
            border-radius: 14px;
            background: #ffffff;
            color: #182033;
        }

        .login-remember {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .login-submit {
            min-height: 54px;
            border: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, #7abfff, #ffba76);
            color: #091221;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
        }

        @media (max-width: 640px) {
            .login-card {
                padding: 22px;
            }

            h1 {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>
    <main class="login-page">
        <section class="login-card">
            <span class="login-kicker">BUNSHIN AI LOGIN</span>
            <h1>記憶の玉へ入る</h1>
            <p>ログイン後、記憶の玉画面へ移動します。</p>

            <div class="login-hint">
                <span>初期メールアドレス</span>
                <strong>{{ $defaultEmail }}</strong>
            </div>

            <div class="login-hint">
                <span>初期パスワード</span>
                <strong>{{ $defaultPassword }}</strong>
            </div>

            @if ($errors->any())
                <div class="error-list">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}" class="login-form">
                @csrf

                <label class="login-field">
                    <span>メールアドレス</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $defaultEmail) }}"
                        autocomplete="email"
                        required
                    >
                </label>

                <label class="login-field">
                    <span>パスワード</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1">
                    <span>ログイン状態を保持する</span>
                </label>

                <button class="login-submit" type="submit">記憶の玉へ進む</button>
            </form>
        </section>
    </main>
</body>
</html>
