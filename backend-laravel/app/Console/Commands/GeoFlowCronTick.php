<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\Task;
use App\Services\Tasks\JobQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Laravel 版的 bin/cron.php「轻量调度器」。每分钟从 Schedule 触发。
 *
 * 主流程：
 *   1. JobQueueService::recoverStaleJobs（>10 分钟 running 回 pending）
 *   2. 扫 active + schedule_enabled 的 task
 *      - 草稿满了 → 跳过
 *      - next_run_at 还没到 → 跳过
 *      - 已有 pending/running job → 跳过
 *      - 入队，更新 next_run_at = now + publish_interval
 *   3. 清 task_schedules 7 天前数据
 *   4. resetDailyAIUsage：跨日把 used_today 归零
 *
 * 自动发布 auto_publish 不在这里，由 GeoFlowAutoPublish 单独命令处理
 * （Phase 7.4），与老 bin/auto_publish.php 对齐。
 */
class GeoFlowCronTick extends Command
{
    protected $signature = 'geoflow:cron-tick';

    protected $description = 'GeoFlow 调度一次：恢复卡死 job + 扫活跃任务入队 + 日常 reset';

    public function handle(JobQueueService $queue): int
    {
        $start = microtime(true);
        $recovered = $queue->recoverStaleJobs();
        if ($recovered > 0) {
            $this->info("恢复 {$recovered} 个卡死 job");
        }

        $tasks = Task::query()
            ->where('status', 'active')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        $this->info("扫描到 {$tasks->count()} 个活跃任务");

        $queued = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            if ((int) ($task->schedule_enabled ?? 1) !== 1) {
                $skipped++;
                continue;
            }

            // 草稿满
            $draftCount = DB::table('articles')
                ->where('task_id', $task->id)
                ->where('status', 'draft')
                ->whereNull('deleted_at')
                ->count();
            if ($draftCount >= (int) ($task->draft_limit ?? 0) && $task->draft_limit > 0) {
                $this->line("任务 {$task->name} 草稿已满 ({$draftCount}/{$task->draft_limit})，跳过");
                $skipped++;
                continue;
            }

            // next_run_at 未到
            if (!$task->next_run_at) {
                $queue->initializeTaskSchedule($task->id);
                $this->line("任务 {$task->name} 初始化 next_run_at");
                $skipped++;
                continue;
            }
            if ($task->next_run_at->isFuture()) {
                $skipped++;
                continue;
            }

            // 已有未完成 job
            if ($queue->hasPendingOrRunningJob($task->id)) {
                $skipped++;
                continue;
            }

            $jobId = $queue->enqueueTaskJob($task->id);
            if ($jobId === null) {
                $skipped++;
                continue;
            }

            $nextRunAt = Carbon::now()->addSeconds(max(60, (int) $task->publish_interval));
            Task::whereKey($task->id)->update([
                'next_run_at' => $nextRunAt,
                'updated_at'  => Carbon::now(),
            ]);
            $queued++;
            $this->line("任务 {$task->name} 入队 job #{$jobId}, 下次 {$nextRunAt->toDateTimeString()}");
        }

        $this->cleanupTaskSchedules();
        $this->resetDailyAiUsage();

        $duration = round(microtime(true) - $start, 2);
        $this->info("完成: 入队 {$queued}, 跳过 {$skipped}, 用时 {$duration}s");

        // 写 system_logs（与老一致，便于双跑期间监控）
        try {
            DB::table('system_logs')->insert([
                'type'    => 'cron',
                'message' => "geoflow:cron-tick: 入队 {$queued}, 跳过 {$skipped}",
                'data'    => json_encode([
                    'queued_count'    => $queued,
                    'skipped_count'   => $skipped,
                    'recovered_count' => $recovered,
                    'execution_time'  => $duration,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            // system_logs 可能不存在 - 不致命
            $this->warn('写 system_logs 失败: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }

    private function cleanupTaskSchedules(): void
    {
        try {
            DB::table('task_schedules')
                ->where('created_at', '<', Carbon::now()->subDays(7))
                ->delete();
        } catch (Throwable $e) {
            // 表可能不存在；不致命
        }
    }

    private function resetDailyAiUsage(): void
    {
        // 跨日重置 used_today。与老 resetDailyAIUsage 行为对齐。
        DB::statement("
            UPDATE ai_models
            SET used_today = 0, updated_at = CURRENT_TIMESTAMP
            WHERE DATE(updated_at) < ?
              AND used_today > 0
        ", [Carbon::today()->toDateString()]);
    }
}
