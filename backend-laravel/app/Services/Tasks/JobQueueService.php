<?php

namespace App\Services\Tasks;

use App\Models\JobQueue;
use App\Models\Task;
use App\Models\TaskRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 任务队列服务。
 *
 * 与老 backend includes/job_queue_service.php 完全对齐行为：
 *   - 与老 worker.php 共用同一张 job_queue 表 → 双跑期间老 worker / 新 Laravel
 *     worker 都可以消费。claim 用原子 UPDATE WHERE status='pending' 防竞态。
 *   - completeJob/failJob/cancelJob 写 task_runs + 同步 tasks.last_*_at 字段。
 */
class JobQueueService
{
    public function initializeTaskSchedule(int $taskId, int $delaySeconds = 60): void
    {
        DB::statement("
            UPDATE tasks
            SET next_run_at = COALESCE(next_run_at, NOW() + (? || ' seconds')::interval),
                schedule_enabled = COALESCE(schedule_enabled, 1),
                max_retry_count = COALESCE(max_retry_count, 3),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$delaySeconds, $taskId]);
    }

    public function hasPendingOrRunningJob(int $taskId): bool
    {
        return JobQueue::query()
            ->where('task_id', $taskId)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }

    public function enqueueTaskJob(
        int $taskId,
        string $jobType = 'generate_article',
        array $payload = [],
        ?string $availableAt = null
    ): ?int {
        if ($this->hasPendingOrRunningJob($taskId)) {
            return null;
        }
        $task = Task::query()->whereKey($taskId)->first(['id', 'max_retry_count']);
        if (!$task) {
            return null;
        }

        $job = JobQueue::create([
            'task_id'       => $taskId,
            'job_type'      => $jobType,
            'status'        => 'pending',
            'payload'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'attempt_count' => 0,
            'max_attempts'  => max(1, (int) ($task->max_retry_count ?? 3)),
            'available_at'  => $availableAt ?: Carbon::now(),
        ]);

        return (int) $job->id;
    }

    /**
     * 原子 claim 下一个 pending job。返回 job 行（含 publish_interval / task_status），
     * 或 null（没有可消费的 job）。
     *
     * @return array<string,mixed>|null
     */
    public function claimNextJob(string $workerId): ?array
    {
        return DB::transaction(function () use ($workerId) {
            $job = DB::selectOne('
                SELECT jq.*, t.publish_interval, t.status AS task_status
                FROM job_queue jq
                INNER JOIN tasks t ON t.id = jq.task_id
                WHERE jq.status = ?
                  AND jq.available_at <= CURRENT_TIMESTAMP
                  AND t.status = ?
                  AND COALESCE(t.schedule_enabled, 1) = 1
                ORDER BY jq.available_at ASC, jq.id ASC
                LIMIT 1
            ', ['pending', 'active']);

            if (!$job) {
                return null;
            }

            $rows = DB::affectingStatement('
                UPDATE job_queue
                SET status = ?, claimed_at = CURRENT_TIMESTAMP,
                    worker_id = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND status = ?
            ', ['running', $workerId, $job->id, 'pending']);

            if ($rows !== 1) {
                return null;   // 被别的 worker 抢走了；不抛错让 worker 继续 poll
            }

            $arr = (array) $job;
            $arr['status'] = 'running';
            $arr['worker_id'] = $workerId;
            return $arr;
        });
    }

    public function completeJob(int $jobId, int $taskId, ?int $articleId, int $durationMs, array $meta = []): void
    {
        JobQueue::whereKey($jobId)->update([
            'status'        => 'completed',
            'finished_at'   => Carbon::now(),
            'error_message' => '',
            'updated_at'    => Carbon::now(),
        ]);

        TaskRun::create([
            'task_id'     => $taskId,
            'job_id'      => $jobId,
            'status'      => 'completed',
            'article_id'  => $articleId,
            'duration_ms' => $durationMs,
            'meta'        => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'started_at'  => Carbon::now(),
            'finished_at' => Carbon::now(),
        ]);

        Task::whereKey($taskId)->update([
            'last_run_at'        => Carbon::now(),
            'last_success_at'    => Carbon::now(),
            'last_error_message' => '',
            'updated_at'         => Carbon::now(),
        ]);
    }

    public function failJob(int $jobId, int $taskId, string $errorMessage, int $durationMs, int $retryDelaySeconds = 60): void
    {
        $job = JobQueue::whereKey($jobId)->first(['attempt_count', 'max_attempts']);
        if (!$job) {
            return;
        }

        $attemptCount = (int) $job->attempt_count + 1;
        $maxAttempts  = max(1, (int) $job->max_attempts);
        $shouldRetry  = $attemptCount < $maxAttempts;

        DB::statement('
            UPDATE job_queue
            SET status = ?,
                attempt_count = ?,
                available_at = CASE WHEN ? THEN NOW() + (? || \' seconds\')::interval ELSE available_at END,
                finished_at  = CASE WHEN ? THEN NULL ELSE CURRENT_TIMESTAMP END,
                error_message = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ', [
            $shouldRetry ? 'pending' : 'failed',
            $attemptCount,
            $shouldRetry ? 1 : 0,
            $retryDelaySeconds,
            $shouldRetry ? 1 : 0,
            $errorMessage,
            $jobId,
        ]);

        TaskRun::create([
            'task_id'       => $taskId,
            'job_id'        => $jobId,
            'status'        => $shouldRetry ? 'retrying' : 'failed',
            'error_message' => $errorMessage,
            'duration_ms'   => $durationMs,
            'started_at'    => Carbon::now(),
            'finished_at'   => Carbon::now(),
        ]);

        Task::whereKey($taskId)->update([
            'last_run_at'        => Carbon::now(),
            'last_error_at'      => Carbon::now(),
            'last_error_message' => $errorMessage,
            'updated_at'         => Carbon::now(),
        ]);
    }

    public function cancelJob(int $jobId, int $taskId, string $reason = '管理员手动停止'): void
    {
        JobQueue::whereKey($jobId)->update([
            'status'        => 'cancelled',
            'finished_at'   => Carbon::now(),
            'updated_at'    => Carbon::now(),
            'error_message' => $reason,
        ]);

        TaskRun::create([
            'task_id'       => $taskId,
            'job_id'        => $jobId,
            'status'        => 'cancelled',
            'error_message' => $reason,
            'duration_ms'   => 0,
            'started_at'    => Carbon::now(),
            'finished_at'   => Carbon::now(),
        ]);

        Task::whereKey($taskId)->update([
            'last_run_at'        => Carbon::now(),
            'last_error_at'      => Carbon::now(),
            'last_error_message' => $reason,
            'updated_at'         => Carbon::now(),
        ]);
    }

    public function recoverStaleJobs(int $timeoutSeconds = 600): int
    {
        $timeoutSeconds = max(60, $timeoutSeconds);
        return DB::affectingStatement('
            UPDATE job_queue
            SET status = ?, claimed_at = NULL, worker_id = \'\',
                updated_at = CURRENT_TIMESTAMP, available_at = CURRENT_TIMESTAMP
            WHERE status = ?
              AND claimed_at IS NOT NULL
              AND claimed_at < NOW() - (? || \' seconds\')::interval
        ', ['pending', 'running', $timeoutSeconds]);
    }
}
