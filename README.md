# 分身AI バックエンド

この repository は、既存 MVP 資材を `legacy_assets/20260504_004800_existing_assets/` に退避したうえで、新規設計から作り直すバックエンドです。

## 現在の方針

- 既存 MVP の画面・Controller・DB 設計は legacy として扱う。
- 新規実装は API-first の Laravel バックエンドとして進める。
- まず設計ドキュメント、API 契約、データモデルを固定し、その後に migration / model / API を小さく実装する。
- 1 回の automation では正式 task を 1 つだけ進める。

## 新規設計ドキュメント

- [バックエンド設計](docs/architecture/backend_design.md)
- [データモデル設計](docs/architecture/data_model.md)
- [API 契約](docs/architecture/api_contract.md)
- [Fresh start decision](docs/decisions/0001-fresh-start.md)
- [OpenAPI draft](openapi/bunshin-memory-api.yaml)
- [別環境での復元とログイン手順](docs/operations/local_restore_login_runbook.md)

## 開発

```bash
composer install
php artisan test
composer dev
```

`composer dev` は起動前に `php artisan migrate --force` と `php artisan db:seed --force` を実行する。local 環境では `admin@example.test` / `password`、固定 Bearer token `local-dev-token`、secret unlock password `secret-password`、確認用 category / memory / tag data が自動で用意される。

ヘルスチェック:

```text
GET /api/v1/health
```

## 次に作るもの

次回 automation は `memories` / `categories` / `tags` の migration と Eloquent model の最小セットから開始する。
