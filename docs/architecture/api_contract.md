# API 契約

## 共通

- Base path: `/api/v1`
- Content-Type: `application/json`
- Protected endpoints require `Authorization: Bearer <plain_text_token>`。
- token は `personal_access_tokens` に sha256 hash として保存し、client には `id|plainTextToken` 形式を 1 回だけ返す。
- tenant onboarding は invite-only とし、`POST /api/v1/auth/signup` で初期 owner と tenant を同時に作成する。server 側に `BUNSHIN_ONBOARDING_INVITE_TOKEN` が未設定の場合、この endpoint は `403 Forbidden` で閉じる。
- user login 用 token は `POST /api/v1/auth/login` で発行する。管理画面モックアップ接続検証では引き続き `php artisan bunshin:issue-admin-token` で token を発行し、Settings の Bearer token に貼り付ける運用も使える。
- password reset は `POST /api/v1/auth/password/forgot` と `POST /api/v1/auth/password/reset` を使う。request は email 存在有無を区別しない `202 Accepted` を返す。
- account password change は protected `PUT /api/v1/auth/password` を使う。成功時は current token を含む既存 Bearer token を全て revoke し、再 login を必要にする。
- profile update は protected `PATCH /api/v1/auth/profile` を使う。初期 contract は `name` のみ更新し、email は `PUT /api/v1/auth/email` の email change flow で扱う。
- email verification は signup / tenant invitation accept 後に送信される一時署名付き URL を `GET /api/v1/auth/email/verify/{id}/{hash}` で検証する。resend は `POST /api/v1/auth/email/verification-notification` を使う。email change は `users.pending_email` に変更先を保持し、`GET /api/v1/auth/email/change/verify/{id}/{hash}` の signed link 完了後に `users.email` へ反映する。
- current token の確認は `GET /api/v1/auth/me`、失効は `POST /api/v1/auth/logout` を使う。logout は current token だけを削除し、同じ user の別 token は残す。
- token lifecycle は `GET /api/v1/auth/tokens`、`DELETE /api/v1/auth/tokens/{token}`、`POST /api/v1/auth/tokens/revoke-all`、`POST /api/v1/auth/tokens/rotate` を使う。token list は plain token / token hash を返さない。rotate の新 plain token は response で 1 回だけ返す。
- tenant role は初期 baseline では `users.role` に保存する。role は `owner`, `admin`, `member` の 3 種で、default は `member`。initial owner signup、local seed、admin token command の default user は `owner`。
- account status は `users.account_status` に保存する。`active` user だけが login / Bearer token access 可能で、`disabled` / `suspended` は既存 token も含めて認証拒否する。
- user が `disabled` / `suspended` の間、既存 Bearer token は guard では `401 Unauthorized` として扱い、`personal_access_tokens.last_used_at` も更新しない。status 変更 API による status transition 成功時は Bearer token と secret unlock token を削除し、reactivation 後に古い token を復活させない。
- tenant archive endpoint、tenant archive lifecycle fields、archived-tenant auth rejection は実装済み。`tenants.archived_at` が入った tenant は active plan として扱わず、login token 発行は `403 Forbidden`、既存 Bearer token による protected API access は `401 Unauthorized` として拒否する。archived tenant の既存 token は `last_used_at` を更新しない。
- tenant purge retention policy は `docs/decisions/0025-tenant-purge-retention-policy.md` を正とする。`php artisan bunshin:purge-archived-tenants` は public API endpoint ではなく internal job / console command として実装済みで、`scheduled_deletion_at` 到達後に tenant content / credentials / PII を削除または匿名化する。
- account export / deletion scope は `docs/decisions/0023-account-deletion-export.md` を正とする。self-service account export / deletion は実装済み。tenant-wide export と tenant archive 方針は `docs/decisions/0024-tenant-export-deletion-archive.md` を正とし、tenant-wide export では他 user の private / secret memory body を bulk export しない。
- tenant member management は `GET /api/v1/tenant/members`、`GET /api/v1/tenant/invitations`、`POST /api/v1/tenant/invitations`、`POST /api/v1/tenant/invitations/accept`、`DELETE /api/v1/tenant/invitations/{invitation}`、`PATCH /api/v1/tenant/members/{member}/role`、`PATCH /api/v1/tenant/members/{member}/account-status`、`DELETE /api/v1/tenant/members/{member}` を使う。invite token は sha256 hash 保存し、plain token は作成 response で 1 回だけ返し、作成時に invitee email へ mail notification でも送る。
- tenant subscription baseline は `tenants.plan_key` / `tenants.subscription_status` / `trial_ends_at` / `subscription_ends_at` に保存する。`tenants.archived_at` が入った tenant は subscription status にかかわらず inactive として扱う。`POST /api/v1/memories` と `POST /api/v1/categories` は active plan と plan quota を確認する。billing provider integration scope と webhook handling は `docs/decisions/0029-billing-provider-integration.md` を正とし、実装後も API runtime は local tenant fields を source of truth とする。checkout / portal endpoint は実装済みだが、local plan state は verified webhook または explicit reconciliation `--apply` まで変更しない。production env / frontend smoke は `docs/operations/billing_provider_production_smoke_runbook.md` を正とする。automated refund / credit / proration / invoice finalization / dunning / dispute / period-end cancellation は `docs/decisions/0032-automated-billing-adjustments-policy.md` により v1 deferred とし、customer-visible dispute / refund request intake は `docs/decisions/0033-customer-billing-dispute-refund-request-flow.md` により v1 deferred とする。public API fields / endpoints は追加しない。
- `tenants` / `users` / `categories` / `memories` / `tenant_member_invitations` は `public_id` を持つ。形式は `ten_01...` / `usr_01...` / `cat_01...` / `mem_01...` / `inv_01...` の prefixed ULID。今後の client request は public id を正とし、integer id は v1 transition 中の互換値としてだけ受け付ける。詳細は `docs/decisions/0020-public-id-request-lookup.md`。
- auth security event baseline は `security_events` に login / signup / password reset / password change / tenant invitation accept / email verification / email change / secret unlock password recovery request / complete / forced rotation / account status change / account export / account deletion / tenant export / tenant archive / tenant purge / billing checkout session create / billing portal session create の最小 event を保存する。plain password / plain token / invite token / signed URL secret / hosted billing URL は保存しない。
- broader audit log / admin impersonation 方針は `docs/decisions/0026-broader-audit-log-admin-impersonation.md` を正とする。初期 broader audit は既存 `security_events` table を v1 audit sink として拡張済みで、token lifecycle、tenant member management、profile / secret unlock password changes、memory/category writes の successful write event を保存する。metadata は public id と scrub-safe fields に限定し、memory title/body、category/tag names、secret content、plain credential、raw payload は保存しない。admin impersonation は初期 SaaS scope から除外し、owner/admin でも他 user として token を発行したり secret unlock を bypass したりしない。external logging/search integration は `docs/decisions/0028-external-logging-search-integration.md` を正とし、初期実装は deferred。将来実装する場合も raw `security_events` row ではなく sanitized projection だけを外部 sink に送る。
- auth write endpoint は named rate limiter を通す。`POST /auth/signup`、`POST /auth/login`、`POST /auth/password/forgot`、`POST /auth/password/reset`、`PUT /auth/password`、`POST /tenant/invitations/accept`、`POST /auth/email/verification-notification`、`PUT /auth/email`、`POST /secret-unlock-password/recovery/request`、`PUT /secret-unlock-password/recovery/{id}/{hash}`、`POST /billing/checkout-sessions`、`POST /billing/portal-sessions` は超過時に `429 Too Many Requests` を返す。
- secret unlock password recovery / forced rotation は `PUT /api/v1/secret-unlock-password` とは別 endpoint として扱う。self-service recovery は Bearer token、account password、verified email の signed link を全て要求し、manager forced rotation は対象 user の unlock password を clear して既存 unlock token を失効させるだけで、secret 内容や temporary password は返さない。
- secret unlock password recovery request / completion は named rate limiter を通す。forced rotation は tenant security action rate limit と tenant member management policy に従う。
- command は同じ user / token name の既存 token を revoke してから新しい token を発行する。
- 手動 smoke test の確認順は `docs/references/admin-ui-mockup/manual-smoke-test.md` を参照する。
- 記憶の海 / 宇宙画面の設計は `docs/architecture/memory_space_screen.md` を参照する。
- `/api/v1` 配下の例外 response は、client が `Accept: application/json` を送らない場合でも JSON として返す。未認証は `401`、validation error は `422`。
- Error format:

```json
{
  "message": "Validation failed.",
  "errors": {
    "body": ["本文を入力してください。"]
  }
}
```

Quota / subscription errors:

- inactive subscription は `402 Payment Required` とし、`{"message":"Tenant subscription is not active."}` を返す。
- plan quota 超過は `422 Unprocessable Entity` とし、`errors.quota` と対象 resource key (`memories` または `categories`) を返す。

## Public ID request lookup

新規 client は API response の `public_id` / `parent_public_id` / `category_public_id` / `user_public_id` を保存し、以後の route param / request field へ送る。integer `id` は内部 id であり、v1 transition 中だけ request 互換として残す。

- Memory route param: `/memories/{memory}` は `mem_01...` を正とする。numeric memory id は移行互換。
- Category route param: `/categories/{category}` は `cat_01...` を正とする。numeric category id は移行互換。
- Tenant member route param: `/tenant/members/{member}` 系は `usr_01...` を正とする。numeric user id は移行互換。
- Category reference fields: `category_id` / `parent_id` は field 名を v1 では維持しつつ、値は `cat_01...` を正とする。numeric category id は移行互換。
- `tenant_member_invitations` は `inv_` public id を持つ。`/tenant/invitations/{invitation}` は `inv_01...` を正、numeric id を v1 transition 互換とする。詳細は `docs/decisions/0021-tenant-member-invitation-public-id.md`。
- email verification / email change / secret unlock password recovery の signed URL は server-generated signed route のため、当面 numeric user id を維持する。
- path lookup の context 外 / missing / malformed / wrong-prefix は `404 Not Found`。write payload の malformed / wrong-prefix / missing / context 外 category は `422`。list filter の context 外 category は現在どおり空 result / aggregate とする。

## Health

`GET /api/v1/health`

```json
{
  "service": "bunshin-memory-api",
  "status": "ok",
  "version": "0.1.0"
}
```

## Auth

`POST /api/v1/auth/signup`

