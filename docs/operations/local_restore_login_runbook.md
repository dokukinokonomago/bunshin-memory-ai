# Local Restore And Login Runbook

最終更新: 2026-05-24

## 目的

別環境で repository を復元した人が、local backend を起動して `/memory-space` と `/admin` にログインできるところまで迷わないための手順。

この手順は local / testing 用。production では固定 token や seed password を使わない。

## 前提

- PHP 8.3 以上
- Composer
- Node.js / npm
- SQLite が使えること
- repository を clone 済みであること

## 初回セットアップ

repository root で実行する。

```bash
composer install
npm install --ignore-scripts
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force
```

`php artisan db:seed --force` は local / testing 環境だけで有効。冪等なので再実行しても local seed data は重複しない。

## 起動

通常はこれだけでよい。

```bash
composer dev
```

`composer dev` は起動前に次を自動実行する。

```bash
php artisan migrate --force
php artisan db:seed --force
```

その後、Laravel server、queue listener、log tail、Vite dev server が同時に起動する。

手動で分けて起動する場合:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

別 terminal:

```bash
npm run dev -- --host 127.0.0.1 --port 5173
```

## URL

- Memory Space: `http://127.0.0.1:8000/memory-space`
- Admin UI: `http://127.0.0.1:8000/admin`
- Health: `http://127.0.0.1:8000/api/v1/health`

`/` は `/memory-space` に redirect する。

Memory Space 右上の `管理` から Admin UI に移動できる。Admin UI sidebar の `Memory Space` から Memory Space に戻れる。

## Local Login

seed 後の local user:

```text
Email: admin@example.test
Password: password
Role: owner
Tenant: default
```

API Bearer token:

```text
local-dev-token
```

Secret memory unlock password:

```text
secret-password
```

## Memory Space での使い方

`http://127.0.0.1:8000/memory-space` を開く。

local host では Bearer input が未設定、または古い `id|...` 形式の token が残っている場合、frontend が `local-dev-token` を自動入力する。

手動でログインする場合は、操作 panel を開いて次を入力する。

```text
Email: admin@example.test
Password: password
```

Secret memory を見る場合は `解除` で次を入力する。

```text
secret-password
```

## Admin UI での使い方

`http://127.0.0.1:8000/admin` を開く。

同一 origin の local host では固定 Bearer token `local-dev-token` が自動利用される。Settings で手動確認する場合:

```text
API Base URL: /api/v1
Bearer Token: local-dev-token
```

別 origin から静的 mockup として開く場合:

```text
API Base URL: http://127.0.0.1:8000/api/v1
Bearer Token: local-dev-token
```

## One-Time Token が必要な場合

固定 local token ではなく、任意の token を発行したい場合だけ使う。

```bash
php artisan bunshin:issue-admin-token \
  --tenant=default \
  --tenant-name="Default Tenant" \
  --email=admin@example.test \
  --name="Admin User" \
  --token-name=admin-mockup \
  --expires-days=30
```

command output の `Bearer token:` に続く値は 1 回だけ表示される。DB には hash だけが保存される。

## Troubleshooting

### 401 が出る

seed が流れているか確認する。

```bash
php artisan db:seed --force
curl -H 'Authorization: Bearer local-dev-token' http://127.0.0.1:8000/api/v1/memory-space
```

ブラウザに古い token が残っている場合は、Admin UI の Settings で `Token クリア` を押してから再読み込みする。直接消す場合は localStorage の `bunshin-admin-api-config` を削除する。

### DB table がない

```bash
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force
```

### APP_KEY がない

```bash
php artisan key:generate
```

### Vite asset が読めない

`composer dev` を使うか、別 terminal で Vite を起動する。

```bash
npm run dev -- --host 127.0.0.1 --port 5173
```

### 完全に作り直したい

local disposable DB だけで行う。

```bash
php artisan migrate:fresh --seed
```

この操作は local DB の既存データを消す。
