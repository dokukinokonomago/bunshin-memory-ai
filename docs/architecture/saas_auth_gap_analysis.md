# SaaS / Auth Gap Analysis

最終更新: 2026-05-17 09:02:17 JST

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
- `POST /api/v1/auth/login` は email / password から `personal_access_tokens` の Bearer token を発行する。invalid credentials は `401`、tenant 未所属 user は `403`。
- `GET /api/v1/auth/me` は authenticated user / tenant / current token metadata を返す。tenant 未所属 authenticated user は `403`。
- `POST /api/v1/auth/logout` は current token だけを削除する。
- `GET /api/v1/auth/tokens`、`DELETE /api/v1/auth/tokens/{token}`、`POST /api/v1/auth/tokens/revoke-all`、`POST /api/v1/auth/tokens/rotate` は current user の token lifecycle baseline を扱う。
- `POST /api/v1/auth/password/forgot` は password reset link を request し、存在しない email でも同じ `202` を返す。
- `POST /api/v1/auth/password/reset` は reset token を検証して password を更新し、対象 user の既存 Bearer token を revoke する。
- `PUT /api/v1/auth/password` は現在の account password を検証して password を更新し、対象 user の既存 Bearer token を全て revoke する。secret unlock password は変更しない。
- tenant onboarding 方針は invite-only に固定済み。`POST /api/v1/auth/signup` は `BUNSHIN_ONBOARDING_INVITE_TOKEN` に一致する invite token がある場合だけ、tenant / initial owner user / signup token を同じ transaction で作成する。public signup は初期 baseline では採用しない。
- tenant role 方針は `users.role` baseline に固定済み。role は `owner`, `admin`, `member` で、default は `member`。initial owner signup、local seed、admin token command の default user は `owner`。`manage-tenant-members` Gate は自 tenant の `owner` / `admin` だけを許可する。
- tenant member management baseline は実装済み。`tenant_member_invitations` に `inv_` public id と sha256 hash 化した invite token を保存し、`GET /api/v1/tenant/members`、`GET /api/v1/tenant/invitations`、`POST /api/v1/tenant/invitations`、`POST /api/v1/tenant/invitations/accept`、`DELETE /api/v1/tenant/invitations/{invitation}`、`PATCH /api/v1/tenant/members/{member}/role`、`DELETE /api/v1/tenant/members/{member}` を提供する。
- subscription / plan / billing status baseline は実装済み。`tenants.plan_key` / `subscription_status` / `trial_ends_at` / `subscription_ends_at` を持ち、`POST /api/v1/memories` と `POST /api/v1/categories` は active plan と tenant-wide quota を確認する。
- security event / auth rate limit baseline は実装済み。`security_events` に login / signup / password reset / password change / tenant invitation accept / email verification / email change / secret unlock password recovery / forced rotation の最小 event を保存し、auth write endpoint と tenant security action は named rate limiter で保護する。
- broader audit log / admin impersonation 方針は `docs/decisions/0026-broader-audit-log-admin-impersonation.md` で決定済み。初期 broader audit は既存 `security_events` table を v1 audit sink として拡張済みで、token lifecycle、tenant member management、profile / secret unlock password changes、memory/category writes の成功 event を保存する。admin impersonation は初期 SaaS scope から明示的に除外する。
- email verification / resend flow は実装済み。signup / tenant invitation accept 後に `VerifyEmail` notification を送り、`GET /api/v1/auth/email/verify/{id}/{hash}` の一時署名付き URL で verified にする。resend は protected `POST /api/v1/auth/email/verification-notification` を使う。
- email change flow は実装済み。protected `PUT /api/v1/auth/email` で変更先を `users.pending_email` に保存し、変更先 email へ signed verification link を送る。`GET /api/v1/auth/email/change/verify/{id}/{hash}` 完了時だけ current email を切り替える。
- account status による認証拒否 baseline は実装済み。`users.account_status` は `active`, `disabled`, `suspended` を持ち、`disabled` / `suspended` user は login token 発行と既存 Bearer token access を拒否する。
- account deletion / export scope は `docs/decisions/0023-account-deletion-export.md` で設計済み。self-service account export endpoint と self-service account deletion endpoint は実装済み。
- tenant-wide export と tenant archive 方針は `docs/decisions/0024-tenant-export-deletion-archive.md` で設計済み。tenant-wide export は owner-only で他 user の private / secret memory content を返さない。tenant archive endpoint は archive-first で実装済み。
- tenant purge retention policy は `docs/decisions/0025-tenant-purge-retention-policy.md` で設計済み。`php artisan bunshin:purge-archived-tenants` は実装済みで、public API ではなく internal command として、retention window 後に memory content / credentials / user PII を削除または匿名化し、tenant tombstone と scrubbed audit records だけを残す。Scheduler 登録、failure alert email hook、manual runbook も実装済み。
- audit log pruning command retention / execution 方針は `docs/decisions/0027-security-event-pruning-policy.md` で決定済み。active / null-tenant events は `created_at` から 180 日、purged tenant events は `purged_at` から 180 日を基準に `security_events` を削除する。`bunshin:prune-security-events` command、tests、scheduler 登録、operations runbook は実装済み。
- external logging/search integration 方針は `docs/decisions/0028-external-logging-search-integration.md` で設計済み。初期実装は deferred とし、長期 audit search / analytics / compliance archive / support investigation が具体要件になった場合だけ、sanitized projection として別 pipeline を追加する。primary DB の `security_events` pruning は decision 0027 のまま維持する。
- billing provider integration scope と webhook handling は `docs/decisions/0029-billing-provider-integration.md` で設計済み。billing provider data model、checkout / customer portal API、provider webhook receiver、provider-local reconciliation command / operations runbook、tenant archive billing cancellation failure triage runbook、production env / frontend smoke runbook は実装済み。dedicated tenant archive billing cancellation retry command は decision 0031 により v1 deferred。local tenant fields を API runtime source of truth、verified provider webhook と明示 operator reconciliation apply を paid subscription state sync の source of truth とする。
- tenant member invitation delivery email / notification は実装済み。`POST /api/v1/tenant/invitations` は plain invite token を response で 1 回だけ返し、同じ token を invitee email へ mail notification で送る。DB / audit metadata / list response には plain token を保存しない。
- smoke test 作成 data の参照有無再確認は 2026-05-16 に完了済み。local SQLite に対象 data は残っておらず、削除対象 0 件のため DB delete は実行していない。

