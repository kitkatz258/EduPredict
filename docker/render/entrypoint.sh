#!/bin/sh
# Boot steps for the Render image. Render free instances have no shell or
# pre-deploy hook, so migrations and the one-time demo seed run here.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Render injects RENDER_EXTERNAL_URL (https://<name>.onrender.com).
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

# Apache must listen on the port Render assigns.
PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# A paused or cold database can refuse the first connections.
attempt=1
until php artisan migrate --force; do
    if [ "$attempt" -ge 5 ]; then
        echo "Migrations failed after ${attempt} attempts." >&2
        exit 1
    fi
    echo "Migration attempt ${attempt} failed; retrying in 10s." >&2
    attempt=$((attempt + 1))
    sleep 10
done

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    php artisan edupredict:seed-demo-if-empty --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
