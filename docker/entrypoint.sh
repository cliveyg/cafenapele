#!/bin/sh
set -e
cd /var/www/html

cp -a /usr/local/share/tastyigniter-public/. public/

# Keep .env on the persistent storage volume so APP_KEY etc. survive container re-creation.
PERSIST_ENV=/var/www/html/storage/.env
if [ ! -f "$PERSIST_ENV" ]; then
    if [ -f .env ] && [ ! -L .env ]; then
        cp .env "$PERSIST_ENV"
    else
        cp .env.example "$PERSIST_ENV"
    fi
fi
ln -sfn "$PERSIST_ENV" .env

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

# Wait for MySQL
echo "Waiting for database ${DB_HOST}:${DB_PORT:-3306}..."
i=0
until mysqladmin ping --skip-ssl-verify-server-cert -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent 2>/dev/null; do
    i=$((i+1))
    if [ "$i" -ge 60 ]; then echo "Database not reachable" >&2; exit 1; fi
    sleep 3
done

# Generate an APP_KEY once if not supplied
if ! grep -Eq '^APP_KEY=.+' "$PERSIST_ENV" && [ -z "${APP_KEY}" ]; then
    php artisan key:generate --force
fi

php artisan storage:link >/dev/null 2>&1 || true
chown -R www-data:www-data public storage bootstrap/cache extensions themes 2>/dev/null || true

exec docker-php-entrypoint "$@"
