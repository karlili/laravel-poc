#!/bin/sh
# Development only: generate Wayfinder's TypeScript route helpers for the
# `vite` service, which has no PHP to run the command itself.
# No `exit 0` here: the image's entrypoint may source this file.

if [ "${COMPOSER_INSTALL_ON_START:-false}" = "true" ]; then
    echo "wayfinder: generating route helpers..."
    php /var/www/html/artisan wayfinder:generate --with-form --no-interaction \
        || { echo "wayfinder: php artisan wayfinder:generate failed" >&2; exit 1; }
fi
