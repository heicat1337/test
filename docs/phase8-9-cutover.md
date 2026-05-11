# Phase 8 / Phase 9 Cutover Notes

## Current status

### Phase 8 — SSR `render_seo.php` → Laravel Blade

Status: **functionally complete, keep legacy fallback for rollback**.

Crawler-only SEO HTML is now served by Laravel Blade through nginx internal rewrites:

| Public URL | Crawler internal nginx target | Laravel route |
| --- | --- | --- |
| `/` | `/__seo_home` | `GET /__seo/home` |
| `/c/:slug` | `/__seo_category/:slug` | `GET /__seo/category/{slug}` |
| `/project/:id` | `/__seo_project/:id` | `GET /__seo/project/{id}` |

Important guardrails:

- Human traffic still receives the Vue SPA.
- Public `/__seo/*` URLs are blocked by nginx.
- Legacy `backend/render_seo.php` remains in the repository and image as rollback fallback.
- Do **not** remove `geoflow` yet.

Validation commands:

```bash
docker compose exec -T nginx nginx -t
docker compose exec -T laravel php artisan test --filter=SeoControllerTest

# Crawler should receive Laravel SEO HTML, not the SPA boot shell.
curl -sS -A 'Googlebot/2.1' http://127.0.0.1:${HOST_PORT:-8080}/ | grep 'application/ld+json'

# Human should still receive SPA.
curl -sS -A 'Mozilla/5.0' http://127.0.0.1:${HOST_PORT:-8080}/ | grep 'id="app"'

# Public SEO internals must stay blocked.
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:${HOST_PORT:-8080}/__seo/home
```

Expected results:

- `nginx -t` succeeds.
- SEO tests pass.
- Googlebot `/` includes `application/ld+json`.
- Human `/` includes the Vue app shell.
- `/__seo/home` returns `404`.

## Phase 9 — full traffic cutover while keeping legacy backend parked

Status: **not complete**.

Phase 9 should not delete the old backend immediately. The safe target is:

1. Route all production user-facing traffic to Laravel/nginx/Vue where possible.
2. Keep `geoflow` available but unexposed or minimally exposed for rollback/admin-only needs.
3. Stop old scheduler/worker double-run before enabling Laravel scheduler as the only producer.
4. Remove legacy code only after a separate soak period.

### Current legacy dependencies still present

nginx still routes these to `geoflow`:

| nginx path | Current target | Phase 9 action |
| --- | --- | --- |
| `/heicat` | `geoflow` | Keep temporarily as legacy admin/rollback unless Laravel parity is confirmed. |
| `/api` fallback | `geoflow` | Narrow this once access logs prove no unmigrated endpoints depend on it. |
| `/sitemap.xml` | `geoflow/sitemap.php` | Move to Laravel before full cutover. |
| `*.php` | `geoflow` | Replace broad fallback with an allowlist, then block arbitrary PHP paths. |
| `/themes`, `/lang`, `/includes` | `geoflow` | Needed only by legacy pages; remove after old UI is no longer public. |

Laravel already owns these API groups through nginx explicit routes:

| API group | Laravel status | Notes |
| --- | --- | --- |
| `/api/v1/nav/*` | Migrated | categories, sites, site detail, recommended. |
| `/api/v1/articles` | Migrated | Public article list and by-slug detail. |
| `/api/v1/catalog` | Migrated | Token + `catalog:read` scope. |
| `/api/v1/tasks/*` | Migrated | Token + task scopes. |
| `/api/v1/jobs/{id}` | Migrated | Token + `jobs:read` scope. |
| `/api/v1/admin/articles/*` | Migrated | Token + article scopes. |

Compose still starts `geoflow` and nginx depends on it. That is intentional during the safe cutover window.

### Recommended Phase 9 sequence

1. **Inventory legacy traffic**
   - Check access logs for `/api`, `*.php`, `/heicat`, `/sitemap.xml`, `/themes`, `/lang`, `/includes`.
   - Anything with real traffic needs either Laravel parity or an explicit keep decision.

2. **Move sitemap to Laravel** ✅ Phase 9.1 done
   - Laravel route/controller now serves `/sitemap.xml`.
   - nginx `/sitemap.xml` proxies to Laravel.
   - Legacy `backend/sitemap.php` remains available in the old backend for rollback.
   - Validate with `scripts/verify-phase9-sitemap.sh`.

3. **Freeze public PHP fallback** 🚧 Phase 9.2 started
   - Public access to legacy internal directories `/includes/` and `/bin/` is blocked.
   - Broad root PHP fallback is still retained for legacy rollback routes; theme/lang assets remain proxied.
   - Next step: replace broad `location ~ \.php$` with an explicit allowlist after traffic inventory is clean.
   - Validate with `scripts/verify-phase9-legacy-guard.sh`.

4. **Scheduler cutover**
   - Ensure old compose profile `scheduler` is not running.
   - Run Laravel schedule/worker as the only active scheduler/worker path.
   - Watch for duplicate jobs before and after cutover.

5. **API cutover**
   - Route known migrated `/api/v1/*` endpoints to Laravel.
   - Keep only explicitly unmigrated endpoints on geoflow.
   - Avoid a broad fallback once the inventory is clean.

6. **Park old backend**
   - Keep `geoflow` container available for rollback, but remove public routes to it.
   - After a soak period, remove old files/images in a separate cleanup phase.

### Do not do these yet

- Do not delete `backend/`.
- Do not remove the `geoflow` service from `docker-compose.yml`.
- Do not remove `render_seo.php` until production crawler SSR has soaked successfully.
- Do not run old scheduler and Laravel scheduler at the same time in production.
