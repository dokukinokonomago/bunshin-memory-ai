#!/bin/bash
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

until php -r '
$host = getenv("DB_HOST") ?: "db";
$port = getenv("DB_PORT") ?: "3306";
$database = getenv("DB_DATABASE") ?: "bunshin_ai";
$user = getenv("DB_USERNAME") ?: "bunshin";
$password = getenv("DB_PASSWORD") ?: "secret";
try {
    new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $password);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
'; do
  sleep 2
done

php artisan migrate --force

exec apache2-foreground
