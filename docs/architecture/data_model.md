# データモデル設計

## 基本方針

旧実装の `period`, `content`, `emotion`, `tags` 直書き構造から、検索と権限管理に耐える正規化モデルへ移行する。

## tables

### tenants

- `id`
- `public_id`: `ten_` + ULID。external API / frontend payload 用の stable public id。
- `name`
- `slug`
- `plan_key`: initial values `free`, `pro`
- `subscription_status`: `active`, `trialing`, `past_due`, `canceled`, `incomplete`
- `trial_ends_at`
- `subscription_ends_at`
- `archived_at`: nullable datetime。tenant archive が成立した時刻。
- `archived_by_user_id`: nullable FK to users。tenant archive を request した owner user。
- `archive_reason`: nullable string。tenant archive request の任意理由。secret memory content や credential は保存しない。
- `deletion_requested_at`: nullable datetime。tenant archive と同時に tenant deletion schedule を作成した時刻。
- `scheduled_deletion_at`: nullable datetime。tenant purge を実行可能にする予定時刻。初期方針は archive から 30 日後。
- `purged_at`: nullable datetime。tenant purge 完了時刻。初期 archive endpoint では設定しない。
- `created_at`
- `updated_at`

subscription / billing provider 連携前の初期 baseline では plan と subscription status は `tenants` に直接持たせる。active plan は `subscription_status in (active, trialing)` かつ `subscription_ends_at` が未設定または未来、`trialing` の場合は `trial_ends_at` が未設定または未来として判定する。plan quota は `config/bunshin.php` の `bunshin.plans` に置き、初期値は `free` が `memories=1000`, `categories=100`、`pro` が unlimited。billing provider integration と webhook handling の方針は `docs/decisions/0029-billing-provider-integration.md` を正とする。

billing provider schema は実装済みで、`billing_provider`、`billing_customer_id`、`billing_subscription_id`、`billing_price_id`、`billing_cancel_at_period_end`、`billing_last_synced_at` を `tenants` に持つ。provider id は public tenant payload へ default では返さない。checkout endpoint は provider customer id を lazy create して `billing_provider` / `billing_customer_id` を保存できるが、local `plan_key` / `subscription_status` / `trial_ends_at` / `subscription_ends_at` は API runtime の source of truth として維持し、verified provider webhook と明示 operator reconciliation apply が paid subscription state を local fields へ同期する。free plan は provider customer id なしで扱う。

provider-local reconciliation は `php artisan bunshin:reconcile-billing-provider` として実装済み。default dry-run は local rows を変更せず drift を報告する。`--apply` は非 archived tenant、matching customer、known subscription、known price mapping、known provider status の場合だけ `tenants` の billing fields を更新する。unknown reference / price / status、archived tenant、ambiguous provider subscription は paid entitlement を grant しない。apply mode の `billing.reconciliation` security event は provider、mode、result、local plan/status、changed field names だけを保存し、provider customer id / subscription id / price id / raw provider response / provider API key は保存しない。

tenant archive 時の provider cancellation handling は `docs/decisions/0030-tenant-archive-billing-provider-cancellation.md` を正とし、実装済み。archive は local-first のまま維持し、provider cancellation は archive transaction 後の side effect として扱う。provider failure は local archive を rollback / reactivate しない。実装は linked provider subscription の即時 cancellation のみを扱い、自動 refund、credit、proration、invoice finalization、period-end cancellation は扱わない。automated billing adjustments は `docs/decisions/0032-automated-billing-adjustments-policy.md` により v1 deferred とし、product / finance / legal policy なしに実装しない。customer-visible dispute / refund request intake も `docs/decisions/0033-customer-billing-dispute-refund-request-flow.md` により v1 deferred とし、local request table、status table、provider case id storage は追加しない。cancellation success 時は scrub-safe local sync fields として `billing_cancel_at_period_end=false`、`billing_last_synced_at=now()` を更新できる。success / skipped / failure は `billing.subscription_cancel.request` security event に provider key、result、reason、previous local plan/status、changed local field names だけを保存する。operator triage は `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md` に従う。