invite-only onboarding 用 endpoint。初期 tenant と owner user を同じ transaction で作成し、作成した owner 用 Bearer token を発行する。public signup は採用しない。server 側の `BUNSHIN_ONBOARDING_INVITE_TOKEN` と request の `invite_token` が一致する場合だけ作成できる。

Request:

```json
{
  "invite_token": "shared-invite-token",
  "tenant_name": "分身AI",
  "tenant_slug": "bunshin-ai",
  "name": "Owner User",
  "email": "owner@example.test",
  "password": "strong-password",
  "password_confirmation": "strong-password"
}
```

Validation:

- `invite_token`: required string, max 2048。trim して比較する。
- `tenant_name`: required string, max 255。trim して保存する。
- `tenant_slug`: required string, max 80, lowercase alphanumeric hyphen, unique。trim / lowercase に正規化する。
- `name`: required string, max 255。trim して保存する。
- `email`: required email, max 255, unique。trim / lowercase に正規化する。
- `password`: required string, min 8, max 1024, confirmed。

Response: `201 Created`

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "id|plain-text-token",
    "expires_at": null,
    "user": {
      "id": 1,
      "name": "Owner User",
      "email": "owner@example.test",
      "pending_email": null,
      "pending_email_requested_at": null,
      "role": "owner",
      "account_status": "active",
      "is_email_verified": false,
      "email_verified_at": null
    },
    "tenant": {
      "id": 1,
      "name": "分身AI",
      "slug": "bunshin-ai",
      "plan_key": "free",
      "subscription_status": "active",
      "has_active_plan": true,
      "trial_ends_at": null,
      "subscription_ends_at": null
    }
  }
}
```

- invalid invite token または server 側 invite token 未設定は `403 Forbidden`。tenant / user / token は作成しない。
- 重複 tenant slug / email と payload shape 不正は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。
- 作成された owner user は必ず `tenant_id` と `role=owner` を持つ。tenant 未所属 user は onboarding API から作成しない。
- 作成後に email verification notification を送る。verification 未完了でも signup token は発行する。
- email verification 未完了 user への login token 発行は当面許可する。tenant 設定、member invitation、billing などの危険操作は後続 task で verified 必須化する。

`POST /api/v1/auth/login`

email / password を検証し、API 用 Bearer token を発行する。token は `personal_access_tokens` に sha256 hash として保存し、plain text token は response で 1 回だけ返す。初期 baseline では login token の `expires_at` は `null`。

Request:

```json
{
  "email": "admin@example.test",
  "password": "password"
}
```

Validation:

- `email`: required email, max 255。trim して lowercase に正規化する。
- `password`: required string, max 1024。

Response: `201 Created`

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "id|plain-text-token",
    "expires_at": null,
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.test",
      "pending_email": null,
      "pending_email_requested_at": null,
      "role": "owner",
      "account_status": "active",
      "is_email_verified": true,
      "email_verified_at": "2026-05-13T10:00:00+00:00"
    },
    "tenant": {
      "id": 1,
      "name": "Default",
      "slug": "default",
      "plan_key": "free",
      "subscription_status": "active",
      "has_active_plan": true,
      "trial_ends_at": null,
      "subscription_ends_at": null
    }
  }
}
```

- invalid credentials は `401 Unauthorized`。email 存在有無を区別しない。
- tenant を持たない user は token を発行せず `403 Forbidden`。
- `account_status` が `disabled` または `suspended` の user は token を発行せず `403 Forbidden`。`security_events.metadata.reason` は `account_not_active`、`metadata.account_status` は実際の status を保存する。
- archived tenant の user は token を発行せず `403 Forbidden`。`security_events.metadata.reason` は `tenant_archived`。
- payload shape が不正な request は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。
- email verification 未完了 user への login token 発行は当面許可する。tenant 設定、member invitation、billing などの危険操作は後続 task で verified 必須化する。

`POST /api/v1/auth/password/forgot`

password reset link を email へ送る。Laravel password broker の `password_reset_tokens` を使い、token は hash 保存される。存在しない email でも account enumeration を避けるため同じ `202 Accepted` response を返す。

Request:

```json
{
  "email": "admin@example.test"
}
```

Validation:

- `email`: required email, max 255。trim して lowercase に正規化する。

Response: `202 Accepted`

```json
{
  "message": "If an account exists for this email, a password reset link has been sent."
}
```

- payload shape が不正な request は `422 Unprocessable Entity`。
- 既存 email に対しては password broker の token throttle を使う。throttle 中でも response は account enumeration を避けるため同一 message の `202 Accepted`。
- route rate limit 超過は `429 Too Many Requests`。

`POST /api/v1/auth/password/reset`

password reset token を検証して password を更新する。成功時は password reset token を削除し、対象 user の既存 Bearer token を全て revoke する。

Request:

```json
{
  "email": "admin@example.test",
  "token": "plain-reset-token",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Validation:

- `email`: required email, max 255。trim して lowercase に正規化する。
- `token`: required string, max 2048。
- `password`: required string, min 8, max 1024, confirmed。

Response: `204 No Content`

- invalid / expired token または email と token の不一致は `422 Unprocessable Entity`。`token` validation error として返す。
- reset 後、対象 user の既存 Bearer token は protected endpoint に使えない。
- rate limit 超過は `429 Too Many Requests`。

`PUT /api/v1/auth/password`

authenticated user の account password を変更する。現在の account password を確認し、成功時は current token を含む対象 user の既存 Bearer token を全て revoke する。secret unlock password は account password とは別 credential のため、この endpoint では変更しない。

Request:

```json
{
  "current_password": "current account password",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Validation:

- `current_password`: required string, max 1024。
- `password`: required string, min 8, max 1024, confirmed, `current_password` と同じ値は不可。

Response: `204 No Content`

- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。
- invalid current password は `422 Unprocessable Entity` とし、`current_password` validation error として返す。
- payload shape が不正な request は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。
- 成功後、変更に使った Bearer token を含む既存 token は protected endpoint に使えない。client は新 password で再 login する。

`GET /api/v1/auth/email/verify/{id}/{hash}`

email verification notification に含まれる一時署名付き URL を検証し、対象 user の `email_verified_at` を保存する。Bearer token は不要。URL signature の有効期限は `auth.verification.expire` に従い、初期値は 60 分。

Response: `200 OK`

```json
{
  "message": "Email has been verified.",
  "data": {
    "user": {
      "id": 1,
      "name": "Owner User",
      "email": "owner@example.test",
      "pending_email": null,
      "pending_email_requested_at": null,
      "role": "owner",
      "account_status": "active",
      "is_email_verified": true,
      "email_verified_at": "2026-05-13T10:00:00+00:00"
    }
  }
}
```

- 既に verified の user に対しては `200 OK` で `"Email is already verified."` を返す。
- signature / hash が不正または期限切れの場合は `403 Forbidden`。
- 対象 user が存在しない場合は `404 Not Found`。
- 対象 user が tenant を持たない場合は `403 Forbidden`。

`POST /api/v1/auth/email/verification-notification`

authenticated user に email verification notification を再送する。Protected endpoint なので Bearer token が必要。

Response: `202 Accepted`

```json
{
  "message": "Email verification link has been sent."
}
```

- 既に verified の user に対しては notification を送らず `200 OK` と current user payload を返す。
- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。
- rate limit 超過は `429 Too Many Requests`。

`PUT /api/v1/auth/email`

authenticated user の email change を request する。変更先 email は trim / lowercase 正規化し、`users.pending_email` に保存する。`users.email` は signed verification link が完了するまで変更しない。verification notification は変更先 email へ on-demand mail として送る。

Request:

```json
{
  "email": "new@example.test"
}
```

Validation:

- `email`: required email, max 255。trim / lowercase に正規化する。
- current user の現在の `email` と同一なら `422 Unprocessable Entity`。
- 他 user の `email` または `pending_email` と重複する場合は `422 Unprocessable Entity`。

Response: `202 Accepted`

```json
{
  "message": "Email change verification link has been sent.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.test",
      "pending_email": "new@example.test",
      "pending_email_requested_at": "2026-05-14T07:00:00+00:00",
      "role": "owner",
      "account_status": "active",
      "is_email_verified": true,
      "email_verified_at": "2026-05-13T10:00:00+00:00"
    }
  }
}
```

- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。
- rate limit 超過は `429 Too Many Requests`。
- request 時点では Bearer token を revoke しない。

`GET /api/v1/auth/email/change/verify/{id}/{hash}`

email change notification に含まれる一時署名付き URL を検証し、対象 user の `pending_email` を `email` に反映する。Bearer token は不要。URL signature の有効期限は `auth.verification.expire` に従い、初期値は 60 分。

Response: `200 OK`

```json
{
  "message": "Email has been changed.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "new@example.test",
      "pending_email": null,
      "pending_email_requested_at": null,
      "role": "owner",
      "account_status": "active",
      "is_email_verified": true,
      "email_verified_at": "2026-05-14T07:05:00+00:00"
    }
  }
}
```

- signature / hash が不正、期限切れ、または `pending_email` がない場合は `403 Forbidden`。
- 対象 user が存在しない場合は `404 Not Found`。
- 対象 user が tenant を持たない場合は `403 Forbidden`。
- verification 完了時点で `pending_email` が他 user の `email` または `pending_email` と重複している場合は `422 Unprocessable Entity`。
- 成功時は `pending_email` / `pending_email_requested_at` を消し、`email_verified_at` を verification 完了時刻に更新する。Bearer token は revoke しない。

`GET /api/v1/auth/me`

authenticated user / tenant / current token metadata を返す。token の plain text は返さない。

Response: `200 OK`

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.test",
      "pending_email": null,
      "pending_email_requested_at": null,
      "role": "owner",
      "account_status": "active",
      "is_email_verified": true,
      "email_verified_at": "2026-05-13T10:00:00+00:00"
    },
    "tenant": {
      "id": 1,
      "name": "Default",
      "slug": "default",
      "plan_key": "free",
      "subscription_status": "active",
      "has_active_plan": true,
      "trial_ends_at": null,
      "subscription_ends_at": null
    },
    "token": {
      "id": 10,
      "name": "login",
      "abilities": ["*"],
      "last_used_at": "2026-05-12T10:00:00+00:00",
      "expires_at": null,
      "created_at": "2026-05-12T09:55:00+00:00"
    }
  }
}
```

- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。

`PATCH /api/v1/auth/profile`

authenticated user の最小 profile を更新する。初期 contract では `users.name` のみを更新し、email は変更しない。email は verification を伴う `PUT /api/v1/auth/email` で変更する。

Request:

```json
{
  "name": "New Profile Name"
}
```

Validation:

- `name`: required string, max 255。trim して保存する。trim 後に空文字なら `422 Unprocessable Entity`。
- `email`: prohibited。この endpoint では変更不可。

Response: `200 OK`

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "New Profile Name",
      "email": "admin@example.test",
      "pending_email": null,
      "pending_email_requested_at": null,
      "role": "owner",
      "account_status": "active",
      "is_email_verified": true,
      "email_verified_at": "2026-05-13T10:00:00+00:00"
    }
  }
}
```

- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。
- email を含む payload や name が不正な request は `422 Unprocessable Entity`。
- profile update は Bearer token を revoke しない。current token は更新後も有効。

Auth user payload:

- `id`: integer
- `public_id`: string, `usr_` + ULID
- `name`: string
- `email`: string
- `pending_email`: nullable string。email change request 中の未検証 email。
- `pending_email_requested_at`: nullable ISO8601 datetime。
- `role`: `owner`, `admin`, `member`
- `account_status`: `active`, `disabled`, `suspended`
- `is_email_verified`: boolean
- `email_verified_at`: nullable ISO8601 datetime

Auth tenant payload:

- `id`: integer
- `public_id`: string, `ten_` + ULID
- `name`: string
- `slug`: string
- `plan_key`: `free`, `pro` などの plan identifier。
- `subscription_status`: `active`, `trialing`, `past_due`, `canceled`, `incomplete`。
- `has_active_plan`: boolean。active / trialing かつ期限切れでない場合だけ `true`。
- `trial_ends_at`: nullable ISO8601 datetime。
- `subscription_ends_at`: nullable ISO8601 datetime。

Role guard:

- `manage-tenant-members` は authenticated user と対象 tenant が一致し、かつ user role が `owner` または `admin` の場合だけ許可する。
- 現行の memories / categories は引き続き authenticated user の `tenant_id` / `id` を `TenantUserContext` として使う owner-scoped data API であり、role によって他 owner の data を広げない。

## Tenant Member Management

Protected tenant member management endpoints require `auth:sanctum` Bearer token and `manage-tenant-members` guard。`owner` / `admin` は自 tenant だけを管理でき、`member` は `403 Forbidden`。別 tenant の user / invitation は存在していても `404 Not Found`。

`TenantMember` payload:

```json
{
  "id": 2,
  "public_id": "usr_01HX0000000000000000000000",
  "name": "Member User",
  "email": "member@example.test",
  "role": "member",
  "is_email_verified": true,
  "email_verified_at": "2026-05-13T10:00:00+00:00"
}
```

`TenantMemberInvitation` payload:

```json
{
  "id": 1,
  "public_id": "inv_01HX0000000000000000000000",
  "email": "invitee@example.test",
  "role": "member",
  "status": "pending",
  "invited_by_user_id": 1,
  "invited_by_user_public_id": "usr_01HX0000000000000000000000",
  "accepted_user_id": null,
  "accepted_user_public_id": null,
  "expires_at": "2026-05-20T01:00:00+00:00",
  "accepted_at": null,
  "revoked_at": null,
  "created_at": "2026-05-13T01:00:00+00:00"
}
```

Invitation status は `pending`, `accepted`, `revoked`, `expired`。

Tenant member route params use `user.public_id` (`usr_01...`) as the canonical identifier during the public id migration. Numeric user ids remain accepted only as v1 transition compatibility. Tenant invitation route params use `tenant_member_invitations.public_id` (`inv_01...`) as the canonical identifier, with positive numeric ids accepted only as v1 transition compatibility.
Invitation accept tokens remain opaque credentials and are not management route ids. New tokens use `inv_...|plainTextToken`; legacy numeric `id|plainTextToken` tokens remain accepted during the v1 transition.

`GET /api/v1/tenant/members`

current tenant の user 一覧を返す。role order は owner、admin、member。

Response: `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Owner User",
      "email": "owner@example.test",
      "role": "owner",
      "account_status": "active",
      "is_email_verified": true,
      "email_verified_at": "2026-05-13T10:00:00+00:00"
    }
  ]
}
```

`GET /api/v1/tenant/invitations`

current tenant の invitation 一覧を返す。plain token / token hash は返さない。

Response: `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "public_id": "inv_01HX0000000000000000000000",
      "email": "invitee@example.test",
      "role": "member",
      "status": "pending",
      "invited_by_user_id": 1,
      "invited_by_user_public_id": "usr_01HX0000000000000000000000",
      "accepted_user_id": null,
      "accepted_user_public_id": null,
      "expires_at": "2026-05-20T01:00:00+00:00",
      "accepted_at": null,
      "revoked_at": null,
      "created_at": "2026-05-13T01:00:00+00:00"
    }
  ]
}
```

`POST /api/v1/tenant/invitations`

tenant member invitation を作成する。invite token は `tenant_member_invitations.token_hash` に sha256 hash のみ保存し、plain token は response で 1 回だけ返す。同じ plain token は invitee email 宛の `TenantMemberInvitationNotification` でも送る。新規 token は `inv_...|plainTextToken` を返す。client は token 全体を opaque credential として扱い、route id として再利用しない。TTL は 7 日。legacy numeric `id|plainTextToken` token は v1 transition 中の accept 互換として維持する。

Request:

```json
{
  "email": "invitee@example.test",
  "role": "member"
}
```

Validation:

- `email`: required email, max 255, users.email に未登録。trim / lowercase に正規化する。
- `role`: required, `owner`, `admin`, `member`。admin は `owner` role を招待できない。
- 同一 tenant / email の未承認かつ未失効 pending invitation がある場合は `422 Unprocessable Entity`。

Response: `201 Created`

```json
{
  "data": {
    "id": 1,
    "public_id": "inv_01HX0000000000000000000000",
    "email": "invitee@example.test",
    "role": "member",
    "status": "pending",
    "invited_by_user_id": 1,
    "invited_by_user_public_id": "usr_01HX0000000000000000000000",
    "accepted_user_id": null,
    "accepted_user_public_id": null,
    "expires_at": "2026-05-20T01:00:00+00:00",
    "accepted_at": null,
    "revoked_at": null,
    "created_at": "2026-05-13T01:00:00+00:00",
    "invite_token": "inv_01HX0000000000000000000000|plain-invite-token"
  }
}
```

- 作成成功時、invitee email へ tenant name、inviter name、role、plain invite token、expiration を含む mail notification を送る。
- plain invite token は response と notification だけに露出し、DB、audit metadata、list response、logs には保存しない。

`POST /api/v1/tenant/invitations/accept`

public endpoint。valid invitation token で新規 user を作成し、作成 user 用 Bearer token を発行する。既存 user email の tenant 紐付けは初期 baseline では対応しない。

Request:

```json
{
  "token": "inv_01HX0000000000000000000000|plain-invite-token",
  "name": "Invited Member",
  "password": "strong-password",
  "password_confirmation": "strong-password"
}
```

Validation:

- `token`: required string, max 2048。pending / not expired / not revoked / not accepted の invitation token のみ有効。
- `name`: required string, max 255。trim して保存する。
- `password`: required string, min 8, max 1024, confirmed。

Response: `201 Created`

`POST /api/v1/auth/login` と同じ `AuthLogin` payload を返す。`data.user.role` は invitation の role。
作成 user の `data.user.account_status` は `active`。

- invalid / expired / revoked / accepted token は `422 Unprocessable Entity`。
- invitation email と同じ users.email が既に存在する場合は `422 Unprocessable Entity`。
- 作成後に email verification notification を送る。verification 未完了でも tenant-invite token は発行する。
- rate limit 超過は `429 Too Many Requests`。

`DELETE /api/v1/tenant/invitations/{invitation}`

pending invitation を revoke する。`{invitation}` は `inv_01...` を正とし、numeric invitation id は v1 transition 互換としてだけ受け付ける。row は削除せず `revoked_at` を保存する。accepted invitation は `422 Unprocessable Entity`。

Response: `204 No Content`

`PATCH /api/v1/tenant/members/{member}/role`

current tenant member の role を更新する。`{member}` は `usr_01...` を正とし、numeric user id は v1 transition 互換としてだけ受け付ける。

Request:

```json
{
  "role": "admin"
}
```

Validation:

- `role`: required, `owner`, `admin`, `member`。
- manager 自身の role は変更不可。
- admin は `owner` role を付与できず、既存 owner も変更できない。
- tenant は少なくとも 1 人の owner を維持する。

Response: `200 OK`

```json
{
  "data": {
    "id": 2,
    "name": "Member User",
    "email": "member@example.test",
    "role": "admin",
    "account_status": "active",
    "is_email_verified": true,
    "email_verified_at": "2026-05-13T10:00:00+00:00"
  }
}
```

`PATCH /api/v1/tenant/members/{member}/account-status`

current tenant member の `account_status` を更新する。`{member}` は `usr_01...` を正とし、numeric user id は v1 transition 互換としてだけ受け付ける。Bearer token / tenant context / `manage-tenant-members` guard / tenant security action rate limit を要求する。

`disabled` は tenant manager による可逆的な管理停止、`suspended` は security / policy hold として扱う。どちらも login token 発行と protected API access を拒否する。`active` への reactivation は新 token を発行せず、email verification state も変更しない。

Request:

```json
{
  "account_status": "suspended",
  "reason": "Suspected credential compromise"
}
```

Validation:

- `account_status`: required, `active`, `disabled`, `suspended`。
- `reason`: optional string, max 500。trim し、security event metadata にだけ保存する。response には返さない。
- manager 自身の status は変更不可。
- admin は owner account を変更できない。owner は他 owner を変更できる。
- owner を `disabled` / `suspended` にする場合、tenant に他の active owner が少なくとも 1 人必要。

Success behavior:

- 成功時は対象 user の Bearer token をすべて削除する。reactivation でも古い token を復活させないため削除する。
- 成功時は対象 user の `secret_unlock_tokens` も削除する。`secret_unlock_password` hash は変更しない。
- `tenant_id`、`role`、memory / category ownership、pending email、email verification state、account password は変更しない。
- `auth.account_status.change` security event を保存する。metadata には manager role、target user id / public id、target role、previous / new account status、任意 reason を含める。

Response: `200 OK`

