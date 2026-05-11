<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 数据库维护（对应老 bin/db_maintenance.php）：
 *   - check：连通性 + 表数 + 当前 database 名
 *   - cleanup：清 view_logs / system_logs 30 天前数据
 *   - vacuum：PG VACUUM ANALYZE 关键表（articles / job_queue / task_runs）
 *
 * Laravel 用 PG，不再走 SQLite 备份分支。
 */
class GeoFlowDbMaintenance extends Command
{
    protected $signature = 'geoflow:db-maintenance {action=check : check|cleanup|vacuum}';

    protected $description = '数据库维护：check / cleanup / vacuum';

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'check'   => $this->check(),
            'cleanup' => $this->cleanup(),
            'vacuum'  => $this->vacuum(),
            default   => $this->reportError("未知 action: {$action}（仅支持 check / cleanup / vacuum）"),
        };
    }

    private function reportError(string $msg): int
    {
        $this->error($msg);
        return self::FAILURE;
    }

    private function check(): int
    {
        try {
            $tableCount = (int) DB::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema()");
            $database = DB::scalar("SELECT current_database()");
        } catch (Throwable $e) {
            $this->error('数据库连接失败: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line("driver: pgsql");
        $this->line("database: {$database}");
        $this->line("host: " . (env('DB_HOST') ?: 'postgres'));
        $this->line("table_count: {$tableCount}");
        $this->line("connection_check: ok");
        return self::SUCCESS;
    }

    private function cleanup(): int
    {
        $cutoff = Carbon::now()->subDays(30);

        $viewLogs = 0;
        $sysLogs = 0;

        try {
            $viewLogs = DB::table('view_logs')->where('created_at', '<', $cutoff)->delete();
        } catch (Throwable $e) {
            // 表不存在不致命
        }

        try {
            $sysLogs = DB::table('system_logs')->where('created_at', '<', $cutoff)->delete();
        } catch (Throwable $e) {
            // 表不存在不致命
        }

        $this->info("清理 view_logs: {$viewLogs} 行");
        $this->info("清理 system_logs: {$sysLogs} 行");
        return self::SUCCESS;
    }

    private function vacuum(): int
    {
        $tables = ['articles', 'job_queue', 'task_runs', 'worker_heartbeats', 'view_logs', 'system_logs'];
        foreach ($tables as $table) {
            try {
                DB::statement("VACUUM ANALYZE {$table}");
                $this->line("VACUUM ANALYZE {$table} ok");
            } catch (Throwable $e) {
                $this->warn("VACUUM {$table} 失败: " . $e->getMessage());
            }
        }
        return self::SUCCESS;
    }
}
