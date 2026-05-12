# 0004: 管理画面接続検証 token は artisan command で発行する

日付: 2026-05-04

## 決定

管理画面モックアップを実 API に接続して検証するための Bearer token は、public API endpoint ではなく artisan command で発行する。

次の実装 task では `php artisan bunshin:issue-admin-token` を追加し、検証用 tenant / user / personal access token を server-side で作成または再発行できるようにする。

## 背景

現在の backend は token-first 方針で、protected API routes は `auth:sanctum` guard を使う。管理画面モックアップは Settings に API Base URL と Bearer token を保存して実 API を呼べる状態になっている。一方で、初期 backend には password login や token issuance API endpoint はまだ存在しない。

この段階の目的は、管理画面モックアップから memories / categories / tags / health の接続を検証することであり、本格的な管理者ログイン UI や credential lifecycle の設計ではない。

## 比較

### login endpoint

利点:

- 管理画面から token 発行まで完結できる。
- 将来の管理者ログイン機能に近い。

リスク:

- password credential、rate limit、token revoke、audit、validation error、UI 入力導線まで設計対象が広がる。
- 現在の task scope で public attack surface を増やす。
- 管理画面モックアップの接続検証には過剰。

判断: 初期検証用途では採用しない。

### 管理用 seed

利点:

- database 初期化と一緒に検証 user を用意できる。
- ローカル開発で再現しやすい。

リスク:

- plain text token は hash 保存後に再表示できないため、seed だけでは運用が不安定。
- seed 内に固定 token を置くと漏えいリスクが高い。
- token 再発行や期限変更の操作が seed 実行に寄りすぎる。

判断: 必要なら sample data 作成の補助として後で検討するが、token 発行の主運用にはしない。

### artisan command

利点:

- public API surface を増やさず、server-side だけで token を発行できる。
- plain text token を発行時に 1 回だけ表示する current token model と相性がよい。
- tenant / user の作成、既存 user への token 追加、期限付き token 発行、既存 token revoke を小さく実装できる。
- 管理画面モックアップの Settings に token を貼る検証フローと直接つながる。

リスク:

- remote browser だけでは token を自力発行できず、server shell access が必要。
- 本番管理者ログインの代替にはならない。

判断: 初期検証運用として採用する。

## command contract draft

次回実装する command の想定 contract:

```bash
php artisan bunshin:issue-admin-token \
  --tenant=default \
  --tenant-name="Default Tenant" \
  --email=admin@example.test \
  --name="Admin User" \
  --token-name=admin-mockup \
  --expires-days=30
```

- `--tenant`: tenant slug。存在しなければ作成する。
- `--tenant-name`: tenant 作成時の表示名。省略時は slug から作る。
- `--email`: token を発行する user email。存在しなければ作成する。
- `--name`: user 作成時の表示名。省略時は email を使う。
- `--token-name`: personal access token name。省略時は `admin-mockup`。
- `--expires-days`: token expiry。省略時は 30 日。`0` は無期限ではなく invalid とする。
- 作成 user の password は login endpoint がないためランダム値にする。
- command output は plain text token を 1 回だけ表示し、hash のみ DB に保存する。

## 影響

- token 発行 API endpoint は引き続き置かない。
- 管理画面モックアップ README は artisan command 発行を標準手順として参照する。
- `docs/architecture/api_contract.md` の token 発行方針を server-side command 前提に更新する。
- 本格 login / session / admin account lifecycle は別 decision / 別 task で扱う。

## 実装状況

`php artisan bunshin:issue-admin-token` は実装済み。tenant / user が存在しなければ作成し、同じ user / token name の既存 token を revoke してから新しい token を発行する。plain text token は command output に 1 回だけ表示し、DB には hash のみ保存する。

## 次の task

管理画面モックアップから実 API への手動接続 smoke test 手順を整理する。