```json
{
  "data": {
    "id": 2,
    "public_id": "usr_01HX0000000000000000000000",
    "name": "Member User",
    "email": "member@example.test",
    "role": "member",
    "account_status": "suspended",
    "is_email_verified": true,
    "email_verified_at": "2026-05-13T10:00:00+00:00"
  }
}
```

- malformed / wrong-prefix / missing / outside-tenant `{member}` は `404 Not Found`。
- self target、owner boundary、last active owner violation は `422 Unprocessable Entity`。
- tenant security action rate limit 超過は `429 Too Many Requests`。

`DELETE /api/v1/tenant/members/{member}`

current tenant member の tenant access を revoke する。`{member}` は `usr_01...` を正とし、numeric user id は v1 transition 互換としてだけ受け付ける。user row は削除せず、`users.tenant_id = null`、`users.role = member` に戻し、対象 user の Bearer token をすべて削除する。

Response: `204 No Content`

- manager 自身は revoke 不可。
- admin は owner を revoke できない。
- tenant は少なくとも 1 人の owner を維持する。

`POST /api/v1/auth/account/export`

Implemented. Current authenticated user の account data export を同期 JSON で返す。Bearer token / tenant context / active account / current account password / account lifecycle rate limit を要求する。初期 implementation は current user が owner である memory / category / tag view に限定し、tenant-wide な他 user memory export は扱わない。

Request:

```json
{
  "current_password": "current-account-password",
  "include_secret": false
}
```

Validation:

- `current_password`: required string。current authenticated user の account password と照合する。
- `include_secret`: optional boolean。default `false`。
- `include_secret=true` の場合、current user 用の valid `X-Secret-Unlock` header が必要。account password だけでは secret memory 本文を bulk export しない。

Response: `200 OK`

```json
{
  "data": {
    "exported_at": "2026-05-15T01:00:00+09:00",
    "user": {
      "id": 1,
      "public_id": "usr_01HX0000000000000000000000",
      "name": "Owner User",
      "email": "owner@example.test",
      "role": "owner",
      "account_status": "active"
    },
    "tenant": {
      "id": 1,
      "public_id": "ten_01HX0000000000000000000000",
      "name": "分身AI",
      "slug": "bunshin-ai"
    },
    "categories": [],
    "tags": [],
    "memories": [
      {
        "id": 1,
        "public_id": "mem_01HX0000000000000000000000",
        "title": "放課後の教室",
        "body": "放課後の教室で友達と話した。",
        "visibility": "private",
        "tags": ["放課後", "友達"]
      },
      {
        "id": 2,
        "public_id": "mem_01HX0000000000000000000001",
        "visibility": "secret",
        "locked": true
      }
    ]
  }
}
```

- `include_secret=false` の場合、`visibility=secret` memory は `id` / `public_id` / `visibility` / `locked=true` の stub として返し、title / body / tags / metadata は返さない。
- `include_secret=true` かつ valid `X-Secret-Unlock` の場合、current user の secret memory も通常 memory と同じ export item として返す。
- top-level `tags` は export される memory content に紐づく tag だけを返す。default export では secret-only tag も除外し、secret unlock 済み export の場合だけ secret memory tag を含める。
- export は user / tenant / token / memory / category / tag を変更しない。
- 成功 / failure は `auth.account_export.request` security event に記録する。export bundle、memory body、plain password、plain token、secret unlock token は保存しない。
- missing / invalid Bearer token は `401 Unauthorized`。tenant context missing は `403 Forbidden`。
- invalid current password または invalid secret unlock token は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。

`DELETE /api/v1/auth/account`

Implemented. Current authenticated user の self-service account deletion を実行する。Bearer token / tenant context / active account / current account password / exact confirmation / account lifecycle rate limit を要求する。secret memory の削除は内容を返さない destructive operation なので、secret unlock token は要求しない。

Request:

```json
{
  "current_password": "current-account-password",
  "confirmation": "DELETE",
  "reason": "No longer using the service"
}
```

Validation:

- `current_password`: required string。current authenticated user の account password と照合する。
- `confirmation`: required string, exact `DELETE`。
- `reason`: optional string, max 500。trim し、security event metadata にだけ保存する。
- tenant の last active owner は deletion 不可。先に別 active owner を作るか、将来の tenant deletion flow を使う。

Success behavior:

- user row は物理削除せず、`deleted_at` / `anonymized_at` を保存し、email / name / pending email / password を anonymize / invalidate する。
- `account_status` は non-authenticating state にし、`tenant_id` は null、`role` は `member` に戻す。
- current user の Bearer token と secret unlock token は全て削除する。
- current user が owner の memories は secret を含めて soft delete し、`memory_tag` pivot を detach する。
- current user が owner の categories は削除する。tenant tags は active memory から未参照になったものだけ prune する。
- current user が作成した pending invitations は revoke する。accepted invitation history と security events は audit retention のため保持する。
- `auth.account.delete` security event を保存する。plain password、plain token、secret memory content、old email は metadata に保存しない。

Response: `204 No Content`

- missing / invalid Bearer token は `401 Unauthorized`。tenant context missing は `403 Forbidden`。
- invalid current password、invalid confirmation、last active owner violation は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。

`POST /api/v1/tenant/export`

Implemented. Current tenant の tenant-wide export を同期 JSON で返す。Bearer token / tenant context / active account / owner role / current account password / tenant lifecycle rate limit を要求する。初期 contract では tenant owner だけが使える。admin は member 管理ができても tenant lifecycle export はできない。

Request:

```json
{
  "current_password": "current-account-password"
}
```

Validation:

- `current_password`: required string。current authenticated owner の account password と照合する。

Response: `200 OK`

```json
{
  "data": {
    "exported_at": "2026-05-15T07:00:00+09:00",
    "tenant": {
      "id": 1,
      "public_id": "ten_01HX0000000000000000000000",
      "name": "分身AI",
      "slug": "bunshin-ai",
      "plan_key": "free",
      "subscription_status": "active"
    },
    "members": [
      {
        "id": 1,
        "public_id": "usr_01HX0000000000000000000000",
        "name": "Owner User",
        "email": "owner@example.test",
        "role": "owner",
        "account_status": "active",
        "is_email_verified": true
      }
    ],
    "invitations": [],
    "quota": {
      "members_count": 1,
      "active_memories_count": 0,
      "categories_count": 0
    },
    "memory_inventory": [
      {
        "owner_user_public_id": "usr_01HX0000000000000000000000",
        "visibility": "private",
        "count": 12
      }
    ],
    "security_event_summary": [
      {
        "event_type": "auth.login",
        "outcome": "success",
        "count": 4,
        "last_seen_at": "2026-05-15T06:00:00+09:00"
      }
    ]
  }
}
```

- tenant metadata、member roster、invitation history、plan / subscription state、quota counts、memory inventory aggregates、security event summary を返す。
- memory inventory は owner / visibility / category / period などの aggregate に限定する。
- memory `title`、`body`、`metadata`、tags、他 user memory に紐づく category names、secret unlock token、Bearer token、password hash、raw security event metadata、IP address、user agent は返さない。
- current owner 自身の memory content も tenant-wide export には含めない。自分の memory content は `POST /api/v1/auth/account/export` を使う。
- `visibility=secret` content は tenant-wide export では返さない。tenant owner の Bearer token、account password、または tenant lifecycle export 権限だけでは、他 user の secret memory を bulk read できない。
- export は user / tenant / token / memory / category / tag / invitation / security event を変更しない。
- 成功 / failure は `auth.tenant_export.request` security event に記録する。export bundle、memory content、plain password、plain token、secret unlock token、raw audit metadata は保存しない。
- missing / invalid Bearer token と archived tenant の既存 Bearer token は `401 Unauthorized`。tenant context missing、account inactive、owner role 以外は `403 Forbidden`。
- invalid current password は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。

`POST /api/v1/tenant/archive`

Implemented. Current tenant の archive を request する。初期 contract は archive-first で、即時 hard delete は行わない。Bearer token / tenant context / active account / owner role / current account password / exact confirmation / tenant lifecycle rate limit を要求する。

Request:

```json
{
  "current_password": "current-account-password",
  "confirmation": "ARCHIVE bunshin-ai",
  "reason": "No longer using this tenant"
}
```

Validation:

- `current_password`: required string。current authenticated owner の account password と照合する。
- `confirmation`: required string, exact `ARCHIVE <tenant_slug>`。
- `reason`: optional string, max 500。trim し、`tenants.archive_reason` と security event metadata に保存する。

Success behavior:

- `tenants.archived_at`、`archived_by_user_id`、optional `archive_reason`、`deletion_requested_at`、`scheduled_deletion_at` を保存する。初期 scheduled deletion window は 30 日。
- local `subscription_status` は `canceled`、`subscription_ends_at` は archive time にする。billing provider cancellation handling は implemented。archive は local-first のまま維持し、linked provider subscription の即時 cancellation は archive transaction 後の side effect として扱う。provider failure は local archive を rollback / reactivate しない。cancellation failure triage は `docs/operations/tenant_archive_billing_cancellation_failure_runbook.md` に従う。dedicated retry command は decision 0031 により v1 では実装しない。
- v1 の archive request payload は provider id、refund flag、proration flag、period-end cancellation flag、cancellation date、refund / dispute request intake fields を受け付けない。初期 provider action は linked provider subscription の即時 cancellation のみで、自動 refund、credit、proration、invoice finalization、dunning、dispute、period-end cancellation は decision 0032 により product / finance / legal policy まで扱わない。customer-visible request intake は decision 0033 により product backend では扱わない。
- tenant 所属 user の Bearer token と secret unlock token を全て削除する。
- pending tenant invitations を revoke する。
- archived tenant の login は `403 Forbidden`、protected API access は token guard で `401 Unauthorized` として拒否する。
- memories / categories / tags / users / accepted invitations / security events は archive 直後には削除しない。retention window 中の復元可能性と audit / billing context を優先する。
- permanent purge は internal command として実装済み。purge job は retention window 後に memory content と tenant-owned operational data を消すが、audit retention policy に必要な tenant tombstone / scrubbed security records は保持する。
- `auth.tenant.archive` security event を保存する。provider cancellation check は separate `billing.subscription_cancel.request` security event を保存する。plain password、plain token、secret memory content、export bundle、provider customer id、provider subscription id、provider price id、raw provider response は metadata に保存しない。

Response: `202 Accepted`

