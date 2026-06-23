#!/usr/bin/env bash
# Start step for the Laravel API on Render.
# Render injects $PORT — serve Laravel's public/ directory on it.
set -o errexit

echo "==> Starting Laravel on port ${PORT:-8000}"
php artisan serve --host 0.0.0.0 --port "${PORT:-8000}"
