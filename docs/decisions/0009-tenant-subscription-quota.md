# 0009: Tenant subscription and quota baseline

Date: 2026-05-13

## Status

Accepted and implemented.

## Context

分身AI backend は invite-only tenant onboarding、tenant role、member invitation、login / token lifecycle まで追加済みだが、SaaS 運用で必要になる plan / subscription gate がなかった。billing provider 接続はまだ不要だが、後から各 create API に散らして入れるより、tenant domain に先に最小 baseline を置く必要がある。

## Decision

- 初期 baseline では subscription / plan / billing status を `tenants` table に直接保存する。
- 追加 field は `plan_key`, `subscription_status`, `trial_ends_at`, `subscription_ends_at`。
- active plan は `subscription_status` が `active` または `trialing` で、期限切れでない場合だけとする。
- plan limit は `config/bunshin.php` の `bunshin.plans` に置く。
- 初期 plan は `free` と `pro`。`free` は `memories=1000`, `categories=100`、`pro` は unlimited。
- `TenantQuotaGuard` を `POST /api/v1/memories` と `POST /api/v1/categories` の作成直前に通す。
- inactive subscription は `402 Payment Required`、quota 超過は `422 Unprocessable Entity` とする。
- quota count は tenant-wide。memories は soft deleted row を除外し、categories は tenant 内 category row 数を数える。

## Consequences

- billing provider 接続なしでも、API は plan inactive / quota exceeded の挙動を先に固定できる。
- 現行の memories / categories は owner-scoped data API のまま維持するが、quota は tenant plan に紐づくため tenant-wide count になる。
- customer id / provider subscription id / webhook sync / entitlement detail は decision 0029 で設計済み。実装は次の billing schema task で追加する。
- 同時作成による quota race は初期 baseline では未対策。必要になったら DB lock または counter cache を追加する。