```json
{
  "message": "Tenant archive has been scheduled.",
  "data": {
    "tenant_public_id": "ten_01HX0000000000000000000000",
    "archived_at": "2026-05-15T07:00:00+09:00",
    "scheduled_deletion_at": "2026-06-14T07:00:00+09:00",
    "billing_provider_cancellation": {
      "status": "succeeded",
      "provider": "stripe"
    }
  }
}
```

- `billing_provider_cancellation.status` は `succeeded`、`skipped`、`requires_operator_review`。skipped / operator review では safe `reason` を返す。provider customer id、subscription id、price id、raw provider error は返さない。

- missing / invalid Bearer token と archived tenant の既存 Bearer token は `401 Unauthorized`。tenant context missing、account inactive、owner role 以外は `403 Forbidden`。
- invalid current password または invalid confirmation は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。

Internal tenant purge job

Implemented. Public HTTP API は追加しない。tenant archive で保存した `scheduled_deletion_at` 到達後に operator / scheduler が internal command として実行する。

Initial command contract:

- command name: `php artisan bunshin:purge-archived-tenants`
- options: `--dry-run`、`--limit=<n>`、任意 argument による single-tenant targeting by tenant public id or slug。
- eligibility: `archived_at is not null`、`scheduled_deletion_at <= now()`、`purged_at is null`。
- default retention source: persisted `scheduled_deletion_at`。archive から 30 日を再計算しない。
- dry-run: eligible tenant と削除 / scrub 対象件数を表示し、DB は変更しない。
- mutation run: tenant row を lock して eligibility を再確認し、tenant-owned content / credentials / invitations / user PII を削除または匿名化し、pre-existing tenant security events を scrub し、tenant row を tombstone 化して `purged_at` を保存する。
- idempotency: `purged_at` が入った tenant は skip する。partial failure で `purged_at` が入らなかった tenant は次回 retry 対象に残す。
- audit: `auth.tenant.purge` event を保存する。metadata は deleted memory count、deleted category count、deleted tag count、deleted invitation count、anonymized user count、scrubbed security event count など scrub-safe count に限定する。
- no public response schema: irreversible operation のため API client から直接実行できない。

Purge data rules:

- force delete tenant `memories` including soft-deleted and `visibility=secret` rows。
- delete `memory_tag` pivots、`categories`、`tags`。
- delete tenant users' `personal_access_tokens`、`secret_unlock_tokens`、`password_reset_tokens`、`sessions`。
- delete all `tenant_member_invitations` for the tenant。
- anonymize and detach tenant `users` instead of physical delete。
- scrub pre-existing tenant `security_events` by nulling `subject_email`、`ip_address`、`user_agent`、raw `metadata` while keeping event type / outcome / timestamp / FK。
- keep tenant tombstone row with `public_id`、archive / deletion timestamps、local subscription fields、`purged_at`; scrub `name`、`slug`、`archive_reason`。

Internal security event pruning job

Implemented command and scheduler. Public HTTP API は追加しない。`security_events` の v1 audit retention を internal command で実行する。方針は `docs/decisions/0027-security-event-pruning-policy.md` を正とする。

Initial command contract:

- command name: `php artisan bunshin:prune-security-events`
- options: `--dry-run`、`--limit=<n>`、任意 argument による tenant targeting by tenant public id or slug。
- default retention: 180 days, configured by `BUNSHIN_SECURITY_EVENT_RETENTION_DAYS`。
- null-tenant events: `tenant_id is null` and `created_at < cutoff`。
- non-purged tenant events: tenant exists with `purged_at is null` and event `created_at < cutoff`。
- purged tenant events: tenant exists with `purged_at < cutoff`; this includes scrubbed pre-existing rows and `auth.tenant.purge` rows。
- dry-run: candidate counts by bucket を表示し、DB は変更しない。subject email、IP address、user agent、raw metadata、secret content、token material は表示しない。
- mutation run: `security_events` rows だけを deterministic batch で削除する。tenant row、user row、memory/category/tag row は変更しない。
- idempotency: eligible rows がなくなった後の再実行は no-op。partial failure 後も同じ cutoff / limit で retry できる。
- scheduler: `routes/console.php` で tenant purge 後の `04:15 UTC` 日次実行として登録済み。default `--limit=5000`、production default enablement、output log、overlap controls、failure email hook は tenant purge scheduler と揃える。
- no self-audit: prune command 自体の実行 event は `security_events` に書かない。

External logging/search integration

Designed but not implemented. Public HTTP API は追加しない。長期 audit search、analytics、compliance archive、support investigation が具体要件になった場合だけ、`docs/decisions/0028-external-logging-search-integration.md` に従って internal pipeline を追加する。

- primary DB の `security_events` は decision 0027 の retention / pruning を維持する。
- external sink は authorization、secret unlock、tenant boundary、user-visible data recovery の source of truth にしない。
- controller からの同期送信はしない。必要なら queue / outbox で sanitized projection を送る。
- projection は event type、outcome、timestamp、tenant public id、actor / subject public id、resource type / public id、scrub-safe enum、changed field names、aggregate counts だけを扱う。
- memory title/body、category/tag names、secret content、export bundle、plain credential、token material、signed URL secret、raw request payload、raw validation error、raw audit metadata、subject email、IP address、user agent は default では送らない。
- provider は未選定。OpenSearch、Datadog、CloudWatch、BigQuery などは deployment / retention / cost requirements が明確になってから選ぶ。

Billing provider integration

Data model、checkout / customer portal API endpoints、provider webhook receiver / subscription sync、provider-local reconciliation command / runbook、production env / frontend smoke runbook は実装済み。方針は `docs/decisions/0029-billing-provider-integration.md` を正とする。

- API runtime source of truth: local `tenants.plan_key` / `subscription_status` / `trial_ends_at` / `subscription_ends_at`。memory / category create request ごとに billing provider API を同期呼び出ししない。
- paid subscription sync source of truth: verified provider webhook、または operator が明示実行する reconciliation `--apply`。checkout success redirect、portal return、client callback は local plan / subscription state を変更しない。
- implemented tenant billing fields: `billing_provider`、`billing_customer_id`、`billing_subscription_id`、`billing_price_id`、`billing_cancel_at_period_end`、`billing_last_synced_at`。provider id は public tenant payload に default では返さない。
- implemented webhook idempotency table: `billing_webhook_events`。raw provider payload は保存せず、provider event id、event type、tenant match、payload hash、processing status、scrubbed error だけを保存する。
- checkout endpoint: `POST /api/v1/billing/checkout-sessions`。owner-only / verified email / rate limited。request は local `plan_key` だけを受け、provider price id は client から受けない。known plan / price mapping から hosted checkout URL を返す。tenant に provider customer id がない場合は provider customer を lazy create して `tenants.billing_provider` / `billing_customer_id` に保存するが、local `plan_key` / `subscription_status` は webhook まで変更しない。
- portal endpoint: `POST /api/v1/billing/portal-sessions`。owner-only / verified email / rate limited。既存 provider customer がある tenant だけ hosted customer portal URL を返す。local `plan_key` / `subscription_status` は変更しない。
- webhook endpoint: `POST /api/v1/billing/webhooks/{provider}`。public Bearer auth なし、provider signature verification 必須。duplicate provider event は idempotent no-op。checkout completion / subscription update / subscription delete は known price mapping の場合だけ local plan state に反映する。unknown tenant / customer / subscription / price id は paid entitlement を grant しない。
- reconciliation command: `php artisan bunshin:reconcile-billing-provider`。public HTTP endpoint は追加しない。default dry-run で local rows を変更せず drift を報告する。`--apply` は non-archived tenant、matching customer、known subscription、known price mapping、known provider status の場合だけ local tenant billing fields を同期する。
- tenant archive が成立した tenant は、後続 provider webhook が active subscription を示しても inactive のまま扱う。archive endpoint は linked provider subscription の immediate cancellation を試行するが、provider failure でも local archive を維持する。refund / credit / proration / invoice finalization / period-end cancellation は decision 0032 に従い扱わない。customer-visible dispute / refund request endpoint、status endpoint、request table は decision 0033 に従い v1 では追加しない。
- DB、logs、`security_events` には card data、billing address、tax id、raw customer email、raw webhook payload、raw provider response、signature secret、checkout / portal URL、provider API key を保存しない。`security_events` metadata と command output には provider customer id、provider subscription id、provider price id も保存 / 表示しない。

Frontend redirect URL handoff:

- source of truth: `docs/decisions/0034-billing-frontend-redirect-url-handoff.md`。
- `BUNSHIN_BILLING_CHECKOUT_SUCCESS_URL`、`BUNSHIN_BILLING_CHECKOUT_CANCEL_URL`、`BUNSHIN_BILLING_PORTAL_RETURN_URL` は provider に渡す server-side config。client request から success / cancel / return URL は受け取らない。
- checkout success route、checkout cancel route、portal return route は将来 product frontend の UX handoff route。backend callback endpoint ではなく、local plan / subscription state を変更しない。
- success route では `GET /api/v1/auth/me` など既存 authenticated API で tenant state を refresh し、webhook 未反映なら pending 表示にする。provider session id、redirect query、portal return visit は entitlement grant に使わない。
- cancel route は non-destructive な checkout cancellation 表示だけを行い、portal return route は tenant state refresh だけを行う。
- manual smoke は `docs/operations/billing_provider_production_smoke_runbook.md` に従い、provider request に config URL が渡ること、redirect 後に frontend が plan mutation endpoint を呼ばないこと、verified webhook 後だけ local plan state が変わること、DB / logs / `security_events` に hosted URL・provider session id・raw redirect query・billing PII が残らないことを確認する。

`POST /api/v1/billing/checkout-sessions`

Request:

```json
{
  "plan_key": "pro"
}
```

Response: `201 Created`

```json
{
  "data": {
    "mode": "checkout",
    "provider": "stripe",
    "plan_key": "pro",
    "url": "https://provider.example/checkout/session",
    "tenant": {
      "public_id": "ten_01HX0000000000000000000000",
      "plan_key": "free",
      "subscription_status": "active",
      "has_active_plan": true
    }
  }
}
```

- billing disabled / provider missing / provider config missing は `503`。
- provider request failure は `502`。
- unknown plan mapping や different provider linkage は `422`。
- endpoint は `billing.checkout_session.create` security event を scrub-safe metadata だけで保存する。checkout URL、provider API key、provider price id、raw customer email は security event metadata に保存しない。

`POST /api/v1/billing/portal-sessions`

Response: `201 Created`

