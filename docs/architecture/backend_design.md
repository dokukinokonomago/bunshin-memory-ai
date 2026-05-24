# バックエンド設計

## 目的

分身AI の記憶データを、将来の対話・分析・人格生成に耐える形で保存、検索、権限管理できる API backend として作り直す。

## 初期スコープ

- API-first の JSON backend。
- 記憶、カテゴリ、タグを正規化して保存する。
- カテゴリは大カテゴリー / サブカテゴリーを `categories.parent_id` で階層化する。
- 年代はカテゴリー階層とは別軸として `period_key` / `occurred_on` に保持する。
- `tenant_id` と `owner_user_id` を基本境界にする。
- subscription / plan / billing status は初期 baseline として `tenants` に保存し、create 系 API で quota guard を通す。billing provider data model、checkout / customer portal endpoint、provider webhook handling、provider-local reconciliation command / runbook、tenant archive billing cancellation failure triage runbook、billing provider production env / frontend smoke runbook は実装済み。billing 方針は `docs/decisions/0029-billing-provider-integration.md` を正とし、tenant archive 時の provider cancellation / refund handling は `docs/decisions/0030-tenant-archive-billing-provider-cancellation.md`、dedicated retry command の v1 defer は `docs/decisions/0031-tenant-archive-billing-cancellation-retry-command.md`、automated billing adjustments の v1 defer は `docs/decisions/0032-automated-billing-adjustments-policy.md`、customer-visible dispute / refund request intake の v1 defer は `docs/decisions/0033-customer-billing-dispute-refund-request-flow.md` を正とする。
- security event / auth rate limit は初期 baseline として、認証系 write endpoint から `security_events` に記録し、named rate limiter で保護する。
- broader audit log は `docs/decisions/0026-broader-audit-log-admin-impersonation.md` を正とし、既存 `security_events` table を v1 audit sink として拡張済み。audit pruning 方針は `docs/decisions/0027-security-event-pruning-policy.md` を正とする。external logging/search integration 方針は `docs/decisions/0028-external-logging-search-integration.md` を正とし、初期実装は deferred。admin impersonation は初期 SaaS scope から除外する。
- email verification / resend は初期 baseline として Laravel の一時署名付き URL と token-first resend endpoint で扱う。
- account status は初期 baseline として `users.account_status` に保存し、`disabled` / `suspended` user の login と既存 Bearer token access を拒否する。
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
- `POST /api/v1/auth/signup`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/password/forgot`
- `POST /api/v1/auth/password/reset`
- `PUT /api/v1/auth/password`
- `GET /api/v1/auth/email/verify/{id}/{hash}`
- `POST /api/v1/auth/email/verification-notification`
- `PUT /api/v1/auth/email`
- `GET /api/v1/auth/email/change/verify/{id}/{hash}`
- `GET /api/v1/auth/me`
- `PATCH /api/v1/auth/profile`
- `POST /api/v1/auth/account/export`
- `DELETE /api/v1/auth/account`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/tokens`
- `DELETE /api/v1/auth/tokens/{token}`
- `POST /api/v1/auth/tokens/revoke-all`
- `POST /api/v1/auth/tokens/rotate`
- `GET /api/v1/tenant/members`
- `POST /api/v1/tenant/export`
- `POST /api/v1/tenant/archive`
- `POST /api/v1/billing/checkout-sessions`
- `POST /api/v1/billing/portal-sessions`
- `POST /api/v1/billing/webhooks/{provider}`
- `DELETE /api/v1/tenant/members/{member}`
- `PATCH /api/v1/tenant/members/{member}/role`
- `GET /api/v1/tenant/invitations`
- `POST /api/v1/tenant/invitations`
- `DELETE /api/v1/tenant/invitations/{invitation}`
- `POST /api/v1/tenant/invitations/accept`
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

`POST /api/v1/secret-unlocks` は実装済み。`users.secret_unlock_password` の専用 hash を検証し、15 分有効な user scoped token を発行する。account password hash は unlock 判定に使わない。unlock password 未設定 user は `422` を返す。token は `secret_unlock_tokens` に sha256 hash のみ保存し、plain text は response で 1 回だけ返す。

`PUT /api/v1/secret-unlock-password` は実装済み。初回 setup では account password を確認し、change では account password と現在の dedicated unlock password の両方を確認する。新 unlock password は account password と同じ値にできず、change では現在の unlock password と同じ値にもできない。成功時は既存 `secret_unlock_tokens` を削除する。recovery / forced rotation は別 endpoint として扱う。

