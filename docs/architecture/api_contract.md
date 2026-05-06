# API 契約

## 共通

- Base path: `/api/v1`
- Content-Type: `application/json`
- Protected endpoints require `Authorization: Bearer <plain_text_token>`。
- token は `personal_access_tokens` に sha256 hash として保存し、client には `id|plainTextToken` 形式を 1 回だけ返す。
- 初期実装の token 発行は server-side の artisan command を使う。管理画面接続用の public login / token issuance endpoint は置かない。
- 管理画面モックアップ接続検証の標準手順は `php artisan bunshin:issue-admin-token` で token を発行し、Settings の Bearer token に貼り付ける運用とする。
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

## Health

`GET /api/v1/health`

```json
{
  "service": "bunshin-memory-api",
  "status": "ok",
  "version": "0.1.0"
}
```

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
    "id": "cat_01HX...",
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
- `category_id`: nullable integer, request user の tenant / owner 内の memory に紐づく category id だけに絞り込む。境界外 category id は空配列になる。
- `include_descendants`: nullable boolean。`category_id` 指定時、default `false`。`true` の場合は指定 category と、その descendants に紐づく memory も含める。指定 category が request user の tenant / owner 外の場合は空配列を返す。
- `visibility`: nullable, `private`, `shared`, `secret`。未指定時は `private` / `shared` のみ。`secret` は明示指定時だけ返す。

Response: `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "period_key": "high_school",
      "occurred_on": "2010-07-15",
      "title": "放課後の教室",
      "body": "放課後の教室で友達と話した。",
      "emotion_label": "普通",
      "emotion_intensity": 3,
      "visibility": "private",
      "category": {
        "id": 1,
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
    "period_key": "university",
    "occurred_on": "2017-02-14",
    "title": "失恋の日",
    "body": "長く付き合っていた人と別れた。",
    "emotion_label": "悲しい",
    "emotion_intensity": 5,
    "visibility": "secret",
    "category": {
      "id": 1,
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
  "category_id": 1,
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
- `category_id`: sometimes nullable, request user の tenant / owner 内に存在する category id。
- `tags`: sometimes nullable array, max 20 items, each trim 後 1-40 chars。
- `metadata`: sometimes nullable object。

Response: `200 OK`

```json
{
  "data": {
    "id": 1,
    "period_key": "university",
    "occurred_on": "2017-02-14",
    "title": "失恋の日",
    "body": "長く付き合っていた人と別れた。",
    "emotion_label": "悲しい",
    "emotion_intensity": 5,
    "visibility": "private",
    "category": {
      "id": 1,
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
  "parent_id": null,
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
  "category_id": 1,
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
- `category_id`: nullable, request user の tenant / owner 内に存在する category id。
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
    "period_key": "high_school",
    "occurred_on": "2010-07-15",
    "title": "放課後の教室",
    "body": "放課後の教室で友達と話した。",
    "emotion_label": "普通",
    "emotion_intensity": 3,
    "visibility": "private",
    "category": {
      "id": 1,
      "name": "学校"
    },
    "tags": ["放課後", "友達"],
    "created_at": "2026-05-04T00:00:00+00:00",
    "updated_at": "2026-05-04T00:00:00+00:00"
  }
}
```

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
      "parent_id": null,
      "name": "家族",
      "slug": "family",
      "sort_order": 1,
      "memory_count": 12,
      "archived": false,
      "children": [
        {
          "id": 2,
          "parent_id": 1,
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
- `parent_id`: nullable integer。指定する場合は request user の tenant / owner 内に存在する category。初期実装では root / subcategory の 2 階層まで。
- `sort_order`: nullable integer, 0-999999。未指定時は `0`。

Response: `201 Created`

```json
{
  "data": {
    "id": 1,
    "parent_id": null,
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

`GET /api/v1/categories/{category}`

Request user の tenant / owner 内に存在する category だけ返す。境界外 category は `404 Not Found`。

`PATCH /api/v1/categories/{category}`

Partial update。`name`, `slug`, `parent_id`, `sort_order` は指定された field だけ validation する。`slug` uniqueness は request user の tenant / owner 内で判定し、対象 category 自身は除外する。`parent_id` は循環と 3 階層以上を作らない。境界外 category は `404 Not Found`。

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
- `category_id`: nullable integer, request user の tenant / owner 内の category id。境界外 category id は memories / secret count を空扱いにする。
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
        "parent_id": null,
        "name": "音楽",
        "slug": "music",
        "sort_order": 1,
        "memory_count": 6,
        "locked_secret_count": 1,
        "children": [
          {
            "id": 2,
            "parent_id": 1,
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
        "category_id": 1,
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

User scoped unlock password を検証し、短時間有効な unlock token を返す。初期 baseline では専用 unlock password 設定 UI が未実装のため、認証済み user の account password hash を検証に使う。

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
- 未認証 request は `401 Unauthorized`。
- tenant を持たない authenticated user は `403 Forbidden`。

## Initial endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/health` | API health |
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

## Secret visibility rule

- `GET /memories` は default で `visibility=secret` を返さない。
- `visibility=secret` は、認可済み user が明示的に `GET /memories?visibility=secret` または `GET /memories/{memory}` で対象 ID を指定した場合だけ返す。
- 記憶の海 / 宇宙画面では追加ルールとして password unlock 風の backend 認可を通すまで secret memory 本文・title・tag を返さない。
- `visibility=all` を後続で追加する場合も、`secret` を含めるには明示的な権限チェックを通す。
