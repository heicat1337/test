#!/bin/bash
cd /opt/web3 || exit 1
git pull || exit 1
docker compose down
docker compose up -d --build
echo "Deploy complete. Waiting for containers..."
sleep 3
docker compose ps
