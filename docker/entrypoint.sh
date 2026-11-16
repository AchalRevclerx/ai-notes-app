#!/bin/sh
set -e

# One-time setup runs only in the main app container, not in the queue worker.
if [ "$1" = "php-fpm" ]; then
    [ -f .env ] || cp .env.example .env

    # The vendor dir is a named volume; reinstall when composer.lock changes.
    if [ ! -f vendor/autoload.php ] || ! cmp -s composer.lock vendor/.composer.lock; then
        composer install --no-interaction --prefer-dist
        cp composer.lock vendor/.composer.lock
    fi

    grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
    php artisan migrate --force

    # Bind-mounted source: make sure php-fpm (www-data) can write logs and caches.
    chmod -R a+rwX storage bootstrap/cache
fi

exec "$@"
