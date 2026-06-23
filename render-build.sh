#!/usr/bin/env bash
# Build step for the Laravel API on Render.
# Runs once per deploy: install deps, cache config, run migrations.
set -o errexit   # stop on first error

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Caching framework config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Running database migrations"
php artisan migrate --force

# Optional: seed reference data the first time (stores, sample catalog).
# Comment out after the first successful deploy to avoid duplicating data.
# php artisan db:seed --force

echo "==> Build finished"
