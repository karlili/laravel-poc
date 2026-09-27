#!/bin/sh
# Development only: bring the local database schema up to date on start and
# sync roles and permissions, as the production migrate job does (see infra/terraform).
# ProductionSeeder is idempotent and creates no demo data.
# No `exit 0` here: the image's entrypoint may source this file.

if [ "${MIGRATE_ON_START:-false}" = "true" ]; then
    echo "migrate: running database migrations..."
    php /var/www/html/artisan migrate --force --no-interaction \
        || { echo "migrate: php artisan migrate failed" >&2; exit 1; }

    echo "migrate: syncing roles and permissions..."
    php /var/www/html/artisan db:seed --class='Database\Seeders\ProductionSeeder' --force --no-interaction \
        || { echo "migrate: php artisan db:seed failed" >&2; exit 1; }
fi