## SaaS として不足している機能

### 認証 API

- token lifecycle / member management / data write 系の broader audit event は実装済み。

### Account lifecycle

- password change / profile update / email change API は実装済み。
- account suspension / disabled user の認証拒否 baseline と status 変更 API / reactivation policy は実装済み。

### Tenant / member management

- 現在は `users.tenant_id` で 1 user 1 tenant 前提。将来 1 user が複数 tenant に参加するなら membership table が必要。
- tenant settings、tenant slug 変更がない。

### SaaS operations

- billing provider 連携の scope / webhook handling は設計済み。customer id / subscription id schema、webhook event storage、checkout / portal endpoint、webhook receiver、provider-local reconciliation command / runbook、tenant archive billing cancellation failure triage runbook、production env / frontend smoke runbook は実装済み。dedicated retry command は v1 deferred。
- storage quota、plan entitlement の詳細設計は未実装。現状は memory count / category count の最小 quota baseline のみ。
- broader audit log / admin impersonation 方針は決定済みで、broader audit event は実装済み。
- self-service account export / deletion の backend flow は実装済み。
- tenant purge command、scheduler 登録、production alerting / runbook は実装済み。
- audit log pruning retention / execution 方針は決定済み。`bunshin:prune-security-events` command、tests、scheduler、operations runbook は実装済み。
- external logging/search integration は設計済みで、実装は deferred。

## 実装順

初期 SaaS baseline は、token-first 方針を維持しながら「ログインして自分のデータを触れる」ための最小 API から入る。

1. `POST /api/v1/auth/login` を追加する。完了済み。
   - email / password を検証する。
   - tenant 未所属 user は `403` として token を発行しない。email 未検証 user への login token 発行は当面許可し、危険操作は後続 task で verified 必須化する。
   - 成功時に plain token を 1 回だけ返し、user と tenant の最小 profile を返す。
2. `GET /api/v1/auth/me` と `POST /api/v1/auth/logout` を追加する。完了済み。
   - `me` は authenticated user / tenant / current token metadata を返す。
   - `logout` は current token だけを revoke する。
3. token lifecycle API を追加する。完了済み。
   - token 一覧、個別 revoke、全 token revoke、current token rotation を扱う。
4. password reset API を追加する。完了済み。
   - request / reset confirm の JSON API、throttle、test mail または notification fake を整える。
5. tenant onboarding 方針を決めて実装する。完了済み。
   - 初期 baseline は invite-only。public signup は採用せず、server 側 invite token がある場合だけ initial owner signup を許可する。
6. role / member management を追加する。完了済み。
   - 初期は `users.role` で owner / admin / member を持つ。membership table は複数 tenant 参加が必要になった段階で追加する。
   - tenant member list / invite / accept / revoke / role update API と invitation delivery notification は実装済み。
