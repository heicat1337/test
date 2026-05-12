#!/bin/sh
set -eu

cd /app

echo "[laravel-entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

if [ "${AUTO_SEED:-true}" = "true" ]; then
  echo "[laravel-entrypoint] Running seeders..."
  php artisan db:seed --force --no-interaction
fi

echo "[laravel-entrypoint] Starting application..."
exec "$@"
