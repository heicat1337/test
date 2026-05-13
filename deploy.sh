#!/bin/bash
cd /opt/web3 || exit 1
git pull || exit 1

# 用 scheduler profile，并清掉旧版本遗留的 orphan 容器（例如老 scheduler/worker）
docker compose --profile scheduler down --remove-orphans

# 保险：旧版服务已经从 compose 移除，但某些 Docker Compose 版本不会彻底清 orphan
# 这里显式清掉旧 scheduler/worker，避免新旧调度器双跑。
docker rm -f web3-scheduler web3-worker 2>/dev/null || true

# Laravel 代码是 bind mount 到容器里的，服务器上必须有 vendor/autoload.php。
# 用同一个 composer-intl 镜像安装依赖，避免宿主机依赖 PHP/Composer。
docker compose build laravel
if [ ! -f backend-laravel/vendor/autoload.php ] || [ backend-laravel/composer.lock -nt backend-laravel/vendor/autoload.php ]; then
  echo "==> Installing Laravel composer dependencies..."
  docker run --rm \
    -v "$PWD/backend-laravel:/app" \
    -w /app \
    composer-intl:local \
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader || exit 1
fi

# 构建并启动所有服务；如遇 buildkit 快照损坏（"parent snapshot ... does not exist"），
# 自动清掉 builder 缓存后重试一次（数据卷 pgdata 不受影响）
if ! docker compose --profile scheduler up -d --build; then
  echo "==> Build failed, cleaning buildkit cache and retrying..."
  docker builder prune -af
  docker compose --profile scheduler up -d --build || exit 1
fi

echo "Deploy complete. Waiting for containers..."
sleep 3
docker compose --profile scheduler ps
