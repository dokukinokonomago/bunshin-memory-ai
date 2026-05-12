# Claude 向け指示: 分身AI 管理画面 HTML UI

以下を Claude にそのまま渡してください。

```text
あなたは senior frontend engineer / product designer として、分身AIバックエンドの管理画面 UI を静的 HTML で設計・実装してください。

## 前提

- backend は API-first で新規実装中です。
- 旧 UI は完全に破棄済みです。旧 Blade / 旧 CSS / 旧画面構成は参照しないでください。
- frontend は backend 完了後に別 automation で本実装します。今回は管理画面側 UI の HTML prototype を作るタスクです。
- backend の API base path は `/api/v1` です。
- UI は「管理画面」です。マーケティング LP ではなく、日常的に記憶データを確認・編集・管理する業務画面として作ってください。

## このシステムについて

「分身AI」は、ユーザー本人の記憶・出来事・感情・タグを蓄積し、将来的にその人らしい AI 体験や振り返り、対話、分析に使えるようにするための記憶管理システムです。

この backend が扱う中心データは `memory` です。memory は、単なるメモではなく「いつ頃の出来事か」「何があったか」「その時の感情は何か」「どのカテゴリやタグに属するか」「通常表示してよいか、秘匿すべきか」を持つ個人の記憶データです。

この管理画面は、一般ユーザー向けの思い出投稿 UI ではありません。運用者または本人が、保存済みの記憶データを点検し、分類し、必要に応じて修正し、API の状態を確認するための admin / operations UI です。

この画面が支える主な作業:

- 記憶データが正しく保存されているか確認する
- 記憶本文、年代、感情、カテゴリ、タグを整理する
- 通常記憶と秘匿記憶を混ぜずに管理する
- カテゴリやタグの表記ゆれを管理する
- API が正常に動いているか確認する
- 将来の AI 利用に向けて、記憶データの品質を保つ

## 重要なドメイン概念

- `memory`: ユーザーの出来事や思い出の記録。本文、年代、感情、カテゴリ、タグ、公開範囲を持つ。
- `period_key`: 記憶が属する時期。例: `childhood`, `elementary_school`, `junior_high`, `high_school`, `university`, `adult`。
- `emotion_label`: 記憶に紐づく感情ラベル。例: 嬉しい、普通、不安、悲しい。
- `emotion_intensity`: 感情の強さ。1-5 の想定。
- `category`: 記憶を大きく分類する軸。例: 家族、学校、仕事、人間関係。
- `tag`: 横断的な検索・整理に使うラベル。例: 放課後、友達、夏、部活。
- `visibility`: 記憶の扱い。`private`, `shared`, `secret` を想定。
- `secret`: とても個人的・秘匿性の高い記憶。通常一覧には出さず、明示的に開いた時だけ表示する。
- `tenant`: データの所属組織または利用単位。
- `owner_user`: 記憶の所有者。

## 作ってほしいもの

静的 HTML / CSS / vanilla JS だけで、管理画面 prototype を作ってください。

推奨ファイル:

- `admin-ui/index.html`
- `admin-ui/styles.css`
- `admin-ui/app.js`

フレームワーク、ビルドツール、外部 CDN は使わないでください。開くだけで動く HTML にしてください。

## 画面要件

### 1. 全体 layout

- 左 sidebar: main navigation
- 上 header: page title, API status, user / tenant 表示
- main area: tab または route-like section 切り替え
- 画面は desktop-first だが、tablet 幅でも破綻しない responsive layout にする

### 2. Navigation

最低限この navigation を用意してください。

- Dashboard
- Memories
- Secret Memories
- Categories
- Tags
- API Health
- Settings

## 各ページの役割

### Dashboard の役割

システム全体の状態を確認する入口です。記憶データの総量、通常記憶と secret 記憶の内訳、カテゴリ・タグの数、直近更新、API health を見て、今どこに注意が必要かを把握できるようにしてください。

Dashboard は装飾的なトップページではありません。運用者が最初に見る status overview です。

### Memories の役割

通常の記憶データを検索・確認・編集する主作業画面です。ここでは `visibility=secret` の記憶を絶対に混ぜないでください。

運用者はこの画面で、本文の確認、年代や感情の修正、カテゴリ付け、タグ整理、詳細確認、作成、編集、削除を行います。

### Secret Memories の役割

秘匿性の高い記憶だけを扱う別画面です。通常の Memories 画面とは導線と見た目を分けてください。

このページは「本人または権限のある運用者が、明示的に開く必要がある領域」です。開く前に confirmation / unlock-like interaction を置き、意図せず secret が見えない設計にしてください。

### Categories の役割

記憶を大きく分類するカテゴリを管理する画面です。カテゴリ名、slug、並び順、利用件数を確認し、作成・編集・削除または archive の導線を用意してください。

カテゴリは記憶データを後から探しやすくし、将来の分析や AI 利用で文脈を与えるための分類軸です。

### Tags の役割

記憶に付く細かいラベルを管理する画面です。タグ名、正規化済み名称、利用件数を確認し、表記ゆれを統合するための merge action UI を用意してください。

タグは検索や横断的な関連付けのための補助情報です。カテゴリより柔軟で、多対多の関係を想定します。

### API Health の役割

backend API の稼働状態を確認する画面です。`GET /api/v1/health` の service / status / version を表示し、障害時には admin がすぐ気づける状態にしてください。

### Settings の役割

prototype 段階では、tenant / user / API base URL / mock mode などの設定表示を置く画面にしてください。実装済みでない操作は disabled / placeholder として表現してください。

### 3. Dashboard

管理者が一目で状況を把握できる dashboard を作ってください。

表示内容:

- 総記憶数
- 通常記憶数
- secret 記憶数
- category 数
- tag 数
- 直近更新された記憶
- API health 状態

データは mock でよいですが、後で API に差し替えやすい JS structure にしてください。

### 4. Memories

通常の memory list 画面を作ってください。

重要:

- default list では `visibility=secret` の記憶を表示しないでください。
- secret は通常一覧に混ぜないでください。

表示要素:

- 検索 input
- period filter
- category filter
- tag filter
- visibility 表示。ただし通常画面は `private` / `shared` 中心
- memory table または dense list
- detail drawer / side panel
- create / edit modal
- delete action は destructive として慎重な UI にする

Memory resource draft:

```json
{
  "id": "mem_01HX...",
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

### 5. Secret Memories

`visibility=secret` 専用画面を別に作ってください。

重要:

- 通常 Memories とは明確に分けてください。
- secret 一覧は「明示的に開いた時だけ表示される」設計にしてください。
- 開く前に confirmation / unlock-like interaction を入れてください。
- 表示後も視覚的に secret context が分かるようにしてください。
- ただし過度に怖い見た目にはしないでください。静かで慎重な管理 UI にしてください。

想定 API:

- 通常一覧: `GET /api/v1/memories`
- secret 明示一覧: `GET /api/v1/memories?visibility=secret`
- ID 指定詳細: `GET /api/v1/memories/{memory}`

### 6. Categories

category 管理画面を作ってください。

表示 / 操作:

- category list
- name
- slug
- sort order
- memory count
- create / edit / archive or delete

### 7. Tags

tag 管理画面を作ってください。

表示 / 操作:

- tag list
- name
- normalized_name
- usage count
- merge tags action の UI だけ用意

### 8. API Health

`GET /api/v1/health` の状態を見る画面を作ってください。

Response:

```json
{
  "service": "bunshin-memory-api",
  "status": "ok",
  "version": "0.1.0"
}
```

prototype では mock response でよいですが、`fetchHealth()` のような関数を作り、後で fetch に差し替えやすくしてください。

## API integration 方針

今回は静的 prototype なので mock data で動かしてください。

ただし JS は次のような構成にしてください。

- `api.listMemories(params)`
- `api.getMemory(id)`
- `api.createMemory(payload)`
- `api.updateMemory(id, payload)`
- `api.deleteMemory(id)`
- `api.listCategories()`
- `api.listTags()`
- `api.getHealth()`

最初は mock data を返す実装でよいです。実 API に差し替える場所を明確にしてください。

## Design direction

- 管理画面なので、情報密度は高めでよいです。
- 余白は取りつつ、業務 UI としてスキャンしやすくしてください。
- カードを多用しすぎず、table / list / drawer / modal を適切に使ってください。
- 色は落ち着いた neutral base に、状態色だけを accent として使ってください。
- secret 画面は別 context と分かる程度の restrained accent にしてください。
- 角丸は控えめにしてください。
- 大きすぎる hero や装飾的な背景は不要です。
- 文字がボタンやテーブルセルからはみ出さないようにしてください。

## Accessibility / UX

- button / input / select / dialog に適切な label を付けてください。
- keyboard 操作を最低限考慮してください。
- modal / drawer を閉じる button を用意してください。
- destructive action は確認 UI を入れてください。
- loading / empty / error state を用意してください。

## 実装上の注意

- 旧 UI の見た目を復元しないでください。
- backend repo に Laravel / Blade UI を作らないでください。
- 今回は静的 HTML prototype だけを作ってください。
- API が未実装でも、mock data で画面の導線が確認できる状態にしてください。
- UI 内に説明文を入れすぎず、管理画面として自然に使える表示にしてください。

## 完了条件

- `admin-ui/index.html` をブラウザで開くと管理画面 prototype が表示される。
- Dashboard / Memories / Secret Memories / Categories / Tags / API Health / Settings を切り替えられる。
- 通常 memory list には secret が混ざらない。
- Secret Memories は明示 unlock 操作後にだけ表示される。
- create / edit / detail / delete の UI 導線がある。
- mock API layer が JS に分離されていて、後で real API に差し替えやすい。
- HTML / CSS / JS が読みやすく、1 ファイルに詰め込みすぎていない。
```
