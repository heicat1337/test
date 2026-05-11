<?php

namespace App\Console\Commands;

use App\Services\Tasks\JobQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 队列健康检查（对应老 bin/health_check_cron.php）：
 *   - recoverStaleJobs：超时 running job 回 pending
 *   - cleanup stale worker_heartbeats（>10 分钟没心跳）
 *   - 打印当前 job_queue 状态分布
 */
class GeoFlowHealthCheck extends Command
{
    protected $signature = 'geoflow:health-check';

    protected $description = '队列健康检查 + 清理过期 worker_heartbeats';

    public function handle(JobQueueService $queue): int
    {
        $recovered = $queue->recoverStaleJobs();
        if ($recovered > 0) {
            $this->info("恢复 {$recovered} 个卡死 job");
        }

        $cutoff = Carbon::now()->subMinutes(10);
        $stale = DB::table('worker_heartbeats')
            ->where('last_seen_at', '<', $cutoff)
            ->delete();
        if ($stale > 0) {
            $this->info("清理 {$stale} 个过期 worker_heartbeat");
        }

        $stats = DB::table('job_queue')
            ->select('status', DB::raw('COUNT(*) AS count'))
            ->groupBy('status')
            ->get();
        $this->info('队列分布:');
        foreach ($stats as $row) {
            $this->line(sprintf('  %-10s %d', $row->status, $row->count));
        }

        try {
            DB::table('system_logs')->insert([
                'type'    => 'health_check',
                'message' => '健康检查完成',
                'data'    => json_encode([
                    'recovered_jobs' => $recovered,
                    'stale_workers'  => $stale,
                    'queue_stats'    => $stats->keyBy('status')->map(fn ($r) => (int) $r->count)->all(),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            // 表不存在不致命
        }

        return self::SUCCESS;
    }
}
