# バックエンド設計

## 目的

分身AI の記憶データを、将来の対話・分析・人格生成に耐える形で保存、検索、権限管理できる API backend として作り直す。

## 初期スコープ

- API-first の JSON backend。
- 記憶、カテゴリ、タグを正規化して保存する。
- `tenant_id` と `owner_user_id` を基本境界にする。
- 「墓場まで」相当の秘匿記憶は magic tag ではなく `visibility` で表現する。
- 旧 UI は完全に破棄する。管理画面モックアップは実 API 接続対象に含めるが、本格 frontend 再設計や新規 UI デザインは対象外にする。
- `visibility=secret` の記憶は通常 list から除外し、明示的に `secret` を要求した場合だけ取得できるようにする。

## 技術方針

- Framework: Laravel 13
- Primary DB: MySQL
- Test DB: SQLite
- API namespace: `/api/v1`
- Response: JSON only
- Auth: token-first。Bearer token / Sanctum personal access token 相当を使い、protected routes は `auth:sanctum` guard で保護する。

## レイヤ構成

- Routes: API の入口。Controller へ薄く委譲する。
- Controllers: request validation と response shaping。
- Actions / Services: 記憶作成、タグ正規化、検索などの use case。
- Models: Eloquent model。tenant 境界の query scope を持つ。
- Policies: owner / tenant / visibility の認可。
- Tests: Feature test で API 契約、Unit test で正規化ロジック。

## MVP API

- `GET /api/v1/health`
- `GET /api/v1/memories`
- `POST /api/v1/memories`
- `GET /api/v1/memories/{memory}`
- `PATCH /api/v1/memories/{memory}`
- `DELETE /api/v1/memories/{memory}`
- `GET /api/v1/categories`
- `POST /api/v1/categories`
- `GET /api/v1/tags`

## Auth baseline

- `personal_access_tokens` table に sha256 hash 化した token を保存する。
- client が使う token は `id|plainTextToken` 形式にし、`Authorization: Bearer <token>` で送る。
- 初期実装では token 発行 API endpoint は置かず、管理画面モックアップ接続検証用 token は artisan command で発行する。
- `php artisan bunshin:issue-admin-token` は検証用 tenant / user を作成または再利用し、同名 token を revoke してから新 token を 1 回だけ表示する。
- `sanctum` guard は内部の Sanctum 相当 implementation として登録済み。後で Laravel Sanctum package に置き換える場合も route contract は `auth:sanctum` のまま維持する。

## 管理画面モックアップ参照

管理画面用の静的モックアップを `docs/references/admin-ui-mockup/` に配置している。Codex automation が backend API を実装する際は、必要に応じて `index.html` と `app.js` の API client を参照し、管理画面が必要とする endpoint、field、filter、secret memory 導線を確認する。

この automation は、管理画面モックアップを実 API に繋ぐための最小限の HTML / JS 改修までを対象に含める。見た目刷新、画面構成の大幅変更、本格 frontend app 化は別 automation で扱う。

現在のモックアップは real API client へ接続済み。Settings で API Base URL と Bearer token を保存し、memories の list/detail/create/update/delete、categories の list/detail/create/update/delete、tags list、health を既存画面から確認する。

手動確認手順は `docs/references/admin-ui-mockup/manual-smoke-test.md` を正とする。local backend と静的 mockup server を起動し、`php artisan bunshin:issue-admin-token` で発行した Bearer token を Settings に貼って確認する。

## 非対象

- 本格 frontend app 化または UI 再設計。
- 旧 Blade UI の再実装または復元。
- AI 生成・要約。
- 画像・音声アップロード。
- 複雑な共有権限。

## 次の実装 task

管理画面モックアップから実 API への手動接続 smoke test を実施し、結果を記録する。`docs/references/admin-ui-mockup/manual-smoke-test.md` に沿って確認し、backend API と mockup の食い違いがあれば `task_board.md` の追加 task 候補に記録する。
