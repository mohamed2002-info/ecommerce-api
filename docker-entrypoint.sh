#!/usr/bin/env bash
# Startup script for the Laravel API on Render (Docker).
set -e

PORT="${PORT:-8000}"

echo "==> Caching config & routes"
php artisan config:cache || true
php artisan route:cache || true

echo "==> Running migrations (idempotent)"
php artisan migrate --force || echo "WARN: migrate failed (continuing)"

echo "==> Starting PHP server on 0.0.0.0:${PORT} (public/)"
# Use PHP's built-in server pointed at public/ — binds reliably to $PORT.
# 'exec' replaces the shell so signals & port binding are handled correctly.
exec php -S 0.0.0.0:"${PORT}" -t public public/index.php
