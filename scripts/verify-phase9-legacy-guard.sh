#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:${HOST_PORT:-8080}}"

echo "== nginx config =="
docker compose exec -T nginx nginx -t

echo "== legacy internal directories should be blocked =="
for path in /includes/config.php /includes/functions.php /bin/cron.php /includes/; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL$path")"
  if [ "$code" != "404" ]; then
    echo "ERROR: expected $path to return 404, got $code" >&2
    exit 1
  fi
  echo "$path -> $code"
done

echo "== legacy API fallback remains available for rollback/unmigrated endpoints =="
api_code="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/api/v1/index.php")"
if [ "$api_code" != "401" ] && [ "$api_code" != "404" ]; then
  echo "ERROR: unexpected /api/v1/index.php status $api_code" >&2
  exit 1
fi
echo "/api/v1/index.php -> $api_code"

echo "Phase 9.2 legacy guard verification passed."
