#!/usr/bin/env bash
# Startup script for the Laravel API on Render (Docker).
set -e

PORT="${PORT:-8000}"

echo "==> Caching config & routes"
php artisan config:cache || true
php artisan route:cache || true

echo "==> Running migrations (idempotent)"
php artisan migrate --force || echo "WARN: migrate failed (continuing)"

echo "==> Starting PHP server on 0.0.0.0:${PORT} (router -> Laravel)"
# Built-in server with a ROUTER script so every request hits Laravel
# (otherwise routes like /api/health return 404 -> health check fails).
# 'exec' replaces the shell so signals & port binding are handled correctly.
exec php -S 0.0.0.0:"${PORT}" -t public server-router.php
