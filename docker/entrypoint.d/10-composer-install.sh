#!/bin/sh
# Development only: vendor/ lives in the bind-mounted source tree, not the image.
# The app container installs dependencies on start; the queue and scheduler
# containers share the same mount, so they wait for the install instead of racing it.
# No `exit 0` here: the image's entrypoint may source this file.

if [ "${COMPOSER_INSTALL_ON_START:-false}" = "true" ]; then
    echo "composer-install: installing PHP dependencies..."
    composer install --no-interaction --no-progress --working-dir=/var/www/html \
        || { echo "composer-install: composer install failed" >&2; exit 1; }
elif [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "composer-install: waiting for the app container to install PHP dependencies..."
    waited=0
    while [ ! -f /var/www/html/vendor/autoload.php ]; do
        if [ "$waited" -ge 300 ]; then
            echo "composer-install: vendor/autoload.php still missing after 300s" >&2
            exit 1
        fi
        sleep 2
        waited=$((waited + 2))
    done
fi
