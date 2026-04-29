#!/bin/bash
cd /opt/web3 || exit 1
git pull || exit 1

# 用 scheduler profile，确保 scheduler/worker 也跟着停 + 重建（否则它们会停在旧镜像）
docker compose --profile scheduler down

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