tenant-wide export と tenant archive 方針は `docs/decisions/0024-tenant-export-deletion-archive.md` を正とする。implemented tenant export は tenant metadata、member roster、invitation history、plan / subscription state、quota counts、memory inventory aggregates、security event summary だけを返し、他 user の private / secret memory content は返さない。tenant archive lifecycle fields、archived-tenant auth rejection、tenant archive endpoint は実装済み。`archived_at` が入った tenant は active plan として扱わず、login token 発行と既存 Bearer token による protected API access を拒否する。implemented tenant archive endpoint は archive-first とし、tenant を frozen state にして token / unlock token / pending invitation を失効させるが、archive 直後に users / memories / categories / tags / accepted invitations / security events を物理削除しない。

tenant purge retention policy は `docs/decisions/0025-tenant-purge-retention-policy.md` を正とする。purge eligibility は `archived_at is not null`、`scheduled_deletion_at <= now()`、`purged_at is null`。purge は public API ではなく internal job / console command として実装済み。tenant row は物理削除せず tombstone として保持し、`public_id`、archive / deletion timestamps、local subscription state、`purged_at` を残す一方、`name`、`slug`、`archive_reason` は scrub する。purge は tenant memories を soft-deleted row と secret row を含めて force delete し、memory_tag、categories、tags、personal_access_tokens、secret_unlock_tokens、password_reset_tokens、sessions、tenant_member_invitations を削除する。tenant users は物理削除せず、PII / credentials を匿名化し、`tenant_id = null`、`role = member`、`account_status = disabled` にして detached account として残す。pre-existing tenant `security_events` は event type / outcome / timestamps / FK を最小保持し、subject email、IP address、user agent、raw metadata を null に scrub する。

### users

Laravel 標準 `users` を利用し、`tenant_id` と tenant role を持たせる。初期 SaaS baseline では 1 user 1 tenant を維持し、membership table は tenant 兼務が必要になった段階で追加する。

- `public_id`: `usr_` + ULID。external API / frontend payload 用の stable public id。
- `tenant_id`
- `role`: `owner`, `admin`, `member`。default は `member`。
- `account_status`: `active`, `disabled`, `suspended`。default は `active`。
- `email_verified_at`: nullable datetime。email verification 完了時に保存する。
- `pending_email`: nullable string。email change request 中の変更先 email。verification 完了まで `email` は現行値を維持する。
- `pending_email_requested_at`: nullable datetime。email change request の受付時刻。
- `secret_unlock_password`: nullable string。`visibility=secret` memory の追加認可で使う専用 unlock password hash。account password とは別に保存し、plain text は保存しない。
- `deleted_at`: nullable datetime。self-service account deletion で user row を保持したまま認証不能な anonymized account として扱うために使う。
- `anonymized_at`: nullable datetime。account deletion で email / name / pending email / password を匿名化または無効化した時刻。

初期 tenant onboarding で作る user は `owner`。local seed と `bunshin:issue-admin-token` で作る検証用 user も default は `owner`。`owner` / `admin` は tenant member 管理用の role guard を通過でき、`member` は通過できない。

account status は認証可否の最小 gate として扱う。`active` user だけが login token 発行と Bearer token authenticated access を利用できる。`disabled` / `suspended` user の既存 `personal_access_tokens` は guard で拒否し、`last_used_at` も更新しない。status 変更 API の設計は `docs/decisions/0022-account-status-management-api.md` を正とする。status 変更 API の成功時は対象 user の Bearer token と secret unlock token を削除し、reactivation 後に古い token が復活しないようにする。reactivation は `email_verified_at`、`pending_email`、account password、secret unlock password、tenant role、memory ownership を変更しない。

email verification は Laravel の `VerifyEmail` notification と一時署名付き URL を使う。signup / tenant invitation accept 後に notification を送り、`GET /api/v1/auth/email/verify/{id}/{hash}` で `email_verified_at` を更新する。resend は authenticated user の tenant context を確認してから送る。

email change は `users.pending_email` に変更先を保存し、on-demand notification を変更先 email に送る。`GET /api/v1/auth/email/change/verify/{id}/{hash}` の signed link が valid な場合だけ `pending_email` を `email` に反映し、`pending_email` / `pending_email_requested_at` を null に戻し、`email_verified_at` を verification 完了時刻に更新する。request 時と verification 完了時の両方で、他 user の `email` / `pending_email` と重複しないことを確認する。

