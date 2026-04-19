#!/bin/sh
set -eu

cd /var/www/html

INTERVAL="${CRON_INTERVAL:-60}"

mkdir -p data/db data/backups logs uploads/images uploads/knowledge

RSS_INTERVAL="${RSS_INTERVAL:-7200}"

echo "[scheduler] started with interval ${INTERVAL}s, RSS every ${RSS_INTERVAL}s"

TICK=0
while true; do
  php /var/www/html/bin/cron.php || true

  # RSS 抓取 (默认每 7200 秒 = 2 小时)
  if [ "$TICK" -eq 0 ] || [ "$((TICK % RSS_INTERVAL))" -lt "$INTERVAL" ]; then
    echo "[scheduler] running RSS fetcher..."
    php /var/www/html/bin/rss_fetcher.php || true
  fi

  TICK=$((TICK + INTERVAL))
  sleep "$INTERVAL"
done

