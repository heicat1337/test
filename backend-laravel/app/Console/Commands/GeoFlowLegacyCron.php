<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 调用老 backend 的 bin/cron.php（保留 GEOFlow 现有调度逻辑）。
 *
 * 双跑期间：
 *   - 默认情况：docker-compose `scheduler` profile 跑老 backend scheduler 容器（推荐）
 *   - 备份方案：用 Laravel Scheduler 跑此命令（routes/console.php 注释中提供）
 *
 * 此 command 通过 docker exec 触发——前提是同一个 docker daemon 可见。
 * 如果 Laravel 与 geoflow 不共享 docker socket，请改用 process_open / shell_exec 直接
 * 拼老 backend 的 PHP 可执行路径。
 */
class GeoFlowLegacyCron extends Command
{
    protected $signature = 'geoflow:cron {--script=cron : 要跑的 bin/ 脚本名（cron / auto_publish / db_maintenance / health_check_cron / rss_fetcher）}';

    protected $description = '触发老 backend bin/{script}.php 执行（双跑兼容）';

    public function handle(): int
    {
        $script = (string) $this->option('script');
        $allowed = ['cron', 'auto_publish', 'db_maintenance', 'health_check_cron', 'rss_fetcher'];
        if (!in_array($script, $allowed, true)) {
            $this->error("Invalid script: {$script}. Allowed: " . implode(', ', $allowed));
            return self::FAILURE;
        }

        // 通过 docker exec 调用同网络 geoflow 容器里的 bin/cron.php
        // 注意：Laravel 容器若没挂 /var/run/docker.sock 会失败；
        //      这种情况下请用老 scheduler 容器替代。
        $cmd = sprintf('docker exec web3-geoflow php /var/www/html/bin/%s.php 2>&1', escapeshellarg($script));
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        foreach ($output as $line) {
            $this->line($line);
        }
        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