secret unlock password は `users.secret_unlock_password` に hash として保存する。`POST /api/v1/secret-unlocks` はこの専用 hash だけを検証し、`users.password` は unlock 判定に使わない。未設定 user は `422` を返し、unlock token を発行しない。`PUT /api/v1/secret-unlock-password` は account password を常に確認し、設定済み user の変更では現在の unlock password も確認してから hash を更新する。成功時は既存 `secret_unlock_tokens` を削除し、発行済み unlock token を失効させる。self-service recovery request は account password と verified email を確認して signed recovery link を送るだけで、`users.secret_unlock_password` と既存 unlock token は変更しない。self-service recovery completion は hash を更新して既存 unlock token を削除するが、current unlock password は要求せず、Bearer token、account password、verified email の signed link を全て要求する。manager forced rotation は対象 user の `secret_unlock_password` を `null` に戻し、既存 unlock token を削除する。local development seed user は smoke test 用に `secret-password` を設定する。

account password change は `users.password` の hash を更新し、成功時に対象 user の `personal_access_tokens` を全て削除する。`users.secret_unlock_password` は account password とは別 credential のため、account password change では変更しない。

profile update は初期 contract では `users.name` だけを更新する。`users.email` は email verification を伴う email change API で扱うため、`PATCH /api/v1/auth/profile` では変更しない。

account export / deletion 方針は `docs/decisions/0023-account-deletion-export.md` を正とする。implemented self-service account export は current user の data bundle を同期 JSON で返すが、`visibility=secret` memory 本文 / tag / metadata は valid `X-Secret-Unlock` がある場合だけ含める。implemented self-service account deletion は user row を物理削除せず、`deleted_at` / `anonymized_at` を保存し、PII と credentials を無効化し、tenant access を外し、Bearer token と secret unlock token を削除する。current user owner scope の memories は soft delete し、categories は削除し、memory tag pivot を detach する。secret memory は削除対象に含めるが、削除 response で内容は返さない。tenant-level export / archive は account-level lifecycle とは別に扱い、owner であっても他 user の memory content を tenant-wide export で bulk read しない。

### tenant_member_invitations

Tenant member invite / accept 用の invitation storage。plain text invitation token は保存せず、sha256 hash のみ保存する。client / operator に渡す token は `inv_...|plainTextToken` 形式で、作成 response で 1 回だけ返す。legacy numeric `id|plainTextToken` token は v1 transition 中の accept 互換として維持する。

- `public_id`: `inv_` + ULID。tenant invitation management route / frontend state 用の stable public id。migration で既存 row を backfill し、新規作成時は model trait が自動生成する。
- `tenant_id`
- `invited_by_user_id`
- `accepted_user_id`
- `email`
- `role`: `owner`, `admin`, `member`
- `token_hash`
- `expires_at`
- `accepted_at`
- `revoked_at`
- `created_at`
- `updated_at`

初期 TTL は 7 日。accept flow は新規 user 作成のみを扱い、既存 user の tenant 紐付けや multi-tenant membership は別 task にする。member revoke は user row を削除せず、`users.tenant_id = null`、`users.role = member` に戻し、対象 user の Bearer token を削除する。

Invitation management route は `inv_01...` を正とし、numeric id は v1 transition 互換としてだけ残す。invite token は opaque credential であり、management route id として扱わない。既存 pending invitation を壊さないため、accept endpoint は legacy numeric `id|plainTextToken` token も transition 中は受け付ける。

### personal_access_tokens

Sanctum personal access token 相当の token storage。plain text token は保存せず、sha256 hash のみ保存する。

- `id`
- `tokenable_type`
- `tokenable_id`
- `name`
- `token`
- `abilities`
- `last_used_at`
- `expires_at`
- `created_at`
- `updated_at`

client に渡す token は `id|plainTextToken` 形式とする。guard は Bearer token の id 部分で候補を探し、plain text 部分の sha256 hash と保存済み hash を `hash_equals` で比較する。

### secret_unlock_tokens

記憶の海 / 宇宙画面で `visibility=secret` memory を一時的に表示するための user scoped unlock token。plain text token は保存せず、sha256 hash のみ保存する。

- `id`
- `user_id`
- `token`
- `last_used_at`
- `expires_at`
- `created_at`
- `updated_at`