secret unlock password recovery / forced rotation の contract は `docs/decisions/0019-secret-unlock-password-recovery-rotation.md` で決定済み。`POST /api/v1/secret-unlock-password/recovery/request` は実装済みで、Bearer token、tenant context、verified email、account password を確認し、30 分有効な signed recovery link を送る。成功時も `users.secret_unlock_password` と既存 `secret_unlock_tokens` は変更しない。`PUT /api/v1/secret-unlock-password/recovery/{id}/{hash}` も実装済みで、signed URL、same-user Bearer token、verified email、account password を確認して `users.secret_unlock_password` を更新し、既存 `secret_unlock_tokens` を削除する。`POST /api/v1/tenant/members/{member}/secret-unlock-password/force-rotation` も実装済みで、tenant member management policy の role boundary を使い、対象 user の unlock password hash を clear し、既存 unlock token を失効させる。secret 内容、temporary password、plain unlock token、対象 user の Bearer token は返却または revoke しない。

`GET /memory-space` は実装済み。Laravel / Vite asset として Three.js canvas を表示し、email / password login、API Base URL、Bearer token、period / category filter、descendant toggle、secret unlock modal、memory list / detail panel を持つ。Laravel session には依存せず、login token は `POST /api/v1/auth/login` で取得して既存 Bearer token input と同じ browser shared config に保存し、unlock token は runtime state だけで扱う。401 response では controls panel を開き、login status に認証失敗を表示する。WebGL renderer 初期化に失敗した場合でも、canvas / scene 操作だけを無効化し、API controls と list/detail は一覧モードとして継続動作する。

詳細は `docs/architecture/memory_space_screen.md` と `docs/decisions/0005-memory-space-screen.md` を正とする。

## Auth baseline

