# 0010: Security Events And Auth Rate Limits

Date: 2026-05-13

## Status

Accepted.

## Context

分身AI backend は token-first auth、invite-only tenant onboarding、tenant member management、password reset、subscription / quota guard まで追加済みだが、認証失敗や password reset request を追跡する security event baseline と、認証系 write endpoint の route-level rate limit がなかった。

## Decision

- Auth / security event は `security_events` table に append-only baseline として保存する。
- 初期 event type は `auth.login`、`auth.signup`、`auth.password_reset.request`、`auth.password_reset.complete`、`auth.password_change`、`auth.tenant_invitation.accept`、`auth.email_verification.request`、`auth.email_verification.complete`、`auth.email_change.request`、`auth.email_change.complete` とする。後続実装で `auth.secret_unlock_password_recovery.request`、`auth.secret_unlock_password_recovery.complete`、`auth.secret_unlock_password_forced_rotation`、`auth.account_status.change`、`auth.account_export.request`、`auth.account.delete`、`auth.tenant_export.request` も追加済み。tenant archive endpoint 実装で `auth.tenant.archive` を追加済み。
- event は `tenant_id`、`user_id`、`event_type`、`outcome`、`subject_email`、`ip_address`、`user_agent`、`metadata`、`created_at` を持つ。
- plain password、plain Bearer token、plain invite token、plain reset token は保存しない。
- `POST /api/v1/auth/signup`、`POST /api/v1/auth/login`、`POST /api/v1/auth/password/forgot`、`POST /api/v1/auth/password/reset`、`PUT /api/v1/auth/password`、`POST /api/v1/tenant/invitations/accept`、`POST /api/v1/auth/email/verification-notification`、`PUT /api/v1/auth/email` は named rate limiter を通す。
- 初期 rate limit は config 固定値とし、login 10/min、signup 5/min、password forgot 5/min、password reset 5/min、password change 5/min、tenant invitation accept 5/min、email verification resend 5/min、email change 5/min とする。後続実装で secret unlock password recovery request / completion は 5/min、tenant security action は 10/min、account lifecycle は 5/min、tenant lifecycle は 5/min を追加済み。account status 変更 API は tenant security action rate limit を使う。tenant export と tenant archive は tenant lifecycle rate limiter を使う。
- rate limit key は normalized email + IP、password change / email verification resend / email change は authenticated user id、tenant invitation accept は token value hash + IP を使う。

## Consequences

- 認証成功 / 失敗、signup invite failure、password reset request / complete、password change、tenant invitation accept、email verification request / complete、email change request / complete を DB で追跡できる。account status による login 拒否は `auth.login` failure として `metadata.reason=account_not_active` を保存する。
- rate limit 超過は Laravel throttle middleware により `429 Too Many Requests` を返す。
- throttle middleware で止まった request は controller に到達しないため、初期 baseline では security event を記録しない。
- token lifecycle、tenant member management の管理操作、memory / category write 操作の broader audit log 方針は decision 0026 で決定済みで、実装も完了済み。

## Next Task

secret unlock password recovery request / completion endpoint、tenant member forced rotation endpoint、prefixed ULID public id response baseline、memories / categories public id resolver implementation、first-party frontend request 移行、tenant member management route params の `usr_` public id lookup、tenant member invitation の `inv_` public id lookup、account status management API、account deletion / export 方針設計、self-service account export endpoint、self-service account deletion endpoint、tenant-wide export と tenant deletion/archive 方針設計は完了済み。tenant archive endpoint、tenant purge command、scheduler 登録、production runbook、broader audit log / admin impersonation 方針決定、broader audit logging 実装も完了済み。tenant member invitation delivery email / notification は実装済み。account status 変更 API の管理画面モックアップ接続要否は確認済みで、現行 mockup には対象導線がないため接続改修は不要。smoke test 作成 data の参照有無再確認は 2026-05-16 に完了済み。audit log pruning command の retention / execution 方針設計は完了済み。audit log pruning command、scheduler、schedule tests、operations runbook は実装済み。external logging/search integration は `docs/decisions/0028-external-logging-search-integration.md` で設計済みで、初期実装は deferred。billing provider integration scope と webhook handling は `docs/decisions/0029-billing-provider-integration.md` で設計済みで、billing provider data model migration / model support / tests、checkout / customer portal API、billing webhook receiver と signature verification / idempotency tests、provider-local reconciliation command / operations runbook、tenant archive billing provider cancellation handling は実装済み。tenant archive billing cancellation failure triage の operations runbook は追加済み。dedicated retry command は decision 0031 により v1 deferred。