client に渡す token は `id|plainTextToken` 形式とする。`GET /api/v1/memory-space?include_secret=1` は `X-Secret-Unlock` header の token をこの table で検証し、token user が request user と一致し、かつ期限内の場合だけ secret memory を含める。

初期 baseline の TTL は 15 分。`POST /api/v1/secret-unlocks` は `users.secret_unlock_password` の専用 hash を検証し、account password hash は使わない。`PUT /api/v1/secret-unlock-password` で専用 unlock password を setup / change した場合、既存 token は削除する。secret unlock password recovery completion と manager forced rotation も対象 user の既存 token を削除する。recovery request だけでは token を削除しない。

### security_events

Auth / security event の append-only baseline。broader audit 方針は `docs/decisions/0026-broader-audit-log-admin-impersonation.md` を正とし、この table を v1 audit sink として拡張済み。現時点の実装では login、signup、password reset request / complete、password change、token lifecycle、profile update、tenant invitation accept / create / revoke、tenant member role change / revoke、email verification request / complete、email change request / complete、secret unlock password setup / change / recovery request / recovery complete / forced rotation、account status change、account export request、account deletion、tenant export request、tenant archive、tenant purge、memory create / update / delete、category create / update / delete の event を保存する。`auth.tenant.purge` success event には scrub-safe count だけを残す。archived tenant login rejection は `auth.login` failure / `metadata.reason=tenant_archived` として保存する。plain password、plain token、invite token、signed URL secret、unlock token、export bundle、secret memory content は保存しない。

- `tenant_id`
- `user_id`
- `event_type`: existing implemented values include `auth.login`, `auth.signup`, `auth.password_reset.request`, `auth.password_reset.complete`, `auth.password_change`, `auth.token.logout`, `auth.token.revoke`, `auth.token.revoke_all`, `auth.token.rotate`, `auth.profile.update`, `auth.tenant_invitation.accept`, `auth.tenant_invitation.create`, `auth.tenant_invitation.revoke`, `auth.tenant_member.role_change`, `auth.tenant_member.revoke`, `auth.email_verification.request`, `auth.email_verification.complete`, `auth.email_change.request`, `auth.email_change.complete`, `auth.secret_unlock_password_recovery.request`, `auth.secret_unlock_password_recovery.complete`, `auth.secret_unlock_password_forced_rotation`, `auth.secret_unlock_password.change`, `auth.account_status.change`, `auth.account_export.request`, `auth.account.delete`, `auth.tenant_export.request`, `auth.tenant.archive`, `auth.tenant.purge`, `memory.create`, `memory.update`, `memory.delete`, `category.create`, `category.update`, `category.delete`
- `outcome`: `success`, `failure`, `requested`
- `subject_email`
- `ip_address`
- `user_agent`
- `metadata`
- `created_at`

`tenant_id` / `user_id` は判定できる場合だけ保存する。存在しない email への login / password reset request や invalid invitation token では null になり得る。`metadata.reason` は `invalid_credentials`、`account_not_active`、`invalid_current_password`、`invalid_invite_token`、`tenant_context_missing`、`invalid_or_expired_token`、`invalid_signature`、`invalid_hash`、`self_target`、`owner_boundary` などの machine-readable reason を入れる。account status で login を拒否した場合は `metadata.account_status` に `disabled` または `suspended` を保存する。secret unlock password forced rotation event では `user_id` は manager、`subject_email` は対象 user、metadata に対象 user public id / role と manager role を保存する。

Broader audit metadata uses public ids where possible and scrub-safe scalar/count fields only. Memory/category write events store resource public ids, visibility, changed field names, category public id, tag count, or affected memory count, but not memory titles, memory bodies, category names, tag names, secret content, export bundles, plain credentials, raw request payloads, or raw validation errors. Initial active-tenant retention target is 180 days; tenant purge retains only minimal scrubbed security event rows according to decision 0025. Security event pruning policy is defined in `docs/decisions/0027-security-event-pruning-policy.md`: null-tenant and non-purged tenant rows are pruned by `created_at`, while purged-tenant rows, including scrubbed rows and `auth.tenant.purge`, are pruned after `tenants.purged_at` is older than the retention cutoff. External logging/search integration is designed in `docs/decisions/0028-external-logging-search-integration.md`; it is deferred and, if implemented later, must export only a sanitized projection instead of raw `security_events` rows.