```json
{
  "data": {
    "mode": "portal",
    "provider": "stripe",
    "url": "https://provider.example/customer-portal/session",
    "tenant": {
      "public_id": "ten_01HX0000000000000000000000",
      "plan_key": "pro",
      "subscription_status": "active",
      "has_active_plan": true
    }
  }
}
```

- tenant に provider customer id がない場合や different provider linkage は `422`。
- billing disabled / provider missing / provider config missing は `503`。
- provider request failure は `502`。
- endpoint は `billing.portal_session.create` security event を scrub-safe metadata だけで保存する。portal URL、provider API key、raw customer email は security event metadata に保存しない。

`POST /api/v1/billing/webhooks/{provider}`

Provider webhook endpoint。Bearer token は不要で、configured provider と webhook signature verification を必須にする。現行実装は Stripe-compatible `Stripe-Signature` header を検証する。

Response: `200 OK`

```json
{
  "data": {
    "provider": "stripe",
    "event_type": "customer.subscription.updated",
    "processing_status": "processed"
  }
}
```

- `processing_status` は `processed`, `ignored`, `failed`, duplicate request では `duplicate`。
- missing / invalid signature、invalid payload は `400`。
- billing disabled / provider missing / webhook secret missing / unsupported provider は `503`。
- duplicate `provider_event_id` は accepted duplicate として `200` を返し、tenant state を再変更しない。
- verified checkout completion と subscription webhook は `billing_webhook_events` に provider event id、event type、tenant match、payload hash、processing status、scrubbed error だけを保存する。raw provider payload は保存しない。
- known price mapping がある場合だけ `tenants.plan_key` / `subscription_status` / provider linkage を更新する。
- unknown tenant / customer / subscription / price id、unknown provider status、archived tenant は paid entitlement を grant しない。
- sync result は `billing.webhook.sync` security event を scrub-safe metadata だけで保存する。provider customer id、provider subscription id、provider price id、raw payload、signature secret、checkout / portal URL、provider API key、raw customer email は metadata に保存しない。

`POST /api/v1/auth/logout`

current token だけを削除する。response body は返さない。

Response: `204 No Content`

- missing / invalid / revoked token は `401 Unauthorized`。
- logout 後、同じ token は protected endpoint に使えない。

`GET /api/v1/auth/tokens`

authenticated user が所有する Bearer token metadata の一覧を返す。plain token / token hash は返さない。他 user の token は同じ tenant でも返さない。

Response: `200 OK`

```json
{
  "data": [
    {
      "id": 10,
      "name": "login",
      "abilities": ["*"],
      "last_used_at": "2026-05-12T10:00:00+00:00",
      "expires_at": null,
      "created_at": "2026-05-12T09:55:00+00:00",
      "is_current": true
    }
  ]
}
```

- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。

`DELETE /api/v1/auth/tokens/{token}`

authenticated user が所有する token を 1 件削除する。response body は返さない。current token 自身を削除した場合、その token は以後使えない。

Response: `204 No Content`

- 他 user の token または存在しない token は `404 Not Found`。
- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。

`POST /api/v1/auth/tokens/revoke-all`

authenticated user が所有する全 token を削除する。current token も削除されるため、response 後は同じ Bearer token で protected endpoint にアクセスできない。同じ tenant の別 user の token は削除しない。

Response: `204 No Content`

- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。

`POST /api/v1/auth/tokens/rotate`

current token を削除し、同じ `name` / `abilities` / `expires_at` を引き継いだ新 token を発行する。plain token は response で 1 回だけ返す。

