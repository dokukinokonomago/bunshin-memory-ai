# Admin UI Mockup Reference

配置日: 2026-05-04

## 目的

このディレクトリは、Codex automation が backend API 実装時に参照する管理画面モックアップです。

frontend 実装そのものをこの backend automation で進めるためのものではありません。API endpoint、response shape、query parameter、secret memory の扱い、管理画面で必要になる一覧・詳細・編集導線を確認するための参照資料として使います。

## ファイル

- `index.html`: 管理画面モックアップ本体。
- `styles.css`: モックアップ用 CSS。
- `app.js`: real backend API client。Settings で API Base URL と Bearer token を設定し、既存画面から API 接続を確認する。
- `manual-smoke-test.md`: real backend API への手動接続 smoke test 手順。
- `source-files.zip`: ユーザー提供 zip の原本。

## Codex automation での使い方

- backend API を実装する前に、必要に応じて `app.js` の `api.*` client と画面側が参照する field を確認する。
- この backend automation では、既存モックアップを real API に繋ぐための最小限の HTML/CSS/JS 改修まで扱う。本格 frontend 再設計は別 automation で扱う。
- protected API は Settings に保存した Bearer token を `Authorization: Bearer <token>` で送る。
- token 発行 API endpoint は置かない。検証用 token は server-side の artisan command で発行する。
- `php artisan bunshin:issue-admin-token` で tenant / user / token を作成し、表示された `Bearer token:` の値を Settings に貼る。
- 同じ `--email` / `--token-name` で再実行すると既存 token は revoke され、新しい token だけが有効になる。
- `visibility=secret` は通常 memory list に混ぜず、明示取得時だけ返す前提で API を設計・実装する。
- mockup と backend 設計 docs が食い違う場合は、`docs/architecture/` と `docs/decisions/` を正とし、mockup から必要な API 要件だけを task_board に追加候補として記録する。

## Token 発行

```bash
php artisan bunshin:issue-admin-token \
  --tenant=default \
  --tenant-name="Default Tenant" \
  --email=admin@example.test \
  --name="Admin User" \
  --token-name=admin-mockup \
  --expires-days=30
```

plain text token は command output に 1 回だけ表示され、DB には sha256 hash だけが保存される。

## 手動 smoke test

real API 接続の手動確認は `manual-smoke-test.md` に沿って行う。Health、Settings の Bearer token、memories list/detail/create/update/delete、categories list/create/update/delete、tags list、401 / validation error 表示を 1 pass で確認する。

## 関連 docs

- `docs/references/admin-ui-mockup/manual-smoke-test.md`
- `docs/architecture/api_contract.md`
- `docs/architecture/data_model.md`
- `docs/decisions/0002-api-scope-and-secret-visibility.md`
- `docs/decisions/0004-admin-mockup-token-issuance.md`
