<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\Ai\ArticleGenerationEngine;
use App\Services\Tasks\JobQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Laravel 版的 bin/worker.php「常驻 worker」。
 *
 * 用法：
 *   php artisan geoflow:worker           # 默认 while(true) 长驻
 *   php artisan geoflow:worker --once    # 只处理一个 job 然后退出（CI / 测试用）
 *
 * 行为与老 worker.php 对齐：
 *   - 心跳：每轮写 worker_heartbeats（worker_id 主键 + status + last_seen_at）
 *   - recoverStaleJobs：每轮先 recover
 *   - claimNextJob：原子领取
 *   - 执行 ArticleGenerationEngine::executeTask
 *   - 成功 → completeJob；任务被停止 → cancelJob；其它 → failJob 自动重试
 */
class GeoFlowWorker extends Command
{
    protected $signature = 'geoflow:worker {--once : 处理一个 job 就退出}';

    protected $description = '常驻 worker：消费 job_queue 表，跑 ArticleGenerationEngine';

    private const IDLE_SLEEP_SECONDS = 5;

    public function handle(JobQueueService $queue, ArticleGenerationEngine $engine): int
    {
        $workerId = gethostname() . ':' . getmypid();
        $this->info('[' . Carbon::now()->toDateTimeString() . "] worker 启动: {$workerId}");
        $this->heartbeat($workerId, 'idle', null);

        $once = (bool) $this->option('once');

        while (true) {
            try {
                $this->heartbeat($workerId, 'idle', null);
                $queue->recoverStaleJobs();
                $job = $queue->claimNextJob($workerId);

                if (!$job) {
                    if ($once) {
                        $this->info('空闲 + --once：退出');
                        return self::SUCCESS;
                    }
                    sleep(self::IDLE_SLEEP_SECONDS);
                    continue;
                }

                $this->processJob($job, $queue, $engine, $workerId);

                if ($once) {
                    return self::SUCCESS;
                }
            } catch (Throwable $e) {
                $this->error('worker 异常: ' . $e->getMessage());
                $this->heartbeat($workerId, 'error', null, ['message' => $e->getMessage()]);
                sleep(self::IDLE_SLEEP_SECONDS);
                if ($once) {
                    return self::FAILURE;
                }
            }
        }
    }

    private function processJob(array $job, JobQueueService $queue, ArticleGenerationEngine $engine, string $workerId): void
    {
        $jobId  = (int) $job['id'];
        $taskId = (int) $job['task_id'];
        $startedAt = microtime(true);
        $this->info("[" . Carbon::now()->toDateTimeString() . "] 领取 job #{$jobId}, task #{$taskId}");
        $this->heartbeat($workerId, 'running', $jobId, ['task_id' => $taskId]);

        try {
            $result = $engine->executeTask($taskId);
        } catch (Throwable $e) {
            $duration = (int) round((microtime(true) - $startedAt) * 1000);
            $message = $e->getMessage();
            if ($this->isStopRequested($taskId, $message)) {
                $queue->cancelJob($jobId, $taskId, '管理员手动停止');
                $this->heartbeat($workerId, 'idle', null, ['last_job_id' => $jobId]);
                $this->info("job #{$jobId} 已按停止请求取消");
                return;
            }
            $queue->failJob($jobId, $taskId, $message, $duration);
            $this->heartbeat($workerId, 'idle', null, ['last_job_id' => $jobId]);
            $this->error("job #{$jobId} 异常: {$message}");
            return;
        }

        $duration = (int) round((microtime(true) - $startedAt) * 1000);
        if (!empty($result['success'])) {
            $queue->completeJob(
                $jobId, $taskId,
                isset($result['article_id']) ? (int) $result['article_id'] : null,
                $duration,
                [
                    'title'   => $result['title'] ?? '',
                    'message' => $result['message'] ?? '',
                ],
            );
            $this->heartbeat($workerId, 'idle', null, ['last_job_id' => $jobId]);
            $this->info("job #{$jobId} 成功");
            return;
        }

        $error = (string) ($result['error'] ?? '未知错误');
        if ($this->isStopRequested($taskId, $error)) {
            $queue->cancelJob($jobId, $taskId, '管理员手动停止');
            $this->heartbeat($workerId, 'idle', null, ['last_job_id' => $jobId]);
            $this->info("job #{$jobId} 已按停止请求取消");
            return;
        }

        $queue->failJob($jobId, $taskId, $error, $duration);
        $this->heartbeat($workerId, 'idle', null, ['last_job_id' => $jobId]);
        $this->warn("job #{$jobId} 失败: {$error}");
    }

    private function isStopRequested(int $taskId, string $message): bool
    {
        $task = Task::query()->whereKey($taskId)->first(['status', 'schedule_enabled']);
        if (!$task) {
            return true;
        }
        $stopped = ($task->status ?? 'active') !== 'active'
            || (int) ($task->schedule_enabled ?? 1) !== 1;
        if ($stopped) {
            return true;
        }
        return str_contains($message, '任务已被管理员停止')
            || str_contains($message, '管理员手动停止')
            || str_contains($message, '任务未激活');
    }

    private function heartbeat(string $workerId, string $status, ?int $jobId, array $meta = []): void
    {
        try {
            DB::statement('
                INSERT INTO worker_heartbeats (worker_id, status, current_job_id, last_seen_at, meta, created_at, updated_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (worker_id) DO UPDATE SET
                    status = EXCLUDED.status,
                    current_job_id = EXCLUDED.current_job_id,
                    last_seen_at = CURRENT_TIMESTAMP,
                    meta = EXCLUDED.meta,
                    updated_at = CURRENT_TIMESTAMP
            ', [
                $workerId, $status, $jobId,
                json_encode(array_merge(['pid' => getmypid()], $meta), JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            // heartbeat 失败不致命
        }
    }
}