- `personal_access_tokens` table に sha256 hash 化した token を保存する。
- client が使う token は `id|plainTextToken` 形式にし、`Authorization: Bearer <token>` で送る。
- tenant onboarding は invite-only とする。`POST /api/v1/auth/signup` は server 側 `BUNSHIN_ONBOARDING_INVITE_TOKEN` と request の invite token が一致する場合だけ、tenant と initial owner user を同じ transaction で作成し、owner 用 Bearer token を発行する。public signup は初期 baseline では採用しない。
- tenant role は初期 baseline では `users.role` に保存する。role は `owner`, `admin`, `member` の 3 種で、default は `member`。1 user 1 tenant 前提を維持し、membership table は複数 tenant 参加が必要になった段階で追加する。
- signup で作る initial user、local seed user、`bunshin:issue-admin-token` の default user は `owner` とする。
- account status は `users.account_status` に保存する。default は `active`。`disabled` / `suspended` user は login token を発行せず、既存 Bearer token も `auth:sanctum` guard で認証しない。guard はこの拒否時に `last_used_at` を更新しない。
- tenant member 管理用の最小 guard として `manage-tenant-members` Gate を定義する。`owner` / `admin` は自 tenant に対して許可され、`member` または別 tenant は拒否される。現行の memories / categories は引き続き owner-scoped data API として role では広げない。
- tenant member invite / accept / revoke / role update API は実装済み。招待 token は `tenant_member_invitations.token_hash` に sha256 hash として保存し、plain `inv_...|plainTextToken` は作成 response で 1 回だけ返す。初期 TTL は 7 日。accept は legacy numeric `id|plainTextToken` も v1 transition 互換として受け付ける。accept は新規 user 作成のみ対応し、既存 user 紐付けは別 task とする。
- `owner` / `admin` は member list / invitation list / invite / pending invitation revoke / member role update / member revoke ができる。`admin` は `owner` role の付与や既存 owner の管理はできない。manager 自身の role 変更 / revoke は拒否する。
- member revoke は user row を削除せず、対象 user の `tenant_id` を `null`、`role` を `member` に戻し、対象 user の Bearer token を削除する。
- `POST /api/v1/auth/login` で email / password から user login 用 token を発行する。
- email verification 未完了 user への login token 発行は当面許可する。tenant 設定、member invitation、billing などの危険操作は後続 task で verified 必須化する。
- `POST /api/v1/auth/password/forgot` で password reset link を request する。email 存在有無を区別せず `202` を返す。
- `POST /api/v1/auth/password/reset` で reset token を検証し password を更新する。成功時は password reset token と既存 Bearer token を削除する。
- `PUT /api/v1/auth/password` で現在の account password を検証し、account password を変更する。成功時は current token を含む対象 user の Bearer token を全て削除し、再 login を必要にする。secret unlock password は変更しない。
- email verification / resend flow は実装済み。signup / tenant invitation accept 後に Laravel `VerifyEmail` notification を送る。verification URL は `GET /api/v1/auth/email/verify/{id}/{hash}` の一時署名付き URL とし、Bearer token は不要。resend は `POST /api/v1/auth/email/verification-notification` で authenticated user / tenant context を確認してから送る。
- email change flow は実装済み。`PUT /api/v1/auth/email` で authenticated user の変更先 email を `users.pending_email` に保存し、変更先 email へ signed verification link を送る。`GET /api/v1/auth/email/change/verify/{id}/{hash}` が valid な場合だけ `users.email` を切り替え、`pending_email` を消し、`email_verified_at` を更新する。request 時と verification 完了時の両方で、他 user の current email / pending email との重複を拒否する。
- `GET /api/v1/auth/me` で authenticated user / tenant / current token metadata を返す。
- `PATCH /api/v1/auth/profile` で authenticated user の `name` を更新する。email はこの endpoint では変更せず、email change flow で扱う。成功時も Bearer token は維持する。
- account export / deletion 方針は `docs/decisions/0023-account-deletion-export.md` で決定済み。self-service export は `POST /api/v1/auth/account/export` として実装済みで、current account password と account lifecycle rate limit を要求する。secret memory 本文 / tag / metadata は `include_secret=true` と valid `X-Secret-Unlock` が揃う場合だけ返す。self-service deletion は `DELETE /api/v1/auth/account` として実装済みで、last active owner を拒否し、user row を匿名化して Bearer token / secret unlock token / owned memories / categories を処理する。tenant manager による他 user account 削除と tenant-wide memory content export は初期 scope 外。
- `POST /api/v1/auth/logout` で current token だけを削除する。
- `GET /api/v1/auth/tokens` で current user が所有する token metadata 一覧を返す。plain token / token hash は返さない。
- `DELETE /api/v1/auth/tokens/{token}` で current user が所有する token を 1 件削除する。他 user の token は `404`。
- `POST /api/v1/auth/tokens/revoke-all` で current user の全 token を削除する。同じ tenant の別 user token は削除しない。
- `POST /api/v1/auth/tokens/rotate` で current token の `name` / `abilities` / `expires_at` を引き継いだ新 token を発行し、旧 token を削除する。新 plain token は response で 1 回だけ返す。
- `security_events` は login、signup、password reset request / complete、password change、tenant invitation accept、email verification request / complete、email change request / complete、secret unlock password recovery request / complete、secret unlock password forced rotation、account status change、account export request、account deletion、tenant export request、tenant archive、tenant purge、billing checkout session create、billing portal session create、billing webhook sync、billing reconciliation、billing subscription cancel request の success / failure / requested / skipped を保存する。`billing.subscription_cancel.request` は provider key、archive cancellation policy、result、reason、previous local plan/status、changed local field names だけを保存し、provider id 類や raw provider response は保存しない。`auth.tenant.purge` は scrub-safe count だけを success metadata に残す。plain password、plain token、invite token、secret unlock token、export bundle、secret memory content、hosted billing URL は保存しない。account status による login 拒否は `auth.login` failure として `metadata.reason=account_not_active` と `metadata.account_status` を残す。
- Broader audit は同じ `security_events` table に実装済み。対象は token lifecycle、tenant member management、profile update、secret unlock password setup/change、memory create/update/delete、category create/update/delete の successful write。metadata は public id と scrub-safe scalar/count だけにし、memory title/body、category/tag names、secret content、raw request payload、raw validation error は保存しない。初期 retention target は 180 日。tenant purge 後は decision 0025 の scrub 方針を優先し、audit pruning は decision 0027 に従って active / null-tenant event は `created_at` から 180 日、purged tenant event は `purged_at` から 180 日を基準に削除する。外部 logging/search は decision 0028 に従い、初期実装せず、必要になった場合だけ sanitized projection の別 pipeline として追加する。
- Admin impersonation は初期 SaaS scope に含めない。tenant owner/admin は管理 endpoint を通して member を管理できるが、他 user として token を発行したり、他 user の private/secret memory をその user として読むことはできない。
- `POST /api/v1/auth/signup`、`POST /api/v1/auth/login`、`POST /api/v1/auth/password/forgot`、`POST /api/v1/auth/password/reset`、`PUT /api/v1/auth/password`、`POST /api/v1/tenant/invitations/accept`、`POST /api/v1/auth/email/verification-notification`、`PUT /api/v1/auth/email`、`POST /api/v1/auth/account/export`、`DELETE /api/v1/auth/account` は named rate limiter を通す。`POST /api/v1/tenant/export` と `POST /api/v1/tenant/archive` は tenant lifecycle rate limiter、`POST /api/v1/billing/checkout-sessions` と `POST /api/v1/billing/portal-sessions` は billing rate limiter を通す。超過時は `429 Too Many Requests`。
- 管理画面モックアップ接続検証用 token は引き続き artisan command でも発行できる。
- `php artisan bunshin:issue-admin-token` は検証用 tenant / user を作成または再利用し、同名 token を revoke してから新 token を 1 回だけ表示する。
- `sanctum` guard は内部の Sanctum 相当 implementation として登録済み。後で Laravel Sanctum package に置き換える場合も route contract は `auth:sanctum` のまま維持する。

