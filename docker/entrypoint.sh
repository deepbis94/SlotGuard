#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "${DB_CONNECTION}" = "pgsql" ] && [ -n "${DB_HOST}" ]; then
    echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
    i=0
    until php -r '
        try {
            new PDO(
                sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s",
                    getenv("DB_HOST"),
                    getenv("DB_PORT") ?: "5432",
                    getenv("DB_DATABASE")
                ),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    '; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "PostgreSQL did not become ready in time."
            exit 1
        fi
        sleep 2
    done
    echo "PostgreSQL is up."
fi

exec "$@"
