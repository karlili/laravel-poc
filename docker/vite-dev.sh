#!/bin/sh
# Development only: start command for the `vite` service in docker-compose.yml.
# Installs dependencies and builds public/build, so the app still has assets
# once the dev server stops, then serves with hot reload.
set -e

# app.css imports Flux's stylesheet from vendor/, which the app container
# installs on start. Wait for it so a first run doesn't fail the build.
until [ -f vendor/autoload.php ] && [ -f vendor/livewire/flux/dist/flux.css ]; do
    echo "vite: waiting for the app container's composer install..."
    sleep 3
done

npm ci
npm run build

# The Laravel plugin writes public/hot while the dev server runs. Remove it on
# shutdown ourselves: the signal doesn't reliably reach the plugin through npm,
# and a stale file makes the app load assets from a dev server that is gone.
npm run dev -- --host 0.0.0.0 &
pid=$!
trap 'kill "$pid" 2>/dev/null; rm -f public/hot; exit 0' INT TERM
wait "$pid"