## Public ID request lookup

prefixed ULID public id response baseline は実装済み。request lookup migration は `docs/decisions/0020-public-id-request-lookup.md` を正とする。

- 内部 DB relation は integer primary key / FK を維持する。
- 新規 client request は `mem_01...` / `cat_01...` / `usr_01...` / `inv_01...` の public id を使う。integer id は v1 transition 中の互換値であり、新規 frontend state には保存しない。
- memories / categories route param は context-scoped public id resolver を通し、context 外、wrong prefix、malformed、missing は `404` とする。
- `category_id` / `parent_id` request field は v1 の field 名を維持しながら、値は `cat_01...` を正とする。validation で内部 category id へ変換してから controller / model に渡す。
- list filter の category が context 外なら空 result / aggregate を返し、write payload の category が context 外なら field-level `422` を返す。
- tenant member route param は `usr_01...` を正とする。tenant invitation route param は `inv_01...` を正とする。どちらも numeric id は v1 transition 互換としてだけ受け付ける。signed auth/recovery URLs は今回の public id lookup migration から除外する。
- 管理画面モックアップと memory-space frontend は integer `id` ではなく public id fields を option value / dataset / route param / request payload に使うよう移行済み。

## Subscription / quota baseline

- billing provider 接続前の初期 baseline として、`tenants.plan_key` / `subscription_status` / `trial_ends_at` / `subscription_ends_at` を持つ。
- active plan は `active` / `trialing` かつ期限切れでない tenant とする。`past_due` / `canceled` / `incomplete` は create 系 API を止める。
- plan limits は `config/bunshin.php` の `bunshin.plans` に置く。初期値は `free` が `memories=1000` / `categories=100`、`pro` が unlimited。
- `TenantQuotaGuard` は `POST /api/v1/memories` と `POST /api/v1/categories` の作成直前に active plan と quota を確認する。
- inactive subscription は `402 Payment Required`、quota 超過は `422 Unprocessable Entity` を返す。
- quota count は tenant-wide。memories は soft deleted row を除外し、categories は tenant 内 category row 数を数える。
- billing provider integration は design 済みで、verified provider webhook が paid subscription state を local tenant fields に同期する。API runtime は同期後の local DB を source of truth とし、write request ごとに provider API を呼ばない。
- billing schema は `tenants.billing_provider` / `billing_customer_id` / `billing_subscription_id` / `billing_price_id` / `billing_cancel_at_period_end` / `billing_last_synced_at` と `billing_webhook_events` として実装済み。checkout / customer portal endpoint は owner-only、verified email、billing rate limit を要求し、hosted provider URL を返す。checkout は provider customer id を lazy create して保存できるが、local `plan_key` / `subscription_status` は verified webhook まで変更しない。provider webhook endpoint は Bearer auth 外の public endpoint として provider signature verification を必須にし、duplicate provider event を idempotent no-op として扱う。known price mapping がある checkout completion / subscription webhook だけ local plan state に反映し、unknown tenant / customer / subscription / price id は paid entitlement を grant しない。`bunshin:reconcile-billing-provider` は internal operator command として default dry-run で provider-local drift を検出し、`--apply` 明示時だけ既知 customer / subscription / price mapping / status に限定して local tenant billing fields を同期する。production env / frontend handoff smoke は `docs/operations/billing_provider_production_smoke_runbook.md` に従う。tenant archive は local archive transaction commit 後に linked provider subscription の immediate cancellation を試行し、success / skipped / requires operator review を `billing.subscription_cancel.request` に記録する。cancellation failure triage は `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md` に従う。dedicated retry command は v1 では実装せず、頻発 failure / batch retry / safer support tooling / compliance 要件が具体化した場合だけ decision 0031 の制約で再検討する。raw webhook payload、raw provider response、card data、billing address、checkout / portal URL、signature secret、provider API key、provider customer id、provider subscription id、provider price id は logs / `security_events` に保存しない。

