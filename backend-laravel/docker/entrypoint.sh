#!/bin/sh
set -eu

cd /app

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "[laravel-entrypoint] Running migrations..."
  php artisan migrate --force --no-interaction
fi

if [ "${AUTO_SEED:-true}" = "true" ]; then
  echo "[laravel-entrypoint] Running seeders..."
  php artisan db:seed --force --no-interaction
fi

echo "[laravel-entrypoint] Starting application..."
exec "$@"
