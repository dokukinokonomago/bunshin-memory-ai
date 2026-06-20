# 0005: 記憶の海 / 宇宙画面を automation 対象に含める

## Status

Accepted

## Context

ユーザーが `memory_space (1).html` として、記憶を宇宙 / 海のように探索する frontend イメージを作成した。

この画面は mock data 上で以下の構造を前提にしている。

- 大カテゴリー
- サブカテゴリー
- 記憶
- 複数 emotion score
- 記憶の重み
- beliefs / chains
- tags

既存 backend は `categories -> memories` の 1 階層と、`period_key` / `occurred_on` による時間情報を持っている。ユーザーから、サブカテゴリーは system 側に追加してよいこと、年代はカテゴリーとは別軸で持たせること、`visibility=secret` の記憶はこの画面で password unlock 風に扱いたいことが示された。

## Decision

この automation の対象に、記憶の海 / 宇宙画面の設計、backend 改修、frontend 実装を含める。

既存の「本格 frontend app 化は非対象」という制約は、管理画面モックアップに対する制約として扱う。記憶の海 / 宇宙画面については、ユーザー向け探索画面として frontend 実装を正式 scope に入れる。

データ構造は以下を正とする。

- 大カテゴリー / サブカテゴリーは `categories.parent_id` による同一 table の階層で表現する。
- `memories.category_id` は原則として末端カテゴリー、つまりサブカテゴリーを指す。ただしサブカテゴリーなしのカテゴリー直下 memory も許容する。
- 年代はカテゴリー階層に混ぜず、`memories.period_key` と `memories.occurred_on` の時間軸として保持する。
- tags は横断的なラベルとして維持し、カテゴリーや年代の代替にしない。
- 表示上の `weight`、複数 emotion score、beliefs / chains、色、座標は visualization 用 payload / metadata として扱う。core 分類モデルと混ぜない。
- `visibility=secret` は通常の記憶の海 payload から除外する。secret memory を画面に出す場合は、Bearer token に加えて password unlock 風の追加認可を通す。

## Secret Unlock Direction

password unlock は frontend だけの演出にしない。backend が追加認可を検証する。

初期案:

- `GET /api/v1/memory-space` は通常、`visibility=secret` の memory 本文・title・tag を返さない。
- locked state として、カテゴリー別の `locked_secret_count` など秘匿内容を漏らさない aggregate だけ返す。
- `POST /api/v1/secret-unlocks` で user scoped unlock password を検証し、短時間だけ有効な unlock token を発行する。
- `GET /api/v1/memory-space?include_secret=1` は Bearer token と unlock token の両方が有効な場合だけ secret memory を含める。
- unlock token は短 TTL とし、frontend storage には長期保存しない。

password そのものを frontend localStorage に保存しない。2026-05-13 の方針決定と実装により、`POST /api/v1/secret-unlocks` は account password hash ではなく `users.secret_unlock_password` の専用 hash を検証する。2026-05-14 に `PUT /api/v1/secret-unlock-password` で専用 unlock password の setup / change API を追加済み。recovery / forced rotation contract は `docs/decisions/0019-secret-unlock-password-recovery-rotation.md` で決定済み。

## Consequences

- 最初の正式 backend task は `categories.parent_id` の migration / model / validation / tests 追加で、2026-05-05 に完了済み。
- その後、memory-space 用 read endpoint、secret unlock endpoint、frontend screen 実装の順に小さく進める。
- 既存 categories CRUD と admin mockup には後方互換を持たせる。`parent_id` 未指定なら root category として扱う。
- 既存 `period_key` は維持し、年代別 UI / API は別 task として設計する。
- smoke test 作成物の削除許可確認 task は未着手に残すが、新しいユーザー指示により優先度は下げる。

## Implementation Status

- 2026-05-05: `categories.parent_id` migration / model relation / create-update validation / Feature tests を追加済み。
- 2026-05-05: category tree response、descendant filter、memory-space read endpoint、secret unlock endpoint、frontend screen baseline を追加済み。
