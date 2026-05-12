# データモデル設計

## 基本方針

旧実装の `period`, `content`, `emotion`, `tags` 直書き構造から、検索と権限管理に耐える正規化モデルへ移行する。

## tables

### tenants

- `id`
- `name`
- `slug`
- `created_at`
- `updated_at`

### users

Laravel 標準 `users` を利用し、`tenant_id` を持たせる。

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

初期 baseline の TTL は 15 分。専用 unlock password 設定 UI が未実装のため、`POST /api/v1/secret-unlocks` では user の account password hash を検証に使う。専用 password / recovery / rotation は別 task で検討する。

### memories

- `id`
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
- `category.parent_id`: nullable integer, 同一 tenant / owner 内の root category のみ指定可
- `category.sort_order`: nullable integer, 0-999999
