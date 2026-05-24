# 管理画面モックアップ 手動 API smoke test

最終更新: 2026-05-06

## 目的

`docs/references/admin-ui-mockup/` の静的モックアップが、real backend API に Bearer token 付きで接続できることを最小手順で確認する。

この手順は API 接続確認用であり、UI の見た目刷新や本格 frontend 化は扱わない。

## 前提

- backend は local 開発環境で起動する。
- DB migration は適用済みである。未適用なら `php artisan migrate` を先に実行する。
- local 開発環境では `php artisan db:seed` を実行すると、`admin@example.test`、固定 Bearer token `local-dev-token`、確認用 category / memory / tag seed data が作成される。
- disposable DB で初期化したい場合だけ `php artisan migrate:fresh` を使う。
- protected API は `Authorization: Bearer <token>` を要求する。

## 起動

通常の local 起動では、backend / queue / log tail / Vite とあわせて migration と seed も自動実行する。

```bash
composer dev
```

この起動では `admin@example.test`、固定 Bearer token `local-dev-token`、確認用 category / memory / tag seed data が自動で用意される。

個別 terminal で起動したい場合は、先に seed を実行する。

Terminal 1:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
php -S 127.0.0.1:8001 -t docs/references/admin-ui-mockup
```

ブラウザで `http://127.0.0.1:8001/` を開く。Settings の API Base URL は別 origin から API を呼ぶため、`http://127.0.0.1:8000/api/v1` を使う。

## Token と Settings

`http://127.0.0.1:8000/admin` または `http://127.0.0.1:8000/memory-space` で開く場合、Settings / Bearer input は `local-dev-token` を自動使用する。ブラウザに古い `id|...` 形式の token が残っている場合も local token に寄せる。

seed data には root category / subcategory、通常 memory 3 件、secret memory 1 件、tags が含まれる。Memory Space では unlock 前に `locked_count = 1` が表示される。

別 origin の静的サーバー `http://127.0.0.1:8001/` で開く場合は、Settings の API Base URL を `http://127.0.0.1:8000/api/v1` にし、Bearer Token は `local-dev-token` を使う。

任意の one-time token を発行したい場合だけ、次を使う。

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
   - category hierarchy がある場合、`親カテゴリ` column に root category は `—`、subcategory は親カテゴリ名が表示される。
   - endpoint: `GET /api/v1/categories`。

3. `Categories` で `新規作成` を押し、検証用 category を作る。
   - 入力例: name `Smoke Parent`, slug `smoke-parent`, 親カテゴリ `なし（大カテゴリ）`, sort `10`。
   - 期待値: toast に作成成功が出て、table に追加される。
   - endpoint: `POST /api/v1/categories`。payload は `parent_id: null`。

4. `Categories` で再度 `新規作成` を押し、検証用 subcategory を作る。
   - 入力例: name `Smoke Child`, slug `smoke-child`, 親カテゴリ `Smoke Parent`, sort `11`。
   - 期待値: toast に作成成功が出て、table の `親カテゴリ` に `Smoke Parent` が表示される。
   - endpoint: `POST /api/v1/categories`。payload は root category id の `parent_id`。

5. 作成した subcategory の編集ボタンを押し、name / sort / parent を確認する。
   - 入力例: name `Smoke Child Updated`, slug `smoke-child`, 親カテゴリ `Smoke Parent`, sort `12`。
   - 期待値: toast に更新成功が出て、table 表示が更新される。
   - endpoint: `PATCH /api/v1/categories/{category}`。payload に `parent_id` が含まれる。

6. `Memories` を開き、list を確認する。
   - 期待値: 401 が出ず、空または既存 memory の table が表示される。
   - default list に `visibility=secret` は混ざらない。
   - endpoint: `GET /api/v1/memories`。

7. `Memories` で `新規作成` を押し、検証用 memory を作る。
   - 入力例: title `Smoke memory`, body `API smoke test body`, period `高校`, emotion `普通`, intensity `3`, category `Smoke Parent / Smoke Child Updated`, visibility `private`, tags `smoke, 友達`。
   - 期待値: toast に作成成功が出て、table に追加される。
   - endpoint: `POST /api/v1/memories`。

8. 作成した memory の table row をクリックし、detail drawer を確認する。
   - 期待値: ID、本文、時期、感情、カテゴリ、タグ、公開範囲が表示される。
   - endpoint: `GET /api/v1/memories/{memory}`。

9. detail drawer の `編集` を押し、memory を更新する。
   - 入力例: title `Smoke memory updated`, body `Updated API smoke test body`, visibility `shared`, tags `smoke, 夏`。
   - 期待値: toast に更新成功が出て、list / detail に更新内容が反映される。
   - endpoint: `PATCH /api/v1/memories/{memory}`。

10. detail drawer の `削除` を押し、memory を削除する。
   - 期待値: toast に削除成功が出て、list から消える。
   - endpoint: `DELETE /api/v1/memories/{memory}`。

11. `Tags` を開き、tags list を確認する。
    - 期待値: 作成済み memory が残っている場合は tag と usage count が表示される。手順 10 で memory を削除済みなら、削除時に pivot が外れるため usage count が減る、または空になる。
    - endpoint: `GET /api/v1/tags`。

12. `Categories` に戻り、作成した category を削除する。
    - 期待値: confirm 後に toast に削除成功が出て、table から消える。
    - endpoint: `DELETE /api/v1/categories/{category}`。
    - parent / child を両方作った場合は child を先に削除し、その後 parent を削除する。

## Error 表示確認

401 確認:

1. `Settings` で `Token クリア` を押す。
2. `Memories` または `Categories` を開く。
3. 期待値: `認証に失敗しました。Bearer token を確認してください。` の error state / toast が出る。
   - API 側は `Accept: application/json` がない request でも `401` JSON を返す。
4. Settings に valid token を戻す。

422 確認:

1. `Categories` で `新規作成` を押す。
2. `カテゴリ名` または `スラッグ` を空にして `保存` を押す。
3. 期待値: validation error が toast / error detail に表示される。
   - API 側は `Accept: application/json` がない request でも `422` JSON を返す。

## 完了判定

- Settings 保存後、protected API に 401 が出ない。
- Health が `ok` を返す。
- Memories の list/detail/create/update/delete が UI 操作から通る。
- Categories の list/create/update/delete と `parent_id` create/update 表示が UI 操作から通る。
- Tags list が UI 操作から通る。
- 401 と 422 の表示が確認できる。
- 実 API とモックアップの食い違いがあれば、実装せず `task_board.md` の追加 task 候補に記録する。
