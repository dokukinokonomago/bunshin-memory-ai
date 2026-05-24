# 0012: Email Verification API

Date: 2026-05-13

## Status

Accepted.

## Context

Token-first auth baseline already supports invite-only signup, login, password reset, token lifecycle, tenant member invitation, subscription quota, and auth security events. Email verification was still missing, but login token issuance for unverified users is intentionally allowed until dangerous actions are separately gated.

## Decision

- Use Laravel `VerifyEmail` notification for email verification delivery.
- Verification links use `GET /api/v1/auth/email/verify/{id}/{hash}` as a temporary signed URL. Bearer token is not required because possession of the signed email link is the verification proof.
- The signed URL expiry uses `auth.verification.expire`, defaulting to 60 minutes.
- `hash` remains Laravel's standard `sha1(email)` check, and the signed URL protects the route parameters from tampering.
- Signup and tenant invitation accept send the verification notification after user creation while still returning a Bearer token.
- Resend uses protected `POST /api/v1/auth/email/verification-notification`, requires a valid Bearer token, verifies tenant context, and returns `202` when sent.
- Already verified users do not receive another notification and get `200 OK`.
- `AuthUser` / `TenantMember` payloads include `is_email_verified` and `email_verified_at`.
- Security events record `auth.email_verification.request` and `auth.email_verification.complete`. Plain tokens, passwords, and verification URL query values are not stored.
- Resend is rate limited with `bunshin.security.rate_limits.email_verification.per_minute`, default `5/min`.

## Consequences

- Email links work without a web session and remain compatible with token-first API clients.
- Login remains allowed before verification, but future dangerous actions can gate on `email_verified_at`.
- Invalid signature / hash attempts are visible in `security_events` without exposing secret material.

## Next Task

secret unlock password recovery request / completion endpoint、tenant member forced rotation endpoint、prefixed ULID public id response baseline、memories / categories public id resolver implementation、first-party frontend request 移行、tenant member management route params の `usr_` public id lookup、tenant member invitation の `inv_` public id lookup、account status management API、account deletion / export 方針設計、self-service account export endpoint、self-service account deletion endpoint は完了済み。tenant-wide export と tenant deletion/archive 方針設計、tenant archive endpoint、tenant purge command、scheduler 登録、production runbook も完了済み。broader audit log / admin impersonation 方針決定と broader audit logging 実装は完了済み。tenant member invitation delivery email / notification は実装済み。account status 変更 API の管理画面モックアップ接続要否は確認済みで、現行 mockup には対象導線がないため接続改修は不要。smoke test 作成 data の参照有無再確認は 2026-05-16 に完了済み。audit log pruning command の retention / execution 方針設計は完了済み。audit log pruning command、scheduler、schedule tests、operations runbook は実装済み。external logging/search integration は `docs/decisions/0028-external-logging-search-integration.md` で設計済みで、初期実装は deferred。billing provider integration scope と webhook handling は `docs/decisions/0029-billing-provider-integration.md` で設計済みで、billing provider data model migration / model support / tests、checkout / customer portal API、billing webhook receiver と signature verification / idempotency tests、provider-local reconciliation command / operations runbook、tenant archive billing provider cancellation handling は実装済み。tenant archive billing cancellation failure triage の operations runbook は追加済み。dedicated retry command は decision 0031 により v1 deferred。