Response: `201 Created`

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "id|plain-text-token",
    "expires_at": null,
    "token": {
      "id": 11,
      "name": "login",
      "abilities": ["*"],
      "last_used_at": null,
      "expires_at": null,
      "created_at": "2026-05-12T10:05:00+00:00",
      "is_current": true
    }
  }
}
```

- missing / invalid / revoked token は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。
- rotate 後、旧 token は protected endpoint に使えない。

## Memory resource draft

```json
{
  "id": 1,
  "period_key": "high_school",
  "occurred_on": "2026-05-04",
  "title": "放課後の教室",
  "body": "放課後の教室で友達と話した。",
  "emotion_label": "普通",
  "emotion_intensity": 3,
  "visibility": "private",
  "category": {
    "id": 1,
    "public_id": "cat_01HX0000000000000000000000",
    "name": "学校"
  },
  "tags": ["放課後", "友達"],
  "created_at": "2026-05-04T00:00:00+09:00",
  "updated_at": "2026-05-04T00:00:00+09:00"
}
```

## List memories

`GET /api/v1/memories`

Authenticated user の `tenant_id` / `id` を `TenantUserContext` として使い、client から送られた `tenant_id` / `owner_user_id` は受け付けない。default list は `visibility=secret` を除外する。

Query parameters:

- `q`: nullable string, max 255。`title`, `body`, `tags.name`, `tags.normalized_name` を部分一致検索する。
- `period_key`: nullable, `childhood`, `elementary_school`, `junior_high`, `high_school`, `university`, `adult`。
- `category_id`: nullable public id string (`cat_01...`)。v1 transition 中は integer category id も互換として受け付ける。request user の tenant / owner 内の memory に紐づく category だけに絞り込む。境界外 category は空配列になる。
- `include_descendants`: nullable boolean。`category_id` 指定時、default `false`。`true` の場合は指定 category と、その descendants に紐づく memory も含める。指定 category が request user の tenant / owner 外の場合は空配列を返す。
- `visibility`: nullable, `private`, `shared`, `secret`。未指定時は `private` / `shared` のみ。`secret` は明示指定時だけ返す。

Response: `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "public_id": "mem_01HX0000000000000000000000",
      "period_key": "high_school",
      "occurred_on": "2010-07-15",
      "title": "放課後の教室",
      "body": "放課後の教室で友達と話した。",
      "emotion_label": "普通",
      "emotion_intensity": 3,
      "visibility": "private",
      "category": {
        "id": 1,
        "public_id": "cat_01HX0000000000000000000000",
        "name": "学校"
      },
      "tags": ["放課後", "友達"],
      "created_at": "2026-05-04T00:00:00+00:00",
      "updated_at": "2026-05-04T00:00:00+00:00"
    }
  ]
}
```

- 並び順は `updated_at` 降順、`id` 降順。
- 未認証 request は `401 Unauthorized`。
- filter shape が不正な request は `422 Unprocessable Entity`。

## Show memory

`GET /api/v1/memories/{memory}`

Authenticated user の `tenant_id` / `id` を `TenantUserContext` として使い、request user の context 外にある memory は存在していても `404 Not Found` として扱う。ID 明示取得のため、認可済み context 内の `visibility=secret` は返す。

Response: `200 OK`

```json
{
  "data": {
    "id": 1,
    "public_id": "mem_01HX0000000000000000000000",
    "period_key": "university",
    "occurred_on": "2017-02-14",
    "title": "失恋の日",
    "body": "長く付き合っていた人と別れた。",
    "emotion_label": "悲しい",
    "emotion_intensity": 5,
    "visibility": "secret",
    "category": {
      "id": 1,
      "public_id": "cat_01HX0000000000000000000000",
      "name": "人間関係"
    },
    "tags": ["恋愛"],
    "created_at": "2026-05-04T00:00:00+00:00",
    "updated_at": "2026-05-04T00:00:00+00:00"
  }
}
```

- 未認証 request は `401 Unauthorized`。
- context 外または存在しない memory id は `404 Not Found`。

## Update memory

`PATCH /api/v1/memories/{memory}`

Authenticated user の `tenant_id` / `id` を `TenantUserContext` として使い、request user の context 外にある memory は存在していても `404 Not Found` として扱う。ID 明示更新のため、認可済み context 内の `visibility=secret` も更新できる。

Partial update として、指定された field だけ validation / 更新する。`tags` は未指定なら変更せず、指定された場合は create API と同じ `TagNameNormalizer` で正規化して pivot を同期する。`tags: []` または `tags: null` は tag をすべて外す。

Request:

```json
{
  "period_key": "university",
  "occurred_on": "2017-02-14",
  "title": "失恋の日",
  "body": "長く付き合っていた人と別れた。",
  "emotion_label": "悲しい",
  "emotion_intensity": 5,
  "visibility": "private",
  "category_id": "cat_01HX0000000000000000000000",
  "tags": ["友達", "夏"],
  "metadata": {
    "client": "admin-edit"
  }
}
```

Validation:

- `body`: sometimes required string, trim 後 1 文字以上。
- `period_key`: sometimes nullable, `childhood`, `elementary_school`, `junior_high`, `high_school`, `university`, `adult`。
- `occurred_on`: sometimes nullable, `YYYY-MM-DD`。
- `title`: sometimes nullable string, max 255。
- `emotion_label`: sometimes nullable string, max 40。
- `emotion_intensity`: sometimes nullable integer, 1-5。
- `visibility`: sometimes required, `private`, `secret`, `shared`。
- `category_id`: sometimes nullable public id string (`cat_01...`)。v1 transition 中は integer category id も互換として受け付ける。request user の tenant / owner 内に存在する category だけ許可する。
- `tags`: sometimes nullable array, max 20 items, each trim 後 1-40 chars。
- `metadata`: sometimes nullable object。

Response: `200 OK`

```json
{
  "data": {
    "id": 1,
    "public_id": "mem_01HX0000000000000000000000",
    "period_key": "university",
    "occurred_on": "2017-02-14",
    "title": "失恋の日",
    "body": "長く付き合っていた人と別れた。",
    "emotion_label": "悲しい",
    "emotion_intensity": 5,
    "visibility": "private",
    "category": {
      "id": 1,
      "public_id": "cat_01HX0000000000000000000000",
      "name": "人間関係"
    },
    "tags": ["友達", "夏"],
    "created_at": "2026-05-04T00:00:00+00:00",
    "updated_at": "2026-05-04T00:00:00+00:00"
  }
}
```

- 未認証 request は `401 Unauthorized`。
- context 外または存在しない memory id は `404 Not Found`。
- payload shape や category boundary が不正な request は `422 Unprocessable Entity`。

## Delete memory

`DELETE /api/v1/memories/{memory}`

Authenticated user の `tenant_id` / `id` を `TenantUserContext` として使い、request user の context 外にある memory は存在していても `404 Not Found` として扱う。ID 明示削除のため、認可済み context 内の `visibility=secret` も削除できる。

削除は soft delete とし、削除前に `memory_tag` pivot は外す。削除済み memory は通常 list / detail から返らない。

Response: `204 No Content`

- 未認証 request は `401 Unauthorized`。
- context 外または存在しない memory id は `404 Not Found`。

## Category resource draft

管理画面モックアップの category table が必要とする `memory_count` と `archived` を含める。現 data model には archive 状態を持たないため、`archived` は初期実装では常に `false`。

```json
{
  "id": 1,
  "public_id": "cat_01HX0000000000000000000000",
  "parent_id": null,
  "parent_public_id": null,
  "name": "学校",
  "slug": "school",
  "sort_order": 2,
  "memory_count": 24,
  "archived": false,
  "created_at": "2026-05-04T00:00:00+00:00",
  "updated_at": "2026-05-04T00:00:00+00:00"
}
```

## Create memory

`POST /api/v1/memories`

Authenticated user の `tenant_id` / `id` を `TenantUserContext` として使い、client から送られた `tenant_id` / `owner_user_id` は受け付けない。
作成前に tenant の active plan と memory quota を確認する。quota は tenant-wide の active memory 数で判定し、soft deleted memory は count しない。

Request:

```json
{
  "period_key": "high_school",
  "occurred_on": "2010-07-15",
  "title": "放課後の教室",
  "body": "放課後の教室で友達と話した。",
  "emotion_label": "普通",
  "emotion_intensity": 3,
  "visibility": "private",
  "category_id": "cat_01HX0000000000000000000000",
  "tags": ["放課後", "友達"],
  "metadata": {
    "client": "admin-mock"
  }
}
```

Validation:

- `body`: required string, trim 後 1 文字以上。
- `period_key`: nullable, `childhood`, `elementary_school`, `junior_high`, `high_school`, `university`, `adult`。
- `occurred_on`: nullable, `YYYY-MM-DD`。
- `title`: nullable string, max 255。
- `emotion_label`: nullable string, max 40。
- `emotion_intensity`: nullable integer, 1-5。
- `visibility`: required, `private`, `secret`, `shared`。
- `category_id`: nullable public id string (`cat_01...`)。v1 transition 中は integer category id も互換として受け付ける。request user の tenant / owner 内に存在する category だけ許可する。
- `tags`: nullable array, max 20 items, each trim 後 1-40 chars。
- `metadata`: nullable object。

Tag normalization:

- 保存時は `TagNameNormalizer` で `name` / `normalized_name` を決める。
- 英数字とスペースの幅を正規化し、`normalized_name` は lowercase にする。
- 初期 alias は `ともだち` / `友人` -> `友達`、`なつ` -> `夏`。
- 正規化後に同じ tag は同一 tenant 内で 1 件に統合する。別 tenant の tag とは統合しない。

Response: `201 Created`

```json
{
  "data": {
    "id": 1,
    "public_id": "mem_01HX0000000000000000000000",
    "period_key": "high_school",
    "occurred_on": "2010-07-15",
    "title": "放課後の教室",
    "body": "放課後の教室で友達と話した。",
    "emotion_label": "普通",
    "emotion_intensity": 3,
    "visibility": "private",
    "category": {
      "id": 1,
      "public_id": "cat_01HX0000000000000000000000",
      "name": "学校"
    },
    "tags": ["放課後", "友達"],
    "created_at": "2026-05-04T00:00:00+00:00",
    "updated_at": "2026-05-04T00:00:00+00:00"
  }
}
```

- inactive subscription は `402 Payment Required`。
- tenant memory quota 超過は `422 Unprocessable Entity`。`errors.quota` と `errors.memories` を返す。

## Categories CRUD

All category endpoints require authentication. API は authenticated user の `tenant_id` / `id` を `TenantUserContext` として使い、client から送られた `tenant_id` / `owner_user_id` は受け付けない。

`GET /api/v1/categories`

Query parameters:

- `tree`: nullable boolean。default `false`。`true` の場合は root category だけを top-level に返し、各 category に `children` を含める。`false` / 未指定の場合は従来どおり flat list を返す。

Response: `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "public_id": "cat_01HX0000000000000000000000",
      "parent_id": null,
      "parent_public_id": null,
      "name": "家族",
      "slug": "family",
      "sort_order": 1,
      "memory_count": 12,
      "archived": false,
      "children": [
        {
          "id": 2,
          "public_id": "cat_01HX0000000000000000000001",
          "parent_id": 1,
          "parent_public_id": "cat_01HX0000000000000000000000",
          "name": "父",
          "slug": "father",
          "sort_order": 1,
          "memory_count": 4,
          "archived": false,
          "children": []
        }
      ],
      "created_at": "2026-05-04T00:00:00+00:00",
      "updated_at": "2026-05-04T00:00:00+00:00"
    }
  ]
}
```

`children` は `tree=true` の場合だけ含める。flat list では含めない。

`POST /api/v1/categories`

作成前に tenant の active plan と category quota を確認する。quota は tenant-wide の category row 数で判定する。

Request:

```json
{
  "name": "学校",
  "slug": "school",
  "parent_id": null,
  "sort_order": 2
}
```

Validation:

- `name`: required string, trim 後 1-80 chars。
- `slug`: required string, trim 後 lowercase、`a-z`, `0-9`, `-` の kebab-case、1-80 chars、request user の tenant / owner 内で unique。
- `parent_id`: nullable public id string (`cat_01...`)。v1 transition 中は integer category id も互換として受け付ける。指定する場合は request user の tenant / owner 内に存在する root category のみ許可する。subcategory を parent にした 3 階層以上の作成は `422 Unprocessable Entity`。
- `sort_order`: nullable integer, 0-999999。未指定時は `0`。

Response: `201 Created`

```json
{
  "data": {
    "id": 1,
    "public_id": "cat_01HX0000000000000000000000",
    "parent_id": null,
    "parent_public_id": null,
    "name": "学校",
    "slug": "school",
    "sort_order": 2,
    "memory_count": 0,
    "archived": false,
    "created_at": "2026-05-04T00:00:00+00:00",
    "updated_at": "2026-05-04T00:00:00+00:00"
  }
}
```

- inactive subscription は `402 Payment Required`。
- tenant category quota 超過は `422 Unprocessable Entity`。`errors.quota` と `errors.categories` を返す。

`GET /api/v1/categories/{category}`

Request user の tenant / owner 内に存在する category だけ返す。境界外 category は `404 Not Found`。

`PATCH /api/v1/categories/{category}`

Partial update。`name`, `slug`, `parent_id`, `sort_order` は指定された field だけ validation する。`slug` uniqueness は request user の tenant / owner 内で判定し、対象 category 自身は除外する。`parent_id` は `cat_01...` を正とし、v1 transition 中は integer category id も互換として受け付ける。request user の tenant / owner 内の root category だけを許可し、自己参照、循環、3 階層以上を作らない。children を持つ category を subcategory に変更する request は `422 Unprocessable Entity`。境界外 category は `404 Not Found`。

`DELETE /api/v1/categories/{category}`

Request user の tenant / owner 内に存在する category だけ削除する。削除前に、この category を参照する memory の `category_id` は `null` にする。境界外 category は `404 Not Found`。

children を持つ category の削除は `422 Unprocessable Entity` とし、先に children を移動または削除させる。この場合、対象 category、child category、memory の category 紐付けは変更しない。

```json
{
  "message": "子カテゴリを持つカテゴリは削除できません。",
  "errors": {
    "children": [
      "子カテゴリを移動または削除してから、カテゴリを削除してください。"
    ]
  }
}
```

## Tags list

All tag endpoints require authentication. API は authenticated user の `tenant_id` を `TenantUserContext` として使い、client から送られた `tenant_id` は受け付けない。

`GET /api/v1/tags`

Response: `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "name": "友達",
      "normalized_name": "友達",
      "usage_count": 18
    }
  ]
}
```

- tag は request user の tenant 内に存在するものだけ返す。
- `usage_count` は `memory_tag` の紐づき件数から算出する。
- 初期実装の並び順は `usage_count` 降順、`name` 昇順。

## Memory Space

All memory-space endpoints require authentication.

`GET /api/v1/memory-space`

記憶の海 / 宇宙画面用の read model を返す。通常は `visibility=secret` の memory 本文、title、tag を返さない。

Query parameters:

- `period_key`: nullable, `childhood`, `elementary_school`, `junior_high`, `high_school`, `university`, `adult`。
- `category_id`: nullable public id string (`cat_01...`)。v1 transition 中は integer category id も互換として受け付ける。request user の tenant / owner 内の category だけ対象にする。境界外 category は memories / secret count を空扱いにする。
- `include_descendants`: nullable boolean。default `true`。
- `include_secret`: nullable boolean。default `false`。

Headers:

- `X-Secret-Unlock`: nullable。`POST /api/v1/secret-unlocks` で発行された unlock token。`include_secret=true` と valid token が揃った場合だけ secret memory を含める。

Response: `200 OK`

```json
{
  "data": {
    "categories": [
      {
        "id": 1,
        "public_id": "cat_01HX0000000000000000000000",
        "parent_id": null,
        "parent_public_id": null,
        "name": "音楽",
        "slug": "music",
        "sort_order": 1,
        "memory_count": 6,
        "locked_secret_count": 1,
        "children": [
          {
            "id": 2,
            "public_id": "cat_01HX0000000000000000000001",
            "parent_id": 1,
            "parent_public_id": "cat_01HX0000000000000000000000",
            "name": "Mr.Children",
            "slug": "mrchildren",
            "sort_order": 1,
            "memory_count": 3,
            "locked_secret_count": 0,
            "children": []
          }
        ]
      }
    ],
    "memories": [
      {
        "id": 10,
        "public_id": "mem_01HX0000000000000000000000",
        "category_id": 1,
        "category_public_id": "cat_01HX0000000000000000000001",
        "period_key": "high_school",
        "occurred_on": null,
        "title": "Tomorrow never knowsを初めて聴いた日",
        "body": "高校の帰り道...",
        "emotion_label": "感動",
        "emotion_intensity": 5,
        "emotion_scores": {
          "感動": 92
        },
        "importance_score": 0.95,
        "beliefs": ["音楽は人生を変える"],
        "chains": [],
        "tags": ["青春"],
        "visibility": "private"
      }
    ],
    "periods": [
      {
        "key": "high_school",
        "label": "高校"
      }
    ],
    "secret": {
      "locked": true,
      "locked_count": 4,
      "unlock_expires_at": null
    }
  }
}
```

- `categories` は request context 内の root category を top-level に返し、`children` に descendant categories を含める。
- unlock なし、または invalid / expired unlock token の場合、`memory_count` は現在の `period_key` filter に一致する `private` / `shared` memory の category subtree aggregate count。
- unlock なし、または invalid / expired unlock token の場合、`locked_secret_count` は現在の `period_key` filter に一致する `secret` memory の category subtree aggregate count。
- `include_secret=true` かつ valid `X-Secret-Unlock` がある場合、`memory_count` は返却対象 memory 全体を数え、`locked_secret_count` は `0` になる。
- `memories` は通常 request context 内の `private` / `shared` memory だけを返す。`include_secret=true` かつ valid `X-Secret-Unlock` がある場合だけ `secret` memory も含める。
- `period_key` / `category_id` / `include_descendants` filter を適用する。
- `periods` は固定 period key と表示 label の一覧。
- `secret.locked_count` は現在の memory-space filter に一致する secret memory count。
- `include_secret=true` の場合でも、追加 unlock token が不正または期限切れなら secret memory は返さず `secret.locked=true` の summary に留める。
- valid unlock token で secret memory を返している場合は `secret.locked=false` / `secret.locked_count=0` / `secret.unlock_expires_at=<token expiry>` を返す。
- 未認証 request は `401 Unauthorized`。
- filter shape が不正な request は `422 Unprocessable Entity`。

`POST /api/v1/secret-unlocks`

User scoped unlock password を検証し、短時間有効な unlock token を返す。unlock password は `users.secret_unlock_password` に hash として保存する専用 credential で、account password hash は unlock 判定に使わない。

Request:

```json
{
  "password": "unlock password"
}
```

Response: `201 Created`

```json
{
  "data": {
    "unlock_token": "id|opaque-token",
    "expires_at": "2026-05-05T00:45:00+09:00"
  }
}
```

frontend は `GET /api/v1/memory-space?include_secret=1` に `X-Secret-Unlock: <unlock_token>` を付ける。

- token は `secret_unlock_tokens` に sha256 hash として保存し、plain text token は response で 1 回だけ返す。
- TTL は初期実装では 15 分。
- wrong password は `422 Unprocessable Entity` とし、`password` validation error を返す。
- unlock password 未設定 user も `422 Unprocessable Entity` とし、unlock token は発行しない。
- 未認証 request は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。

`PUT /api/v1/secret-unlock-password`

Dedicated unlock password を初回設定または変更する。account password の確認は常に必須。既に unlock password が設定済みの場合は、現在の unlock password も確認する。recovery / forced rotation flow はこの endpoint には含めず、下記の別 endpoint として扱う。

Request:

```json
{
  "account_password": "current account password",
  "current_password": "current unlock password, required when already configured",
  "password": "new unlock password",
  "password_confirmation": "new unlock password"
}
```

Response: `200 OK`

```json
{
  "data": {
    "has_secret_unlock_password": true,
    "mode": "set"
  }
}
```

- `mode` は初回設定時 `set`、変更時 `changed`。
- `password` は 8-1024 文字で、`password_confirmation` と一致する必要がある。
- `password` は request の `account_password` と同じ値にできない。
- 変更時は `current_password` が必須で、既存の dedicated unlock password と一致する必要がある。
- 変更時は新しい `password` を `current_password` と同じ値にできない。
- 成功時は既存の `secret_unlock_tokens` を削除し、発行済み unlock token を失効させる。
- invalid account password は `422 Unprocessable Entity` とし、`account_password` validation error を返す。
- invalid current unlock password は `422 Unprocessable Entity` とし、`current_password` validation error を返す。
- 未認証 request は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。

`POST /api/v1/secret-unlock-password/recovery/request`

Dedicated unlock password を忘れた user が recovery link を request する。実装済み。Bearer token、tenant context、active account、verified email、current account password を全て要求する。成功しても password hash は変更せず、既存 `secret_unlock_tokens` も維持する。初期実装の signed recovery link は 30 分有効。

Request:

```json
{
  "account_password": "current account password"
}
```

Response: `202 Accepted`

```json
{
  "message": "Secret unlock password recovery link has been sent."
}
```

- `account_password`: required string, max 1024。
- email 未検証 user は `403 Forbidden` とし、recovery link を送らない。
- invalid account password は `422 Unprocessable Entity` とし、`account_password` validation error を返す。
- request 成功時は `auth.secret_unlock_password_recovery.request` security event を `requested` として保存する。email 未検証または invalid account password で拒否した場合は `failure` と machine-readable `metadata.reason` を保存する。plain password と signed URL の secret は保存しない。
- 未認証 request は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。
- rate limit 超過は `429 Too Many Requests`。

`PUT /api/v1/secret-unlock-password/recovery/{id}/{hash}`

Email に送った signed recovery link を検証し、dedicated unlock password を reset する。実装済み。Bearer token は必須で、path の user と authenticated user が一致する場合だけ成功する。通常 change と異なり `current_password` は要求しない。

Request:

```json
{
  "account_password": "current account password",
  "password": "new unlock password",
  "password_confirmation": "new unlock password"
}
```

Response: `200 OK`

```json
{
  "data": {
    "has_secret_unlock_password": true,
    "mode": "recovered"
  }
}
```

- signed URL の期限は初期値 30 分とし、期限切れ / invalid signature は `403 Forbidden`。
- `account_password`: required string, max 1024。
- `password`: required string, min 8, max 1024, confirmed。
- `password` は request の `account_password` と同じ値にできない。
- `users.secret_unlock_password` が設定済みの場合、`password` は現在の dedicated unlock password と同じ値にできない。
- 成功時は `users.secret_unlock_password` の hash を更新し、対象 user の既存 `secret_unlock_tokens` を削除する。
- 成功時は `auth.secret_unlock_password_recovery.complete` security event を `success` として保存する。invalid signature / invalid hash / wrong user / email 未検証 / invalid account password / password reuse は `failure` と machine-readable `metadata.reason` を保存する。plain password と signed URL の secret は保存しない。
- Bearer token は revoke しない。account password reset / change と secret unlock password recovery は別 credential の操作として分ける。
- 未認証 request は `401 Unauthorized`。
- tenant を持たない authenticated user、path user と authenticated user が一致しない request、email 未検証 user は `403 Forbidden`。
- invalid account password または password reuse は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。

`POST /api/v1/tenant/members/{member}/secret-unlock-password/force-rotation`

Tenant manager が対象 member の dedicated unlock password を強制 reset する。実装済み。`{member}` は `usr_01...` を正とし、numeric user id は v1 transition 互換としてだけ受け付ける。manager は対象 user の secret unlock password を知る必要がなく、新しい password も設定しない。対象 user は次回 `PUT /api/v1/secret-unlock-password` の setup flow で新しい dedicated unlock password を設定する。

Request:

```json
{
  "reason": "user forgot unlock password"
}
```

Response: `200 OK`

```json
{
  "data": {
    "user_id": 2,
    "user_public_id": "usr_01HX0000000000000000000000",
    "has_secret_unlock_password": false,
    "mode": "forced_rotation"
  }
}
```

- `reason`: optional string, max 500。security event metadata に保存してよいが、plain password / token / secret memory 内容は保存しない。
- `manage-tenant-members` と同じ role boundary を使う。`owner` / `admin` は許可、`member` は拒否。`admin` は `owner` を対象にできない。
- acting user 自身を対象にする forced rotation は拒否し、self-service recovery を使わせる。
- 対象 user が同じ tenant に存在しない場合は `404 Not Found`。
- 成功時は対象 user の `users.secret_unlock_password` を `null` にし、対象 user の既存 `secret_unlock_tokens` を削除する。
- 成功時は `auth.secret_unlock_password_forced_rotation` security event を `success` として保存する。metadata には manager role、対象 user id / role、任意 reason を保存してよい。self target や owner boundary で拒否した場合は `failure` と machine-readable `metadata.reason` を保存する。
- 対象 user の Bearer token は revoke しない。account compromise 対応は account status / token revoke / password reset と組み合わせる。
- 未認証 request は `401 Unauthorized`。
- tenant を持たない authenticated user または権限不足は `403 Forbidden`。
- payload shape が不正な request は `422 Unprocessable Entity`。
- rate limit 超過は `429 Too Many Requests`。

## Initial endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/health` | API health |
| POST | `/auth/login` | issue Bearer token with email / password |
| POST | `/auth/password/forgot` | request password reset link |
| POST | `/auth/password/reset` | reset password with reset token |
| PUT | `/auth/password` | change current account password and revoke existing Bearer tokens |
| GET | `/auth/me` | current authenticated user / tenant / token context |
| POST | `/auth/logout` | revoke current Bearer token |
| GET | `/auth/tokens` | list current user's Bearer token metadata |
| DELETE | `/auth/tokens/{token}` | revoke one owned Bearer token |
| POST | `/auth/tokens/revoke-all` | revoke all current user's Bearer tokens |
| POST | `/auth/tokens/rotate` | rotate current Bearer token |
| GET | `/memories` | memory list |
| POST | `/memories` | create memory |
| GET | `/memories/{memory}` | memory detail |
| PATCH | `/memories/{memory}` | update memory |
| DELETE | `/memories/{memory}` | soft delete memory |
| GET | `/categories` | category list |
| POST | `/categories` | create category |
| GET | `/categories/{category}` | category detail |
| PATCH | `/categories/{category}` | update category |
| DELETE | `/categories/{category}` | delete category |
| GET | `/tags` | tag list |
| GET | `/memory-space` | memory space read model |
| POST | `/secret-unlocks` | issue short-lived secret unlock token |
| PUT | `/secret-unlock-password` | set or change dedicated secret unlock password |
| POST | `/secret-unlock-password/recovery/request` | request signed secret unlock password recovery link |
| PUT | `/secret-unlock-password/recovery/{id}/{hash}` | complete secret unlock password recovery |
| POST | `/tenant/members/{member}/secret-unlock-password/force-rotation` | force a tenant member to reset their unlock password |

## Secret visibility rule

- `GET /memories` は default で `visibility=secret` を返さない。
- `visibility=secret` は、認可済み user が明示的に `GET /memories?visibility=secret` または `GET /memories/{memory}` で対象 ID を指定した場合だけ返す。
- 記憶の海 / 宇宙画面では追加ルールとして password unlock 風の backend 認可を通すまで secret memory 本文・title・tag を返さない。
- `visibility=all` を後続で追加する場合も、`secret` を含めるには明示的な権限チェックを通す。
