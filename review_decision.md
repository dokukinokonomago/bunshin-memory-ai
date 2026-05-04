# Review Decision: Auth 方針

作成: 2026-05-04 04:43:15 JST
最終確認: 2026-05-04 07:28:24 JST

## 判断状況

決定済み。

2026-05-04 07:28:24 JST に、ユーザーが `/api/v1` の正式認証方針として token-first を選択した。以後は session-first ではなく Bearer token 前提で auth 実装、route middleware、管理画面モックアップ接続を進める。

## 判断したいこと

分身AIバックエンドの `/api/v1` を、token-first API として作るか、session-first admin API として作るかを決める。

## 現状

- API は `/api/v1` 配下に JSON endpoint として実装済み。
- `routes/api.php` の protected endpoint は現在 `auth` middleware を使っている。
- `config/auth.php` は Laravel 標準の `web` session guard のみ定義されている。
- `composer.json` に token auth 用 package は未導入。
- Feature test は `$this->actingAs($user)` で認証済み user context を作っている。
- backend automation の対象は API 実装まで。frontend は別 automation で後から再設計する。
- 管理画面モックアップの mock API layer は plain `fetch('/api/v1/...')` で、現時点では token header / CSRF / login flow を固定していない。

## 選択肢 A: token-first

Bearer token で `/api/v1` を認証する。Laravel 標準候補としては Sanctum personal access token 相当を使い、API client は `Authorization: Bearer <token>` を送る。

### 向いている条件

- frontend と backend を別 origin / 別 deploy にしやすくしたい。
- 将来、管理画面以外の client、CLI、mobile、AI worker から同じ API を使う可能性がある。
- API-first backend として、session cookie や CSRF への依存を薄くしたい。
- tenant / owner context を token に紐づく user から安定して決めたい。

### 影響

- `laravel/sanctum` など token guard の導入が必要。
- `auth` middleware を `auth:sanctum` など明示 guard に変更する。
- login / token issuance / token revoke endpoint または管理用 token 発行手順が必要。
- Feature test は token guard 前提に寄せる。既存の domain/API tests は大きく壊さず、認証 helper を共通化できる。

### リスク

- token の保管、失効、漏洩時対応を最初から決める必要がある。
- 管理画面が browser-only なら、localStorage 等への token 保存設計を避けるための追加設計が必要。

## 選択肢 B: session-first

Laravel の `web` session guard を使い、browser 管理画面から cookie + CSRF で `/api/v1` を呼ぶ。

### 向いている条件

- 初期 client が同一 origin の管理画面だけである。
- Laravel の login / logout / password reset を近く実装したい。
- token 管理より、browser session の標準挙動を優先したい。

### 影響

- `web` middleware / session / CSRF と API route の境界を明確にする必要がある。
- 別 origin frontend にすると、cookie domain、SameSite、CORS、CSRF cookie の設計が必要になる。
- 管理画面モックアップの `fetch` に credentials / CSRF header 対応が必要になる。
- API-only client や worker からは使いにくいため、後で token auth を追加する可能性が高い。

### リスク

- API-first の設計に session 前提が入りやすい。
- frontend が別 automation / 別 deploy になる場合、最初の接続確認が token-first より複雑になりやすい。

## 推奨

初期方針は token-first を推奨する。

理由:

- この repo は frontend を持たない API backend として作り直している。
- 管理画面は後続 automation で再設計されるため、同一 origin session 前提に固定しない方が接続設計を選びやすい。
- 将来の AI worker / external client から memory API を使う余地がある。
- `tenant_id` / `owner_user_id` 境界は authenticated user から決まるため、Bearer token でも既存 API の domain 境界設計と相性がよい。

ただし、初期管理画面を Laravel 同一 app 内でだけ配信し、外部 client を当面作らないなら session-first でも成立する。

## 決定内容

1. `/api/v1` の正式認証は token-first とする。
2. API client は `Authorization: Bearer <token>` を送る。
3. Laravel 標準候補として Sanctum personal access token 相当を導入する。
4. protected API routes は `auth:sanctum` など token guard を明示する。
5. 管理画面モックアップ接続も token-first 前提で行う。

## 追加で実装時に決める項目

1. 初期 token 発行を user login endpoint で行うか、管理用 seed / artisan command で始めるか。
2. 管理画面モックアップ側で token をどこに保持するか。
3. token revoke / rotate の最小 endpoint を初期実装に含めるか。

## 決定後の次 task

Sanctum 相当の token auth を導入し、`/api/v1` protected routes の guard と Feature test helper を更新する。その後、管理画面モックアップの mock API layer を token-first の real API client に置き換える。
