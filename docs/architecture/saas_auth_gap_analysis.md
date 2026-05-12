# SaaS / Auth Gap Analysis

最終更新: 2026-05-09 15:56:06 JST

## 目的

分身AIバックエンドを個人検証用 API から SaaS として運用できる backend に近づけるため、現時点で不足している user login / account / tenant / token / SaaS 運用機能を洗い出し、実装 task に落とす。

## 現在実装済みの範囲

- `/api/v1` は token-first 方針で、protected route は `auth:sanctum` guard を使う。
- `personal_access_tokens` に sha256 hash 化した token を保存し、`Authorization: Bearer <token>` で protected API を認証する。
- `User::createApiToken()` は `id|plainTextToken` 形式の token を発行できる。
- `php artisan bunshin:issue-admin-token` は検証用 tenant / user / token を作成できる。
- local seed は `admin@example.test` / `password` / `local-dev-token` を作る。
- `tenants` と `users.tenant_id` は存在し、memory / category / tag は tenant / owner 境界で分離されている。
- `password_reset_tokens` table は Laravel 標準 migration として存在する。

## SaaS として不足している機能

### 認証 API

- user が email / password で token を発行する `POST /api/v1/auth/login` がない。
- login 済み user / tenant / token 状態を返す `GET /api/v1/auth/me` がない。
- current token を失効する `POST /api/v1/auth/logout` がない。
- token 一覧、個別 revoke、全端末 logout、token rotation の API がない。
- login / password reset / token 発行への rate limit と audit event がない。

### Account lifecycle

- 新規 tenant / 初期 owner user を作る onboarding flow がない。
- public signup にするか invite-only にするか未決。
- email verification の API / notification / resend flow がない。
- password reset request / reset confirm の API がない。
- password change、profile update、email change の API がない。
- account suspension / disabled user を認証時に拒否する状態がない。

### Tenant / member management

- tenant owner / admin / member などの role model がない。
- tenant member list / invite / accept / revoke / role update API がない。
- 現在は `users.tenant_id` で 1 user 1 tenant 前提。将来 1 user が複数 tenant に参加するなら membership table が必要。
- tenant settings、tenant slug 変更、tenant 削除 / archive 方針がない。

### SaaS operations

- subscription / plan / billing status による機能制限がない。
- usage limit、storage / memory count quota、plan entitlement の設計がない。
- audit log、security event log、admin impersonation 方針がない。
- privacy / data export / account deletion / tenant data deletion の backend flow がない。

## 実装順

初期 SaaS baseline は、token-first 方針を維持しながら「ログインして自分のデータを触れる」ための最小 API から入る。

1. `POST /api/v1/auth/login` を追加する。
   - email / password を検証する。
   - tenant 未所属、停止 user、email 未検証 user をどう扱うかは request validation / tests で明示する。
   - 成功時に plain token を 1 回だけ返し、user と tenant の最小 profile を返す。
2. `GET /api/v1/auth/me` と `POST /api/v1/auth/logout` を追加する。
   - `me` は authenticated user / tenant / current token metadata を返す。
   - `logout` は current token だけを revoke する。
3. token lifecycle API を追加する。
   - token 一覧、個別 revoke、全 token revoke、任意 token name / expires_at を扱う。
4. password reset API を追加する。
   - request / reset confirm の JSON API、throttle、test mail または notification fake を整える。
5. tenant onboarding 方針を決めて実装する。
   - 初期は invite-only 推奨。public signup にする場合は tenant 作成、owner user 作成、email verification、abuse 対策を同時に設計する。
6. role / member management を追加する。
   - 初期は `users.role` で owner / admin / member を持つか、将来拡張を見越して membership table にするかを決める。
7. subscription / quota / audit を追加する。
   - billing provider 接続前でも `plan_key` / `subscription_status` / quota check の domain baseline を先に置く。

## 次に実装する 1 task

`POST /api/v1/auth/login` の backend baseline を追加する。

完了条件:

- route / request / controller / resource または response shaping が追加される。
- valid email / password で token、user、tenant が返る。
- invalid credentials は `422` または `401` のどちらかに統一し、API contract に記録する。
- tenant 未所属 user の扱いを test で固定する。
- token は hash 保存され、plain text は response で 1 回だけ返る。
- `TokenAuthTest` または新規 `AuthApiTest` が login 成功 / 失敗 / protected API 利用まで検証する。
- `docs/architecture/api_contract.md` と OpenAPI に反映する。

## 人間判断が必要な論点

- public signup を許可するか、invite-only で始めるか。
- `users.tenant_id` による 1 user 1 tenant を当面維持するか、membership table に拡張するか。
- tenant role は `users.role` で始めるか、membership role として持つか。
- email verification 未完了 user に login token を発行するか。
- invalid credentials の response を security 優先で `401` にするか、validation 統一で `422` にするか。
- billing / subscription を MVP backend scope に含めるか、plan gate だけ先に置くか。
