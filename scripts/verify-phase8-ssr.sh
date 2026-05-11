#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:${HOST_PORT:-8080}}"

echo "== nginx config =="
docker compose exec -T nginx nginx -t

echo "== Laravel SEO tests =="
docker compose exec -T laravel php artisan test --filter=SeoControllerTest

echo "== crawler home should be SSR =="
home_html="$(mktemp)"
curl -sS -A 'Googlebot/2.1' "$BASE_URL/" -o "$home_html"
grep -q 'application/ld+json' "$home_html"
if grep -q 'id="app"' "$home_html"; then
  echo "ERROR: crawler home returned SPA app shell" >&2
  exit 1
fi

echo "== human home should stay SPA =="
human_html="$(mktemp)"
curl -sS -A 'Mozilla/5.0' "$BASE_URL/" -o "$human_html"
grep -q 'id="app"' "$human_html"

echo "== internal SEO route should be blocked publicly =="
code="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/__seo/home")"
if [ "$code" != "404" ]; then
  echo "ERROR: expected /__seo/home to return 404, got $code" >&2
  exit 1
fi

echo "== crawler category/project smoke =="
curl -sS -A 'Googlebot/2.1' "$BASE_URL/c/exchange" | grep -q 'application/ld+json'
curl -sS -A 'Googlebot/2.1' "$BASE_URL/project/1" | grep -q 'application/ld+json'

echo "Phase 8 SSR verification passed."
