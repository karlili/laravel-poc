#!/bin/sh
# Development only: vendor/ lives in the bind-mounted source tree, not the image,
# so the app container installs dependencies on start.
# No `exit 0` here: the image's entrypoint may source this file.

if [ "${COMPOSER_INSTALL_ON_START:-false}" = "true" ]; then
    echo "composer-install: installing PHP dependencies..."
    composer install --no-interaction --no-progress --working-dir=/var/www/html \
        || { echo "composer-install: composer install failed" >&2; exit 1; }
fi
