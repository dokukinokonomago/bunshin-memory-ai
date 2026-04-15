# 分身AI MVP

Laravel + MySQL を Docker 上で動かす構成です。記憶の登録、一覧、詳細、年代フィルター、削除を実装しています。

## 起動

```bash
docker compose up --build -d
```

アプリ:

```text
http://localhost:28080
```

MySQL:

```text
host: 127.0.0.1
port: 13306
database: bunshin_ai
user: bunshin
password: secret
root password: root
```

## 構成

- `docker-compose.yml`: Laravel と MySQL の起動設定
- `docker/app/Dockerfile`: PHP 8.3 + Apache + pdo_mysql
- `docker/app/start-container.sh`: DB待機と migration 自動実行
- `app/Http/Controllers/MemoryController.php`: MVPの画面処理
- `app/Models/Memory.php`: 記憶モデル
- `database/migrations/2026_04_15_000100_create_memories_table.php`: `memories` テーブル
- `resources/views/memories/*.blade.php`: 画面テンプレート

## 補足

- Webポートは `28080`、MySQLポートは `13306` に変更しています。
- コンテナ起動時に `php artisan migrate --force` を実行します。
