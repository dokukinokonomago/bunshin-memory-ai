# バックエンド設計

## 目的

分身AI の記憶データを、将来の対話・分析・人格生成に耐える形で保存、検索、権限管理できる API backend として作り直す。

## 初期スコープ

- API-first の JSON backend。
- 記憶、カテゴリ、タグを正規化して保存する。
- カテゴリは大カテゴリー / サブカテゴリーを `categories.parent_id` で階層化する。
- 年代はカテゴリー階層とは別軸として `period_key` / `occurred_on` に保持する。
- `tenant_id` と `owner_user_id` を基本境界にする。
- 「墓場まで」相当の秘匿記憶は magic tag ではなく `visibility` で表現する。
- 旧 UI は完全に破棄する。管理画面モックアップは実 API 接続対象に含めるが、本格 frontend 再設計や新規 UI デザインは対象外にする。
- 例外として、記憶の海 / 宇宙画面はユーザー向け探索 frontend としてこの automation の正式実装対象に含める。
- `visibility=secret` の記憶は通常 list から除外し、明示的に `secret` を要求した場合だけ取得できるようにする。

## 技術方針

- Framework: Laravel 13
- Primary DB: MySQL
- Test DB: SQLite
- API namespace: `/api/v1`
- Response: `/api/v1` は JSON only。`Accept: application/json` がない request でも、未認証は `401` JSON、validation error は `422` JSON を返す。
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
- `GET /api/v1/memory-space`
- `POST /api/v1/secret-unlocks`
- `GET /api/v1/categories`
- `POST /api/v1/categories`
- `GET /api/v1/tags`

## 記憶の海 / 宇宙画面

参照 mockup は `docs/references/memory-space-screen/memory_space.html` に配置している。

この画面の実装では、テーマ分類、時間軸、横断タグ、感情、secret unlock を分けて扱う。

- テーマ分類: `categories.parent_id` による root category / subcategory。
- 時間軸: `memories.period_key` / `memories.occurred_on`。カテゴリーとは混ぜない。
- 横断ラベル: `tags`。
- 感情: 既存 `emotion_label` / `emotion_intensity` を primary emotion とし、複数 emotion score は初期実装では `metadata.emotion_scores` から返す。
- 表示重み: 初期実装では `metadata.importance_score`。
- beliefs / chains: 初期実装では `metadata.beliefs` / `metadata.chains`。
- secret memory: 通常 payload から除外し、memory-space 画面では password unlock 風 UI と backend 追加認可を通した場合だけ返す。

`GET /api/v1/memory-space` は実装済み。category tree、memory payload、period options、secret locked summary を返す。通常は secret memory 本文・title・tag を返さない。`include_secret=true` と valid `X-Secret-Unlock` が揃った場合だけ secret memory を含め、`secret.unlock_expires_at` を返す。

`POST /api/v1/secret-unlocks` は実装済み。初期 baseline では user の account password hash を unlock password として検証し、15 分有効な user scoped token を発行する。token は `secret_unlock_tokens` に sha256 hash のみ保存し、plain text は response で 1 回だけ返す。

`GET /memory-space` は実装済み。Laravel / Vite asset として Three.js canvas を表示し、API Base URL、Bearer token、period / category filter、descendant toggle、secret unlock modal、memory list / detail panel を持つ。Laravel session には依存せず、token と unlock token は browser runtime state だけで扱う。WebGL renderer 初期化に失敗した場合でも、canvas / scene 操作だけを無効化し、API controls と list/detail は一覧モードとして継続動作する。

詳細は `docs/architecture/memory_space_screen.md` と `docs/decisions/0005-memory-space-screen.md` を正とする。

## Auth baseline

- `personal_access_tokens` table に sha256 hash 化した token を保存する。
- client が使う token は `id|plainTextToken` 形式にし、`Authorization: Bearer <token>` で送る。
- 初期実装では token 発行 API endpoint は置かず、管理画面モックアップ接続検証用 token は artisan command で発行する。
- `php artisan bunshin:issue-admin-token` は検証用 tenant / user を作成または再利用し、同名 token を revoke してから新 token を 1 回だけ表示する。
- `sanctum` guard は内部の Sanctum 相当 implementation として登録済み。後で Laravel Sanctum package に置き換える場合も route contract は `auth:sanctum` のまま維持する。

## SaaS / Auth readiness

現状の auth は API 接続検証用 baseline であり、SaaS として必要な user login / logout / token lifecycle / password reset / tenant onboarding / member role / billing gate は未実装。

不足機能と実装順は `docs/architecture/saas_auth_gap_analysis.md` を正とする。次の backend task は、token-first 方針のまま `POST /api/v1/auth/login` を追加し、email / password から短期または通常 token を発行できるようにすること。

## 管理画面モックアップ参照

管理画面用の静的モックアップを `docs/references/admin-ui-mockup/` に配置している。Codex automation が backend API を実装する際は、必要に応じて `index.html` と `app.js` の API client を参照し、管理画面が必要とする endpoint、field、filter、secret memory 導線を確認する。

この automation は、管理画面モックアップを実 API に繋ぐための最小限の HTML / JS 改修までを対象に含める。見た目刷新、画面構成の大幅変更、本格 frontend app 化は別 automation で扱う。

現在のモックアップは real API client へ接続済み。Settings で API Base URL と Bearer token を保存し、memories の list/detail/create/update/delete、categories の list/detail/create/update/delete、tags list、health を既存画面から確認する。

手動確認手順は `docs/references/admin-ui-mockup/manual-smoke-test.md` を正とする。local backend と静的 mockup server を起動し、`php artisan bunshin:issue-admin-token` で発行した Bearer token を Settings に貼って確認する。

## 非対象

- 管理画面モックアップの本格 frontend app 化または UI 再設計。
- 旧 Blade UI の再実装または復元。
- AI 生成・要約。
- 画像・音声アップロード。
- 複雑な共有権限。

## 次の実装 task

`POST /api/v1/auth/login` の backend baseline を追加する。

記憶の海 / 宇宙画面の初期 backend / frontend baseline と smoke は完了済み。管理画面は本格 frontend 化せず、category hierarchy の接続確認に必要な最小差分だけ扱う。
