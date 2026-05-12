# 記憶の海 / 宇宙画面モックアップ

## 参照元

- 元ファイル: `/Users/fukui/Dropbox/download/memory_space (1).html`
- repo 内コピー: `docs/references/memory-space-screen/memory_space.html`

## automation での扱い

この画面は今後の実装対象に含める。管理画面モックアップとは別に、ユーザー向けの探索画面として backend API と frontend 実装を進める。

ただし、実装の正はこの HTML に直書きされた mock data ではなく、`docs/architecture/memory_space_screen.md`、`docs/architecture/data_model.md`、`docs/architecture/api_contract.md`、`docs/decisions/0005-memory-space-screen.md` とする。

## 現 mock data が前提にしている構造

- `bigCategories`: 大カテゴリー。例: 音楽、学校、人生の節目。
- `midCategories`: サブカテゴリー。大カテゴリーの `id` を `parentId` として持つ。
- `memories`: 記憶。`midCategoryId` でサブカテゴリーに紐づく。
- `weight`: 記憶の表示上の重み。
- `emotions`: 複数 emotion label と score。
- `beliefs` / `chains`: 詳細パネルに表示する補助情報。
- `tags`: 横断的なラベル。

## backend 側への対応方針

- 大カテゴリー / サブカテゴリーは `categories.parent_id` による同一 table の階層として扱う。
- 年代はカテゴリー階層に混ぜず、`period_key` / `occurred_on` の時間軸として扱う。
- `weight`、複数 emotion score、`beliefs`、`chains`、表示座標や色は初期実装では visualization 用 payload / metadata として扱い、core category / memory 分類と分ける。
- `visibility=secret` は通常 payload から除外し、この画面では password unlock 風の UI と backend 認可を通した場合だけ返す。
