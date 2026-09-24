# syntax=docker/dockerfile:1

# One image for every process: the web app, the queue worker, the scheduler
# and the migration job only differ by their start command.
#
#   docker build --target production -t laravel-crm .
#   docker build --target development -t laravel-crm-dev .   (used by compose.yaml)

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

############################################
# Base: PHP-FPM + NGINX with the extensions the CRM needs
############################################
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS base

USER root
RUN install-php-extensions bcmath exif gd intl

ENV HEALTHCHECK_PATH=/up \
    PHP_UPLOAD_MAX_FILE_SIZE=25M \
    PHP_POST_MAX_SIZE=110M \
    NGINX_CLIENT_MAX_BODY_SIZE=110M

USER www-data

############################################
# Development: source code is bind-mounted by compose.yaml
############################################
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

USER root
# Match www-data to the host user so files written by the container stay editable.
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
    && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx
USER www-data

############################################
# Composer dependencies (production only)
############################################
FROM base AS vendor

WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize

############################################
# Frontend assets. Tailwind scans Flux's Blade stubs, so this stage needs vendor/.
############################################
FROM node:${NODE_VERSION}-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /var/www/html/vendor/livewire/flux ./vendor/livewire/flux
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

############################################
# Production
############################################
FROM base AS production

# Cache config, routes, views and events at start-up. Migrations run as a
# separate job (see infra/terraform), never on web start-up.
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=false \
    LOG_CHANNEL=stderr \
    LOG_STACK=stderr

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
