# 記憶の海 / 宇宙画面設計

## 目的

ユーザーが自分の記憶を、カテゴリー階層、年代、感情、重み、タグを使って探索できる frontend 画面を作る。

参照 mockup は `docs/references/memory-space-screen/memory_space.html`。ただし mockup 内の直書き data は参考であり、実装の正は backend API とこの設計 doc に置く。

## 体験方針

- 大カテゴリーを大きな星団 / 海域として見せる。
- サブカテゴリーを大カテゴリー配下の中間ノードとして見せる。
- 記憶は末端ノードとして表示する。
- 年代はカテゴリーとは別軸にし、フィルター、タイムライン、レイヤー切替に使う。
- secret memory は最初から露出しない。locked state を見せ、password unlock 風の操作で追加認可できた場合だけ表示する。

## データ軸

### カテゴリー軸

大カテゴリー / サブカテゴリーは `categories` table の階層で表現する。

- root category: `parent_id = null`
- subcategory: `parent_id = <root category id>`
- 初期実装では深さ 2 までを正式対応範囲にする。
- 将来深さを増やす場合も、API payload は `parent_id` と `children` の両方に対応できる形を維持する。

`memories.category_id` は原則として subcategory を指す。サブカテゴリーを作らないカテゴリーでは root category 直下の memory も許容する。

### 年代軸

年代はカテゴリーに混ぜない。

- `period_key`: 人生フェーズ。例: `childhood`, `elementary_school`, `junior_high`, `high_school`, `university`, `adult`
- `occurred_on`: 分かる場合の具体日付。

将来「2020年代」「上京前」「子育て前半」のような user-defined period が必要になった場合は、`periods` table を別 task で追加する。

### 感情軸

既存 API の `emotion_label` / `emotion_intensity` は primary emotion として維持する。

記憶の海画面で使う複数 emotion score は、初期実装では `metadata.emotion_scores` から返す。

例:

```json
{
  "emotion_label": "感動",
  "emotion_intensity": 5,
  "metadata": {
    "emotion_scores": {
      "感動": 92,
      "懐かしさ": 88,
      "喜び": 75
    }
  }
}
```

emotion score を検索・集計の主軸にする段階で、別 table 化を検討する。

### 重み・信念・鎖

初期実装では metadata に置く。

- `metadata.importance_score`: 0.0-1.0 の表示重み。
- `metadata.beliefs`: string array。
- `metadata.chains`: string array。

AI 生成・人格生成で正式な信念 model が必要になった段階で、`beliefs` / `memory_belief` などの別 table を検討する。

### 表示情報

色、座標、形状は core model に直入れしない。初期実装では server が stable payload を返し、frontend が deterministic に layout してよい。

必要になった場合だけ、`categories.metadata.visualization` のような JSON 領域を追加する。

## API 初期案

### `GET /api/v1/memory-space`

認証必須。通常は `visibility=secret` を含めない。

Query:

- `period_key`: nullable。年代フィルター。
- `category_id`: nullable public id string (`cat_01...`)。指定 category と descendants を対象にする。v1 transition 中は integer category id も互換として受け付けるが、memory-space frontend は `category.public_id` を送る。
- `include_descendants`: nullable boolean。default `true`。
- `include_secret`: nullable boolean。default `false`。
- Header `X-Secret-Unlock`: nullable。`include_secret=true` 時に valid unlock token を渡す。