### billing_webhook_events

Implemented billing webhook idempotency / processing state storage。処理方針は `docs/decisions/0029-billing-provider-integration.md` を正とする。raw provider webhook body は保存せず、signature verification 後に hash と scrub-safe processing metadata だけを保存する。

- `billing_provider`
- `provider_event_id`
- `event_type`
- `livemode`
- `tenant_id`
- `billing_customer_id`
- `billing_subscription_id`
- `payload_hash`
- `received_at`
- `processed_at`
- `processing_status`: `received`, `processed`, `ignored`, `failed`
- `error_code`
- `error_message`

`billing_provider` + `provider_event_id` は unique とし、duplicate webhook は idempotent no-op として扱う。verified checkout completion / subscription webhook は known price mapping の場合だけ `tenants.plan_key` / `subscription_status` / provider linkage を同期する。unknown tenant / customer / subscription / price id、unknown provider status、archived tenant は paid entitlement を grant せず、`failed` または `ignored` の scrubbed processing state を保存する。card data、billing address、tax id、raw customer email、raw payload、signature secret、checkout / portal URL、provider API key は table、logs、`security_events` に保存しない。

### memories

- `id`
- `public_id`: `mem_` + ULID。external API / frontend payload 用の stable public id。
- `tenant_id`
- `owner_user_id`
- `category_id`
- `period_key`
- `occurred_on`
- `title`
- `body`
- `emotion_label`
- `emotion_intensity`
- `visibility`
- `source`
- `metadata`
- `created_at`
- `updated_at`
- `deleted_at`

`visibility` は `private`, `secret`, `shared` を初期候補にする。旧「墓場まで」は `secret` として扱う。`secret` は通常 list から除外し、明示 filter または ID 指定で認可された場合だけ返す。

`period_key` / `occurred_on` はカテゴリーとは別の時間軸として扱う。年代別表示やタイムラインはこの軸から派生させ、カテゴリー階層には混ぜない。

記憶の海 / 宇宙画面で使う複数 emotion score、表示重み、beliefs、chains は初期実装では `metadata` に置く。

- `metadata.emotion_scores`: emotion label to score map。例: `{ "感動": 92, "懐かしさ": 88 }`
- `metadata.importance_score`: 0.0-1.0 の表示重み。
- `metadata.beliefs`: string array。
- `metadata.chains`: string array。

### categories

- `id`
- `public_id`: `cat_` + ULID。external API / frontend payload 用の stable public id。
- `tenant_id`
- `owner_user_id`
- `parent_id`
- `name`
- `slug`
- `sort_order`
- `created_at`
- `updated_at`

大カテゴリー / サブカテゴリーは同一 `categories` table の階層として表現する。

- `parent_id = null`: root category / 大カテゴリー。
- `parent_id = <category id>`: subcategory / サブカテゴリー。
- 初期実装では深さ 2 までを正式対応範囲にする。
- `memories.category_id` は原則として末端カテゴリーを指す。ただしサブカテゴリーなしの root category 直下 memory も許容する。
- `parent_id` は同一 tenant / owner 内の category だけを参照できるよう validation / query scope で制約する。

slug uniqueness は初期実装では tenant / owner 全体で unique のまま維持する。兄弟内 unique への変更は、URL 設計が必要になった段階で別 task にする。

### tags

- `id`
- `tenant_id`
- `name`
- `normalized_name`
- `created_at`
- `updated_at`

`normalized_name` は tag 入力を trim、英数字/スペースの幅正規化、空白連続の 1 スペース化、lowercase 化した storage key。初期実装では deterministic alias として `ともだち` / `友人` を `友達`、`なつ` を `夏` に統合する。同一 `tenant_id` 内では `normalized_name` を unique にし、表記ゆれ入力は同じ tag に紐づける。別 tenant の tag とは統合しない。

### memory_tag

- `memory_id`
- `tag_id`

Memory の API delete は soft delete とし、削除前に `memory_tag` pivot を detach する。これにより削除済み memory は list / detail から返らず、tag usage count にも残らない。

## tenant 分離

全ての user data table は `tenant_id` を持つ。API は認証済み request user から `TenantUserContext` を作り、query は context scope を必ず通す。

