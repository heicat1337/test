#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:${HOST_PORT:-8080}}"

echo "== nginx config =="
docker compose exec -T nginx nginx -t

echo "== Laravel sitemap tests =="
docker compose exec -T laravel php artisan test --filter=SitemapControllerTest

echo "== Laravel route exists =="
docker compose exec -T laravel php artisan route:list --path=sitemap > /tmp/phase9_sitemap_routes.txt
grep -q 'sitemap.xml' /tmp/phase9_sitemap_routes.txt

echo "== public sitemap should be XML from Laravel =="
headers="$(mktemp)"
body="$(mktemp)"
curl -sS -D "$headers" "$BASE_URL/sitemap.xml" -o "$body"
grep -qi 'content-type: application/xml' "$headers"
grep -q '<?xml version="1.0" encoding="UTF-8"?>' "$body"
grep -q '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' "$body"
grep -q '<loc>.*/articles</loc>' "$body"
grep -q '<loc>.*/c/' "$body"

if grep -q '<html' "$body"; then
  echo "ERROR: sitemap returned HTML, expected XML" >&2
  exit 1
fi

echo "Phase 9.1 sitemap verification passed."
