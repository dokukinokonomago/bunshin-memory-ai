# タスクボード

最終更新: 2026-05-04 00:45:17 JST

## 現在の目的

分身AI MVP のバックエンド挙動を小さな task 単位で安定化する。

## 今回進める 1 task

記憶作成・更新で、空白だけの `content` が保存されないようにバリデーションと Feature test を追加する。

## 完了条件

- 空白だけの `content` を POST / PUT した場合に validation error で戻る。
- 正常な `content` は従来どおり trim されて保存される。
- 追加した Feature test が通る。

## 未着手 task

- 関連タグの正規化仕様を Feature test で固定する。
- 隠し記憶タグ `__grave_hidden__` の直接注入防止を Feature test で固定する。
- 記憶一覧の検索条件を Feature test で固定する。
- 編集画面からカスタム感情を維持できるか確認し、必要なら修正する。

## 進行中 task

- なし。

## 完了 task

- 2026-05-04: 記憶作成・更新で空白だけの `content` を拒否するバリデーションと Feature test を追加した。

## 変更ファイル一覧

- `app/Http/Controllers/MemoryController.php`: trim 後に空になる `content` を validation error にする処理を追加。
- `tests/Feature/MemoryCreatePreviewTest.php`: 空白本文の作成・更新拒否、正常本文の trim 保存テストを追加。
- `tests/TestCase.php`: ローカルでも testing SQLite と testing env でテスト起動できるように調整。
- `resources/views/memories/create_v2.blade.php`: Blade の inline `@php` 構文を block 形式へ修正して作成画面の構文エラーを解消。
- `task_board.md`: 初回タスクボードを作成し、今回の進捗を反映。

## 動作確認結果

- `php artisan test tests/Feature/MemoryCreatePreviewTest.php --display-warnings`: 7 passed, 24 assertions。
- `php artisan test`: 20 passed, 69 assertions。

## 調査中に思いついた追加 task

- `MemoryController` の定数と view 側の年代・感情定義が重複しているため、後続で一元化を検討する。
- `visibleMemoriesQuery()` / `graveMemoriesQuery()` の JSON 検索を DB 差異に強い実装へ寄せられるか確認する。

## 人間判断が必要な論点

- カスタム感情を今後も自由入力として許可するか、MVP仕様書の固定リストに戻すか。
- 「不明」年代を MVP 仕様に正式追加済みとして扱うか。

## 次回 automation が最初に見るべきメモ

今回の task は完了。次回は「関連タグの正規化仕様を Feature test で固定する」から開始する。
