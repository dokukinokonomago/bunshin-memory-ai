# 0011: Product Policy Decisions

Date: 2026-05-13

## Status

Accepted.

## Context

`task_board.md` に残っていた人間判断が必要な論点について、2026-05-13 にユーザーが推奨方針どおり採用してよいと承認した。以後の backend task はこの decision を前提に進める。

## Decisions

- Secret unlock password は account password と分離し、専用 unlock password へ移行する。2026-05-13 の secret unlock task で `users.secret_unlock_password` の専用 hash 検証へ移行済み。2026-05-14 に setup / change API も追加済み。recovery / forced rotation contract は `docs/decisions/0019-secret-unlock-password-recovery-rotation.md` で決定済み。
- `users.tenant_id` による 1 user 1 tenant は当面維持する。複数 tenant 参加、既存 user 招待受諾、account merge が必要になった段階で `tenant_memberships` table を追加する。
- Tenant member invitation delivery は mail notification 化済み。plain invite token の 1 回 response は local testing / operator fallback 用に維持する。
- Public signup は当面許可せず、invite-only onboarding を継続する。public signup を開く場合は email verification、abuse 対策、rate limit、billing readiness と同時に再設計する。
- Email verification 未完了 user への login token 発行は当面許可する。ただし、将来は tenant 設定、member invitation、billing などの危険操作を verified 必須にする。
- Invalid credentials response は `401 Unauthorized` を維持する。email 存在有無は区別しない。
- Billing provider 接続は実装済み。provider-neutral data model、checkout / portal、webhook、reconciliation、archive cancellation は decisions 0029 / 0030 / 0031 を正とする。Automated refund / credit / proration / invoice finalization / dunning / dispute / period-end cancellation は decision 0032 により v1 deferred とし、customer-visible dispute / refund request intake は decision 0033 により v1 deferred とする。product / finance / legal policy なしに実装しない。
- Broader audit log は管理画面、課金、member management が本格化する前に追加する。初期保存期間は 180 日を目安にし、検索 API と外部 logging 基盤連携は後続 task で広げる。具体方針は `docs/decisions/0026-broader-audit-log-admin-impersonation.md` で決定済み。
- Memory-space payload で secret memory の locked aggregate は件数と大まかな存在だけ返す。title、body、tags、category、emotion は unlock 前に返さない。
- Smoke test で作成した `Smoke memory updated`、`Smoke Test Updated`、`memory-space-smoke@example.test`、`Smoke ...` 系 data、category id `4` / `5` は削除してよい。ただし、実行直前に参照 memory / 必要 fixture 有無を再確認する。
- Public id は prefixed ULID を採用する。例: `mem_01...`, `cat_01...`, `usr_01...`, `ten_01...`。request lookup migration 方針は `docs/decisions/0020-public-id-request-lookup.md` を正とする。

## Consequences

- secret unlock は account password 共用に戻さず、forced rotation は setup / change / recovery とは別 endpoint として維持する。
- Multi-tenant membership と既存 user invitation は独立した migration / model / API task として扱う。
- Smoke data cleanup は destructive operation なので、削除 task で read-only 確認をしてから実行する。2026-05-16 の再確認では local SQLite に対象 data が残っておらず、削除対象 0 件のため DB delete は実行していない。
- Public id baseline は実装済み。既存 integer id を内部 id として維持し、external API / frontend payload に prefixed ULID を追加した。route param や request field の public id lookup 移行方針も決定済みで、新規 client request は public id を正、integer id は v1 transition 互換とする。

## Next Task

secret unlock password recovery request / completion endpoint、tenant member forced rotation endpoint、prefixed ULID public id response baseline、memories / categories public id resolver implementation、first-party frontend request 移行、tenant member management route params の `usr_` public id lookup、tenant member invitation の `inv_` public id lookup、account status management API、account deletion / export 方針設計、self-service account export endpoint、self-service account deletion endpoint は完了済み。tenant-wide export と tenant deletion/archive 方針設計、tenant archive endpoint、tenant purge command、scheduler 登録、production runbook、broader audit log / admin impersonation 方針決定、broader audit logging 実装、tenant member invitation delivery email / notification も完了済み。account status 変更 API の管理画面モックアップ接続要否は確認済みで、現行 mockup には対象導線がないため接続改修は不要。smoke test 作成 data の参照有無再確認は 2026-05-16 に完了済み。audit log pruning command の retention / execution 方針設計は完了済み。audit log pruning command、scheduler、schedule tests、operations runbook は実装済み。external logging/search integration は `docs/decisions/0028-external-logging-search-integration.md` で設計済みで、初期実装は deferred。billing provider integration scope と webhook handling は `docs/decisions/0029-billing-provider-integration.md` で設計済みで、billing provider data model migration / model support / tests、checkout / customer portal API、billing webhook receiver と signature verification / idempotency tests、provider-local reconciliation command / operations runbook、tenant archive billing provider cancellation handling は実装済み。tenant archive billing cancellation failure triage の operations runbook は追加済み。dedicated retry command は decision 0031 により v1 deferred。automated billing adjustments は decision 0032 により v1 deferred。customer-visible billing dispute / refund request flow は decision 0033 により v1 deferred / support-only outside product backend。