## SaaS / Auth readiness

現状の auth は API 接続検証用 baseline に invite-only tenant onboarding、tenant role、tenant member invite / accept / delivery notification / revoke / role update / account status update、user login、account status による disabled / suspended user の認証拒否、password reset、account password change、profile update、email verification / resend、email change、current session 取得、current token logout、token lifecycle API、subscription / plan / quota guard、security event / auth rate limit baseline、broader audit event baseline、secret unlock password setup / change API、secret unlock password recovery request / completion API、secret unlock password forced rotation API、prefixed ULID public id response baseline、memories / categories public id request lookup migration、tenant member route public id lookup、tenant member invitation `inv_` public id lookup、self-service account export endpoint、self-service account deletion endpoint、tenant-wide export endpoint、tenant archive lifecycle fields、archived-tenant auth rejection、tenant archive endpoint、tenant purge command、tenant purge scheduler / runbook、audit log pruning command / scheduler / runbook、billing provider data model、billing checkout / customer portal API、billing webhook receiver、provider-local reconciliation command / runbook、billing provider production env / frontend smoke runbook を追加済み。external logging/search integration は設計済みで初期実装は deferred。tenant archive の方針は `docs/decisions/0024-tenant-export-deletion-archive.md`、tenant purge retention policy は `docs/decisions/0025-tenant-purge-retention-policy.md` で決定済み。broader audit / admin impersonation 方針と実装は `docs/decisions/0026-broader-audit-log-admin-impersonation.md`、audit log pruning 方針は `docs/decisions/0027-security-event-pruning-policy.md`、external logging/search 方針は `docs/decisions/0028-external-logging-search-integration.md`、billing provider 方針は `docs/decisions/0029-billing-provider-integration.md` を正とする。

不足機能と実装順は `docs/architecture/saas_auth_gap_analysis.md` を正とする。account status 変更 API の管理画面モックアップ接続要否は確認済みで、現行 mockup に tenant members view / account status 操作導線がないため接続改修は不要。

## Internal jobs

- Implemented `php artisan bunshin:purge-archived-tenants`
  - Public API endpoint は追加しない。
  - Eligibility は `archived_at is not null`、`scheduled_deletion_at <= now()`、`purged_at is null`。
  - Options は `--dry-run`、`--limit`、任意 argument の single-tenant targeting by tenant public id or slug。
  - `scheduled_deletion_at` を retention source of truth とし、archive からの日数を再計算しない。
  - tenant row は tombstone として保持し、memory content / categories / tags / invitations / credentials / user PII を削除または匿名化する。
  - pre-existing tenant `security_events` は subject email、IP address、user agent、raw metadata を scrub し、event type / outcome / timestamps / FK だけを残す。
  - implementation は tenant row lock、batch processing、idempotent retry、per-tenant failure isolation、`auth.tenant.purge` logging を持つ。
  - Scheduler は `routes/console.php` で日次 `03:30 UTC`、default `--limit=50`、`withoutOverlapping(120)`、`onOneServer()` として登録済み。
  - scheduled run は `BUNSHIN_TENANT_PURGE_SCHEDULE_ENABLED` で制御し、default は production のみ有効。`BUNSHIN_OPERATIONS_ALERT_EMAIL` がある場合は failure output を email する。
  - production runbook は `docs/operations/tenant_purge_runbook.md` を正とする。