Response draft:

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
            "locked_secret_count": 0
          }
        ]
      }
    ],
    "memories": [
      {
        "id": 10,
        "public_id": "mem_01HX0000000000000000000000",
        "category_id": 2,
        "category_public_id": "cat_01HX0000000000000000000001",
        "period_key": "high_school",
        "occurred_on": null,
        "title": "Tomorrow never knowsを初めて聴いた日",
        "body": "高校の帰り道...",
        "emotion_label": "感動",
        "emotion_intensity": 5,
        "emotion_scores": {
          "感動": 92,
          "懐かしさ": 88,
          "喜び": 75
        },
        "importance_score": 0.95,
        "beliefs": ["音楽は人生を変える"],
        "chains": [],
        "tags": ["初めての出会い", "青春"],
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

`include_secret=true` の場合でも、unlock token がない、期限切れ、不正な場合は secret memory は返さず、`secret.locked=true` とする。valid unlock token がある場合だけ secret memory を `memories` に含め、`secret.locked=false` / `locked_count=0` / `unlock_expires_at=<token expiry>` を返す。

実装済み baseline:

- category tree は request context 内の root category を top-level に返し、`children` に descendants を含める。
- category の `memory_count` / `locked_secret_count` は、現在の `period_key` filter に一致する subtree aggregate count とする。
- memory payload は `metadata.emotion_scores` / `metadata.importance_score` / `metadata.beliefs` / `metadata.chains` を visualization field として展開する。
- `periods` は固定 period key と表示 label の一覧を返す。
- `POST /api/v1/secret-unlocks` と `X-Secret-Unlock` 検証は実装済み。`users.secret_unlock_password` の専用 hash を検証し、15 分有効な user scoped token を返す。account password hash は unlock 判定に使わない。
- `PUT /api/v1/secret-unlock-password` は実装済み。専用 unlock password の setup / change は account password 確認を必須にし、change では現在の unlock password も確認する。
- secret unlock password recovery / forced rotation は setup / change と別 contract とする。recovery request / completion と manager forced rotation は実装済み。recovery は Bearer token、account password、verified email を要求し、completion 時だけ unlock password hash を更新して既存 unlock token を失効させる。manager forced rotation は対象 user の unlock password hash を clear し、既存 unlock token を失効させるだけで、secret 内容や temporary password は返さない。

### `POST /api/v1/secret-unlocks`

認証必須。user scoped unlock password を検証し、短時間有効な unlock token を返す。unlock password は account password とは別の `users.secret_unlock_password` hash を使う。未設定 user は `422` で token を発行しない。

Request:

```json
{
  "password": "user unlock password"
}
```

Response:

```json
{
  "data": {
    "unlock_token": "opaque-token",
    "expires_at": "2026-05-05T00:45:00+09:00"
  }
}
```

frontend は以後の `GET /api/v1/memory-space?include_secret=1` に `X-Secret-Unlock: <unlock_token>` を付ける。

token は `secret_unlock_tokens` に sha256 hash のみ保存し、plain text は response で 1 回だけ返す。TTL は初期実装では 15 分。

### Secret unlock password recovery / forced rotation

記憶の海画面の unlock dialog は、unlock password を忘れた場合に backend recovery flow へ誘導できる。ただし frontend だけで secret memory を露出してはいけない。

- `POST /api/v1/secret-unlock-password/recovery/request` は実装済み。Bearer token、account password、verified email を確認して signed recovery link を送る。
- `PUT /api/v1/secret-unlock-password/recovery/{id}/{hash}` は実装済み。signed link と Bearer token が同じ user を指す場合だけ、新しい unlock password に reset する。
- recovery completion は `secret_unlock_tokens` を削除し、既存 unlock token を失効させる。Bearer token は revoke しない。
- `POST /api/v1/tenant/members/{member}/secret-unlock-password/force-rotation` は実装済み。tenant manager が対象 user の unlock password hash を clear し、既存 unlock token を失効させる。manager は対象 user の secret memory や temporary password を受け取らない。
- forced rotation 後の target user は、既存 `PUT /api/v1/secret-unlock-password` の setup flow で新しい dedicated unlock password を設定する。

## Frontend 実装方針

- 参照 HTML の Three.js 表現を、Laravel/Vite 配下の通常 frontend asset として実装する。初期 route は `GET /memory-space`。
- Laravel auth session には依存せず、API Base URL / Bearer token / unlock token を frontend state で扱う。email / password login は `POST /api/v1/auth/login` で Bearer token を発行して既存 token input と同じ保存先に反映する。
- 管理画面モックアップのような別 static mock ではなく、repo root の frontend 画面として実装する。
- UI は実 API 接続に必要な最小 controls に絞る。
  - API token 設定
  - email / password login
  - period filter
  - category focus
  - secret unlock modal
  - memory detail panel
- secret password は保存しない。unlock token も短 TTL 前提で session state に留める。

実装済み baseline:

- `resources/views/memory-space.blade.php` に `GET /memory-space` 用 shell を追加。
- `resources/js/memory-space.js` は Three.js を Vite bundle として import し、category tree と memories を deterministic layout で描画する。
- `resources/css/memory-space.css` は full-bleed canvas と最小 control overlay、summary、list、detail、unlock dialog を定義する。
- frontend は `POST /api/v1/auth/login` で login token を取得でき、手入力 Bearer token と同じ shared config に保存する。`GET /api/v1/memory-space` には Bearer token を付け、period / category / descendant filter を query に反映する。
- 401 response では controls panel を開き、login status と global status に認証失敗を表示する。
- category select value、category map key、memory active id、detail state は `public_id` / `category_public_id` を使う。response の integer `id` / `category_id` は移行互換の表示・debug 値としてだけ扱う。
- unlock dialog は `POST /api/v1/secret-unlocks` を呼び、password は保存せず、返却された unlock token だけを runtime state に保持して `X-Secret-Unlock` で再取得する。
- WebGL renderer 初期化に失敗した場合は canvas / scene 操作だけを無効化し、API controls、filters、list/detail、secret unlock dialog は一覧モードとして継続動作する。
- browser smoke は built asset 経由で nonblank canvas、invalid token の 401 表示、seed data 付き list/detail/secret unlock flow、WebGL unavailable fallback の list/detail 動作まで確認済み。

## 実装順

1. `categories.parent_id` の migration / model / validation / tests を追加する。完了済み。
2. category tree response と descendant filter を categories / memories API に追加する。完了済み。
3. memory-space API の read endpoint を追加する。完了済み。
4. secret unlock の backend baseline を追加する。完了済み。
5. 記憶の海 / 宇宙 frontend を Vite asset として実装し、実 API に接続する。完了済み。
6. seed data 付き browser smoke で API token、list/detail、period / category filter、secret unlock 表示を確認する。完了済み。
7. WebGL unavailable fallback を追加する。完了済み。
8. prefixed ULID public id response baseline を追加する。完了済み。
9. memories / categories API の public id resolver implementation と memory-space frontend request 移行を行う。完了済み。

public id lookup / request validation 移行方針は `docs/decisions/0020-public-id-request-lookup.md` で決定済み。memory-space frontend は category filter と detail state に public id を使うよう移行済み。
