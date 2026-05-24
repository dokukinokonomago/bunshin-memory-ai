# 0006: Tenant Onboarding は Invite-Only で開始する

Date: 2026-05-12
Status: Accepted

## Context

SaaS baseline には新規 tenant と初期 owner user を作る flow が必要だが、public signup を開くには email verification、abuse 対策、rate limit、tenant slug 取得競合、利用規約同意などを同時に設計する必要がある。

## Decision

初期実装では public signup を採用せず、invite-only onboarding で開始する。

- `POST /api/v1/auth/signup` は public route だが、server 側 `BUNSHIN_ONBOARDING_INVITE_TOKEN` と request の `invite_token` が一致する場合だけ受け付ける。
- `BUNSHIN_ONBOARDING_INVITE_TOKEN` が未設定または空の場合、signup endpoint は `403 Forbidden` で閉じる。
- signup は tenant と initial owner user を同じ DB transaction で作成し、owner user に `tenant_id` を必ず設定する。
- 成功時は owner user 用の Bearer token を `name=signup` で発行し、plain token は response で 1 回だけ返す。
- 初期 baseline では `users.tenant_id` による 1 user 1 tenant を維持する。
- email verification 未完了 user への login token 発行は当面許可する。tenant 設定、member invitation、billing などの危険操作は後続 task で verified 必須化する。

## Consequences

- tenant 未所属 user を onboarding API から作らない。
- invite token を知る人だけが tenant / owner user を作れるため、public signup より小さい攻撃面で検証を進められる。
- 招待 token は共有 secret であり、個別招待、失効、招待履歴、member invite は別 task で設計する。
- public signup を将来許可する場合は、この decision を置き換え、email verification / rate limit / abuse 対策を同時に追加する。
