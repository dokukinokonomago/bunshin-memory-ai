# タスクボード

最終更新: 2026-05-21 19:03:54 JST

## 現在の目的

分身AIバックエンドを新規設計 baseline に沿って進める。`categories.parent_id` baseline は完了済み。production billing readiness は approved config / smoke target が揃うまで blocked として残す。

## 今回進める 1 task

`approved production billing config と smoke target を approved secret / operator path で投入した上で、production billing readiness を再実行する`

## 完了条件

- automation memory と前回 task board を確認し、今回の正式 task を 1 つに絞る。完了。
- 旧資材が `legacy_assets/20260504_004800_existing_assets/` に退避済みで、二重退避しないことを確認する。完了。
- readiness command / config / test の現状を確認する。完了。
- `php artisan bunshin:billing-smoke-readiness` を実行し、secret / Bearer token / hosted URL / provider id を記録せずに結果を要約する。完了。
- readiness が ready でない場合、本番 checkout / portal / webhook smoke には進まない。完了。
- targeted syntax / tests と `git diff --check` を実行する。完了。
- 結果を run log / task board / automation memory に残す。完了。

## 未着手 task

- approved production billing config と smoke target を approved secret / operator path で投入した上で、production billing readiness を再実行する。
- readiness が ready になった後の本番 checkout / cancel / portal return / webhook delivery smoke。
- category 階層を将来 3 階層以上に広げる場合は、validation / tree response / memory-space aggregate count を別 task で再設計する。
- public API が integer id 互換を外す段階になったら、category request tests を public id only に切り替える。

## 進行中 task

- なし。2026-05-21 19:03:54 JST に今回 task は完了。

## 完了 task

- 2026-05-21 19:03:54 JST: production billing readiness を再実行した。現環境では Stripe API base 以外の prerequisites が未設定または未確認で、14 checks missing のため ready ではない。checkout / portal / webhook smoke は未実施。
- 2026-05-21 18:04:17 JST: `categories.parent_id` の migration / model / validation / tests baseline を再検証した。既存実装が完了条件を満たしており、runtime code / DB migration / public API endpoint / docs / OpenAPI schema の追加修正は不要だった。legacy assets presence、PHP syntax checks、category routes 5 routes、testing migration fresh、targeted Feature tests 27 tests / 359 assertions、`git diff --check` は成功。
- 2026-05-21 17:03:31 JST: production billing readiness を再実行した。現環境では Stripe API base 以外の prerequisites が未設定または未確認で、14 checks missing のため ready ではない。checkout / portal / webhook smoke は未実施。
- 2026-05-21 16:03:39 JST: `categories.parent_id` の migration / model / validation / tests baseline を再検証した。既存実装が完了条件を満たしており、追加修正不要だった。
- 2026-05-04: 新規 Laravel backend skeleton、設計 docs、OpenAPI draft、health endpoint を作成し、旧資材を `legacy_assets/20260504_004800_existing_assets/` に退避した。

## 調査中に思いついた追加 task

- `THREE.Clock` deprecation warning を解消するため、将来 Three.js `Timer` へ置き換えるかを確認する。

## 人間判断が必要な論点

- `categories.parent_id` は初期実装どおり大カテゴリー / サブカテゴリーの 2 階層固定で運用するか、API validation と response を 3 階層以上にも正式対応させるか。
- production billing smoke に使う API origin、frontend origin、approved smoke tenant、owner account、provider account をどれにするか。
- 実課金を伴う production smoke を許可するか、provider の approved simulation / test path に限定するか。
- production billing config と smoke hints をどの approved secret / operator path で投入するか。

## 次回 automation が最初に見るべきメモ

`categories.parent_id` baseline は 2026-05-21 18:04:17 JST に再検証済み。production billing readiness は 2026-05-21 19:03:54 JST 時点でも blocked。approved secrets / smoke targets が投入されるまで checkout / portal / webhook smoke に進まない。secret、Bearer token、hosted URL、provider id はログ・run log・task_board・memory に残さない。

## 次にやるべき 1 task

`approved production billing config と smoke target を approved secret / operator path で投入した上で、production billing readiness を再実行する`

## 変更ファイル一覧

- `task_board.md`
- `run_log.md`
- `/Users/fukui/.codex/automations/ai-3/memory.md`

## 動作確認結果

- `test -d legacy_assets/20260504_004800_existing_assets`: 成功。
- `php -l app/Console/Commands/BillingSmokeReadinessCommand.php`: 成功。
- `php -l tests/Feature/BillingSmokeReadinessCommandTest.php`: 成功。
- `php artisan bunshin:billing-smoke-readiness`: exit 1。Stripe API base 以外の 14 checks が未充足。
- `php artisan test tests/Feature/BillingSmokeReadinessCommandTest.php`: 成功。4 tests / 32 assertions。
- `git diff --check`: 問題なし。

## 次回 automation への引き継ぎ

今回の task は完了。次回は `approved production billing config と smoke target を approved secret / operator path で投入した上で、production billing readiness を再実行する` から開始する。`categories.parent_id` baseline は追加実装不要。production checkout / portal / webhook smoke は readiness が ready になるまで実行しない。secret、Bearer token、hosted URL、provider id はログ・run log・task_board・memory に残さない。

今回の task は完了。次回は `approved production billing config と smoke target を approved secret / operator path で投入した上で、production billing readiness を再実行する` から開始する。
