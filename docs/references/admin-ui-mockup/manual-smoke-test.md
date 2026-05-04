# 管理画面モックアップ 手動 API smoke test

最終更新: 2026-05-04

## 目的

`docs/references/admin-ui-mockup/` の静的モックアップが、real backend API に Bearer token 付きで接続できることを最小手順で確認する。

この手順は API 接続確認用であり、UI の見た目刷新や本格 frontend 化は扱わない。

## 前提

- backend は local 開発環境で起動する。
- DB migration は適用済みである。未適用なら `php artisan migrate` を先に実行する。
- disposable DB で初期化したい場合だけ `php artisan migrate:fresh` を使う。
- protected API は `Authorization: Bearer <token>` を要求する。

## 起動

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
php -S 127.0.0.1:8001 -t docs/references/admin-ui-mockup
```

ブラウザで `http://127.0.0.1:8001/` を開く。Settings の API Base URL は別 origin から API を呼ぶため、`http://127.0.0.1:8000/api/v1` を使う。

## Token 発行と Settings

Terminal 3:

```bash
php artisan bunshin:issue-admin-token \
  --tenant=default \
  --tenant-name="Default Tenant" \
  --email=admin@example.test \
  --name="Admin User" \
  --token-name=admin-mockup \
  --expires-days=30
```

command output の `Bearer token:` に続く `id|plainTextToken` を 1 回だけコピーする。

管理画面:

1. Sidebar の `Settings` を開く。
2. `API Base URL` に `http://127.0.0.1:8000/api/v1` を入力する。
3. `Bearer Token` に command output の token を貼る。
4. `保存` を押す。

同じ `--email` / `--token-name` で command を再実行すると、既存 token は revoke される。再発行後は Settings の token も新しい値に差し替える。

## Smoke Test 手順

1. `API Health` を開く。
   - 期待値: Status が `正常`、Service が `bunshin-memory-api`、Version が `0.1.0`。
   - endpoint: `GET /api/v1/health`。この endpoint は token なしでも通る。

2. `Categories` を開き、list を確認する。
   - 期待値: 401 が出ず、空または既存 category の table が表示される。
   - endpoint: `GET /api/v1/categories`。

3. `Categories` で `新規作成` を押し、検証用 category を作る。
   - 入力例: name `Smoke Test`, slug `smoke-test`, sort `10`。
   - 期待値: toast に作成成功が出て、table に追加される。
   - endpoint: `POST /api/v1/categories`。

4. 作成した category の編集ボタンを押し、name / sort を更新する。
   - 入力例: name `Smoke Test Updated`, slug `smoke-test`, sort `11`。
   - 期待値: toast に更新成功が出て、table 表示が更新される。
   - endpoint: `PATCH /api/v1/categories/{category}`。

5. `Memories` を開き、list を確認する。
   - 期待値: 401 が出ず、空または既存 memory の table が表示される。
   - default list に `visibility=secret` は混ざらない。
   - endpoint: `GET /api/v1/memories`。

6. `Memories` で `新規作成` を押し、検証用 memory を作る。
   - 入力例: title `Smoke memory`, body `API smoke test body`, period `高校`, emotion `普通`, intensity `3`, category `Smoke Test Updated`, visibility `private`, tags `smoke, 友達`。
   - 期待値: toast に作成成功が出て、table に追加される。
   - endpoint: `POST /api/v1/memories`。

7. 作成した memory の table row をクリックし、detail drawer を確認する。
   - 期待値: ID、本文、時期、感情、カテゴリ、タグ、公開範囲が表示される。
   - endpoint: `GET /api/v1/memories/{memory}`。

8. detail drawer の `編集` を押し、memory を更新する。
   - 入力例: title `Smoke memory updated`, body `Updated API smoke test body`, visibility `shared`, tags `smoke, 夏`。
   - 期待値: toast に更新成功が出て、list / detail に更新内容が反映される。
   - endpoint: `PATCH /api/v1/memories/{memory}`。

9. detail drawer の `削除` を押し、memory を削除する。
   - 期待値: toast に削除成功が出て、list から消える。
   - endpoint: `DELETE /api/v1/memories/{memory}`。

10. `Tags` を開き、tags list を確認する。
    - 期待値: 作成済み memory が残っている場合は tag と usage count が表示される。手順 9 で memory を削除済みなら、削除時に pivot が外れるため usage count が減る、または空になる。
    - endpoint: `GET /api/v1/tags`。

11. `Categories` に戻り、作成した category を削除する。
    - 期待値: confirm 後に toast に削除成功が出て、table から消える。
    - endpoint: `DELETE /api/v1/categories/{category}`。

## Error 表示確認

401 確認:

1. `Settings` で `Token クリア` を押す。
2. `Memories` または `Categories` を開く。
3. 期待値: `認証に失敗しました。Bearer token を確認してください。` の error state / toast が出る。
4. Settings に valid token を戻す。

422 確認:

1. `Categories` で `新規作成` を押す。
2. `カテゴリ名` または `スラッグ` を空にして `保存` を押す。
3. 期待値: validation error が toast / error detail に表示される。

## 完了判定

- Settings 保存後、protected API に 401 が出ない。
- Health が `ok` を返す。
- Memories の list/detail/create/update/delete が UI 操作から通る。
- Categories の list/create/update/delete が UI 操作から通る。
- Tags list が UI 操作から通る。
- 401 と 422 の表示が確認できる。
- 実 API とモックアップの食い違いがあれば、実装せず `task_board.md` の追加 task 候補に記録する。