- Implemented `php artisan bunshin:prune-security-events`
  - Public API endpoint は追加しない。
  - Default retention は 180 日で、`BUNSHIN_SECURITY_EVENT_RETENTION_DAYS` から設定する。
  - null-tenant event と non-purged tenant event は `created_at` が cutoff より古い場合に削除対象にする。
  - purged tenant event は scrub 済み pre-existing rows と `auth.tenant.purge` rows を含め、tenant `purged_at` が cutoff より古い場合に削除対象にする。
  - Options は `--dry-run`、`--limit`、任意 argument の tenant public id or slug targeting。
  - `--limit` は default 5000、1-50000 の範囲だけ許可する。retention days は 30-3650 の範囲だけ許可する。
  - dry-run / failure output に subject email、IP、user agent、raw metadata、secret content、token material は出さない。
  - Scheduler は `routes/console.php` で日次 `04:15 UTC`、default `--limit=5000`、`withoutOverlapping(120)`、`onOneServer()` として登録済み。
  - scheduled run は `BUNSHIN_SECURITY_EVENT_PRUNE_SCHEDULE_ENABLED` で制御し、default は production のみ有効。`BUNSHIN_OPERATIONS_ALERT_EMAIL` がある場合は failure output を email する。
  - scheduled output は default で `storage/logs/security-event-prune-schedule.log` に append する。
  - operations runbook は `docs/operations/security_event_pruning_runbook.md` を正とする。
- Implemented `php artisan bunshin:reconcile-billing-provider`
  - Public API endpoint は追加しない。
  - Default は dry-run で、provider subscription state と local tenant billing fields の drift を報告し、local rows は変更しない。
  - Options は `--apply`、`--limit`、任意 argument の tenant public id or slug targeting。
  - `--apply` は非 archived tenant、matching customer、known subscription、known price mapping、known provider status の場合だけ local tenant billing fields を同期する。
  - tenants with billing customer but no local subscription id は provider 側に subscription が 1 件だけある場合だけ対象にする。複数件は ambiguous として skip する。
  - unknown tenant / customer / subscription / price / status、archived tenant、provider request failure は paid entitlement を grant しない。
  - Apply mode は scrub-safe `billing.reconciliation` security event を書く。metadata は provider、mode、result、local plan/status、changed field names だけにし、provider customer id、subscription id、price id、raw provider response、provider secret は保存しない。
  - operations runbook は `docs/operations/billing_provider_reconciliation_runbook.md` を正とする。

## 管理画面モックアップ参照

管理画面用の静的モックアップを `docs/references/admin-ui-mockup/` に配置している。Codex automation が backend API を実装する際は、必要に応じて `index.html` と `app.js` の API client を参照し、管理画面が必要とする endpoint、field、filter、secret memory 導線を確認する。

この automation は、管理画面モックアップを実 API に繋ぐための最小限の HTML / JS 改修までを対象に含める。見た目刷新、画面構成の大幅変更、本格 frontend app 化は別 automation で扱う。

現在のモックアップは real API client へ接続済み。Settings で API Base URL と Bearer token を保存し、memories の list/detail/create/update/delete、categories の list/detail/create/update/delete、tags list、health を既存画面から確認する。tenant members view は現行 mockup に存在しないため、account status 変更 API は今の接続対象に含めない。

手動確認手順は `docs/references/admin-ui-mockup/manual-smoke-test.md` を正とする。local backend と静的 mockup server を起動し、`php artisan bunshin:issue-admin-token` で発行した Bearer token を Settings に貼って確認する。

## 非対象

- 管理画面モックアップの本格 frontend app 化または UI 再設計。
- 旧 Blade UI の再実装または復元。
- AI 生成・要約。
- 画像・音声アップロード。
- 複雑な共有権限。

## 次の実装 task

tenant archive billing cancellation failure triage の operations runbook は追加済み。automated billing adjustments policy は decision 0032 で v1 deferred として整理済み。customer-visible billing dispute / refund request flow は decision 0033 で v1 deferred / support-only outside product backend として整理済み。billing checkout / customer portal API の success / cancel / return URL を将来の product frontend に接続する前提は decision 0034 で整理済み。billing provider production env / frontend smoke checklist は `docs/operations/billing_provider_production_smoke_runbook.md` として整理済み。tenant archive billing provider cancellation handling、tenant archive provider cancellation / refund handling 方針、provider-local reconciliation command / operations runbook、billing webhook receiver と signature verification / idempotency tests、billing checkout / customer portal API、billing provider data model、billing provider integration scope と webhook handling、external logging/search integration、`bunshin:prune-security-events` command、tests、scheduler、operations runbook、account status 変更 API の管理画面モックアップ接続要否確認、tenant member invitation delivery email / notification、broader audit logging、tenant purge command の scheduler 登録、production alerting、manual runbook、audit log pruning 方針設計は完了済み。記憶の海 / 宇宙画面の初期 backend / frontend baseline と smoke は完了済み。次は production billing frontend smoke checklist に沿った実環境確認、または次の backend 小粒 task の選定から開始する。管理画面は本格 frontend 化せず、必要な API 接続確認の最小差分だけ扱う。
