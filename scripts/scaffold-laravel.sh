#!/usr/bin/env bash
# scaffold-laravel.sh
#
# Phase 0：在 test/ 下用 Docker scaffold Laravel 12 + Filament 4 + Sanctum + Pest
# 到 backend-laravel/。
#
# 复跑安全：目标目录已存在则报错退出，不会覆盖现有文件。
#
# 使用：
#   cd /Users/heicat/xuaweb3/test
#   bash scripts/scaffold-laravel.sh
#
# 前置：Docker Desktop 已运行。

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_DIR="$ROOT_DIR/backend-laravel"
COMPOSER_IMG="composer-intl:local"

if [ -e "$TARGET_DIR" ]; then
  echo "[ABORT] $TARGET_DIR 已存在。要重新 scaffold 请先手动删除。"
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  echo "[ABORT] Docker 未运行。请先启动 Docker Desktop。"
  exit 1
fi

# 0) 先建一个带 ext-intl + ext-pdo_pgsql 的 composer 镜像。
#    Filament 4 强依赖 intl；composer:2 官方镜像不带。
echo "[0/4] docker build $COMPOSER_IMG ..."
docker build -q -t "$COMPOSER_IMG" -f "$ROOT_DIR/scripts/Dockerfile.composer-intl" "$ROOT_DIR/scripts/" >/dev/null

# 1) Laravel 12 scaffold
echo "[1/4] composer create-project laravel/laravel:^12.0 ..."
docker run --rm \
  -v "$ROOT_DIR":/app \
  -w /app \
  "$COMPOSER_IMG" \
  create-project laravel/laravel:^12.0 backend-laravel \
    --prefer-dist --no-interaction

# 2) Filament 4
#    注：Filament 3.x 全系列被 PKSA-1ds2-yqqr-64g1 拦截；
#       Filament 4.0–4.8.4 也有 PKSA-5bdf-2x61-v43c，4.8.5+ 已修复。
#       composer 会自动跳到 4.8.5+。
echo "[2/4] composer require filament/filament:^4.0 ..."
docker run --rm \
  -v "$TARGET_DIR":/app \
  -w /app \
  "$COMPOSER_IMG" \
  require filament/filament:^4.0 -W --no-interaction

# 3) Sanctum（API token 鉴权，替代旧 includes/api_token_service.php）
echo "[3/4] composer require laravel/sanctum ..."
docker run --rm \
  -v "$TARGET_DIR":/app \
  -w /app \
  "$COMPOSER_IMG" \
  require laravel/sanctum --no-interaction

# 4) Pest（测试框架，可选；3.x 与 Laravel 12 自带的 PHPUnit 11 兼容）
#    注：Pest 4.x 强依赖 PHPUnit 12，但 Laravel 12 默认锁 11，所以必须加 -W。
#    composer 会按 Laravel 锁的 PHPUnit 上限解析到 Pest 3.8。
echo "[4/4] composer require pestphp/pest --dev ..."
docker run --rm \
  -v "$TARGET_DIR":/app \
  -w /app \
  "$COMPOSER_IMG" \
  require pestphp/pest -W --dev --no-interaction

# 5) 给 .env 一份本地开发模板（合并进 backend-laravel/.env 即可）
cat > "$TARGET_DIR/.env.local.example" <<'EOF'
# Phase 0：本地开发用。把这份内容合并进 .env 后再启动。
APP_NAME="Xua Web3 Laravel"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:18081

# 共用 test/docker-compose.yml 起的 Postgres（host 暴露端口 15432）
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=15432
DB_DATABASE=geo_system
DB_USERNAME=geo_user
DB_PASSWORD=geo_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILAMENT_FILESYSTEM_DISK=public
EOF

echo
echo "✅ Scaffold 完成。"
echo
echo "   Laravel    $(grep -A1 '"name": "laravel/framework"' "$TARGET_DIR/composer.lock" | grep version | head -1 | cut -d'"' -f4)"
echo "   Filament   $(grep -A1 '"name": "filament/filament"'  "$TARGET_DIR/composer.lock" | grep version | head -1 | cut -d'"' -f4)"
echo "   Sanctum    $(grep -A1 '"name": "laravel/sanctum"'    "$TARGET_DIR/composer.lock" | grep version | head -1 | cut -d'"' -f4)"
echo "   Pest       $(grep -A1 '"name": "pestphp/pest"'       "$TARGET_DIR/composer.lock" | grep version | head -1 | cut -d'"' -f4)"
echo
echo "下一步："
echo "  1) 把 backend-laravel/.env.local.example 合并进 backend-laravel/.env"
echo "  2) test/ 下：docker compose up -d postgres   # 让 PG 跑起来"
echo "  3) 跑 Phase 0 schema 升级（详见 migrations/README.md）"
echo "  4) 进 Phase 1：写 NavCategory / NavSite Eloquent 模型 + Filament Resource"
