#!/usr/bin/env bash
# Startup script for the Laravel API on Render (Docker).
# NOTE: no `set -e` — the web server must start even if a prep step fails,
# otherwise a transient DB hiccup at boot would crash the whole container (502).

PORT="${PORT:-8000}"

echo "==> Caching config & routes"
php artisan config:cache || echo "WARN: config:cache failed (continuing)"
php artisan route:cache  || echo "WARN: route:cache failed (continuing)"

# Migrations are NOT run at boot anymore: the database is already migrated, and
# making startup depend on a remote DB connection caused 502s when Railway was
# briefly slow. Run migrations manually via Render Shell when you change schema:
#   php artisan migrate --force

echo "==> Starting PHP server on 0.0.0.0:${PORT} (router -> Laravel)"
# Built-in server with a ROUTER script so every request hits Laravel
# (otherwise routes like /api/health return 404). 'exec' replaces the shell so
# signals & port binding are handled correctly by Render.
exec php -S 0.0.0.0:"${PORT}" -t public server-router.php