- `Memory::queryForContext($context)` / `Memory::findForContext($context, $id)`: `tenant_id` と `owner_user_id` の両方で絞る。
- `Category::queryForContext($context)` / `Category::findForContext($context, $id)`: `tenant_id` と `owner_user_id` の両方で絞る。
- `Tag::queryForContext($context)` / `Tag::findForContext($context, $id)`: `tenant_id` で絞る。
- detail / update / delete 相当の単体取得も `findForContext` を通し、別 tenant または別 owner の data は存在しないものとして扱う。

## public id

`tenants` / `users` / `categories` / `memories` / `tenant_member_invitations` は integer primary key を内部 id として維持しつつ、`public_id` を external API / frontend payload 用に持つ。形式は `ten_01...` / `usr_01...` / `cat_01...` / `mem_01...` / `inv_01...` の prefixed ULID。migration で既存 row を backfill し、新規作成時は model trait が自動生成する。

request lookup の移行方針は `docs/decisions/0020-public-id-request-lookup.md` を正とする。新規 client request では `public_id` を正とし、integer id は v1 transition 中の互換値としてだけ扱う。

- `memories` route param は `mem_01...`、`categories` route param と `category_id` / `parent_id` reference は `cat_01...`、tenant member route param は `usr_01...`、tenant invitation route param は `inv_01...` を正とする。
- `category_id` / `parent_id` は database 上は integer FK のまま維持し、request validation で context-scoped category public id から内部 id へ変換する。
- response の integer `id` は移行期間中は残すが、frontend state や external integration は `public_id` / `parent_public_id` / `category_public_id` / `user_public_id` を使う。
- `tenant_member_invitations` の invitation management route は `inv_01...` を正とし、integer id は v1 transition 互換としてだけ扱う。
- email verification / email change / secret unlock password recovery の signed URL は server-generated signed route であり、当面 numeric user id を維持する。

## subscription / quota

`Tenant` は `plan_key` / `subscription_status` / `trial_ends_at` / `subscription_ends_at` から `hasActivePlan()` を判定する。`TenantQuotaGuard` は create 系 endpoint の直前で tenant の active plan と quota を確認する。

- `POST /api/v1/memories`: tenant が inactive plan の場合は `402 Payment Required`。active plan でも tenant 全体の active memory 数が plan limit 以上なら `422 Unprocessable Entity`。
- `POST /api/v1/categories`: tenant が inactive plan の場合は `402 Payment Required`。active plan でも tenant 全体の category 数が plan limit 以上なら `422 Unprocessable Entity`。
- memory quota は soft deleted memory を通常 count から除外する。category quota は tenant 内の category row 数を数える。
- 初期 baseline は request user の owner-scoped data API を維持するが、quota は tenant plan に紐づくため tenant-wide count とする。

## security event / auth rate limit

認証系 write endpoint は named rate limiter を通す。初期値は `config/bunshin.php` の `bunshin.security.rate_limits` に置く。

- `login`: 10 requests / minute by normalized email + IP。
- `signup`: 5 requests / minute by normalized email + IP。
- `password_forgot`: 5 requests / minute by normalized email + IP。
- `password_reset`: 5 requests / minute by normalized email + IP。
- `password_change`: 5 requests / minute by authenticated user id。
- `invitation_accept`: 5 requests / minute by invitation token hash + IP。

rate limit 超過時は Laravel throttle middleware の `429 Too Many Requests` を返し、controller には到達しないため security event は記録しない。必要になれば middleware level event logging を別 task で追加する。

## validation 初期案

- `body`: required, string, trim 後 1 文字以上
- `period_key`: nullable, fixed enum
- `emotion_label`: nullable, max 40
- `emotion_intensity`: nullable, integer 1-5
- `visibility`: required, enum
- `tags`: array, max 20 items, each max 40 chars
- `tags.*`: trim 後に validation し、保存時は `TagNameNormalizer` を通して `name` / `normalized_name` を決める
- `category.name`: required, trim 後 1-80 chars
- `category.slug`: required, lowercase kebab-case, tenant / owner 内で unique
- `category.parent_id`: nullable category identifier。request では public id string (`cat_01...`) を正とし、v1 transition 中だけ integer category id も互換として受け付ける。同一 tenant / owner 内の root category のみ指定可。children を持つ category は subcategory 化不可
- `category.sort_order`: nullable integer, 0-999999