7. subscription / quota / audit を追加する。
   - billing provider 接続前でも `plan_key` / `subscription_status` / quota check の domain baseline を先に置く。完了済み。
   - audit log / security event log / login rate limit を追加する。完了済み。
8. email verification / resend flow を追加する。
   - login token 発行は email 未検証でも当面許可したまま、verification notification / verify / resend API を先に追加する。危険操作の verified 必須化は後続 task で扱う。完了済み。
9. secret unlock password を専用 password へ分離する。
   - 2026-05-13 の product policy decision に従い、account password 共用 baseline から `users.secret_unlock_password` の専用 hash 検証へ移行する。完了済み。
   - 専用 unlock password の setup / change API は完了済み。account password を常に確認し、変更時は current unlock password も確認する。
   - 専用 unlock password の recovery / forced rotation API contract は `docs/decisions/0019-secret-unlock-password-recovery-rotation.md` で決定済み。recovery request / completion endpoint と tenant member forced rotation endpoint は実装済み。
10. account lifecycle API を追加する。
   - password change、profile update、email change、account status による disabled / suspended user の認証拒否 baseline は完了済み。
   - account status 変更 API / reactivation policy は実装済み。
   - self-service account export endpoint と self-service account deletion endpoint は実装済み。
   - tenant-wide export endpoint、tenant archive lifecycle fields、archived-tenant auth rejection、tenant archive endpoint は実装済み。
   - tenant purge retention policy、tenant purge command、command tests、scheduler 登録、production alerting / runbook は実装済み。

## 直近で完了した task

billing provider production env / frontend smoke checklist を `docs/operations/billing_provider_production_smoke_runbook.md` として整理した。runbook は `BUNSHIN_BILLING_ENABLED`、provider、Stripe secret / webhook secret / API base URL、price mapping、checkout success / cancel URL、portal return URL の確認順、owner verified account による checkout / portal session smoke、frontend success / cancel / portal return route smoke、verified webhook smoke、reconciliation fallback、DB / logs / `security_events` の scrub verification を定義する。local tenant fields は引き続き API runtime source of truth で、paid subscription state sync は verified provider webhook と明示 operator reconciliation `--apply` のみ。checkout success / cancel / portal return は UX handoff であり、plan mutation や entitlement grant には使わない。billing provider data model、checkout / customer portal API、billing webhook receiver、provider-local reconciliation command / operations runbook、tenant archive billing provider cancellation handling、tenant archive billing cancellation failure triage runbook も実装済み。tenant archive billing provider cancellation / refund handling は `docs/decisions/0030-tenant-archive-billing-provider-cancellation.md`、dedicated retry command の v1 defer は `docs/decisions/0031-tenant-archive-billing-cancellation-retry-command.md`、automated billing adjustments の v1 defer は `docs/decisions/0032-automated-billing-adjustments-policy.md`、customer-visible billing dispute / refund request flow の v1 defer は `docs/decisions/0033-customer-billing-dispute-refund-request-flow.md`、billing frontend redirect URL handoff は `docs/decisions/0034-billing-frontend-redirect-url-handoff.md` を正とする。

## 次に実装する 1 task

production billing frontend smoke checklist に沿った実環境確認、または次の backend 小粒 task の選定。

後続 candidate:

- customer-visible billing dispute / refund request flow は decision 0033 により v1 deferred。product / finance / legal policy が具体化した段階で再検討する。
- 現行 setup / change endpoint には recovery / forced rotation を含めない。

## 人間判断が必要な論点

2026-05-13 21:33 JST にユーザーが推奨方針どおり採用することを承認済み。詳細は `docs/decisions/0011-product-policy-decisions.md` を正とする。

- `users.tenant_id` による 1 user 1 tenant は当面維持する。複数 tenant 参加、既存 user 招待受諾、account merge が必要になった段階で membership table に拡張する。
- tenant member invitation delivery は mail notification 化済み。response で plain token を 1 回返す fallback は local testing / operator verification 用に維持する。
- public signup は invite-only を継続する。
- email verification 未完了 user への login token 発行は当面許可し、tenant 設定、member invitation、billing などの危険操作は後続 task で verified 必須化する。
- invalid credentials response は `401 Unauthorized` を維持する。
- billing provider integration scope / webhook handling、provider-neutral schema、checkout / portal endpoint、webhook receiver、provider-local reconciliation command / runbook、tenant archive provider cancellation / refund handling 方針、tenant archive billing cancellation failure triage runbook は実装または設計済み。dedicated retry command は decision 0031 により v1 deferred。自動 refund / credit / proration / invoice finalization / dunning / dispute / period-end cancellation は decision 0032 により v1 deferred。customer-visible billing dispute / refund request flow は decision 0033 により v1 deferred。future implementation には product / finance / legal policy が必要。
