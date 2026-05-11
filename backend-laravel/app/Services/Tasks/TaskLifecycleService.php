<?php

namespace App\Services\Tasks;

use App\Exceptions\Api\ApiException;
use App\Models\AiModel;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\ImageLibrary;
use App\Models\JobQueue;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\TaskRun;
use App\Models\TitleLibrary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 任务生命周期 service。与老 includes/task_lifecycle_service.php (639 行) 对齐：
 *   - listTasks / createTask / getTask / updateTask
 *   - startTask / stopTask / enqueueTask
 *   - listTaskJobs / getJob
 *
 * 与 JobQueueService 协作（共享 job_queue 表，与老 worker.php 双跑兼容）。
 */
class TaskLifecycleService
{
    public function __construct(
        private readonly JobQueueService $queue = new JobQueueService(),
    ) {}

    public function listTasks(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $q = Task::query();
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $q->where('name', 'LIKE', '%' . $filters['search'] . '%');
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(function (Task $t) {
                $arr = $t->toArray();
                $arr['pending_jobs'] = JobQueue::where('task_id', $t->id)->where('status', 'pending')->count();
                $arr['running_jobs'] = JobQueue::where('task_id', $t->id)->where('status', 'running')->count();
                return $arr;
            })
            ->all();

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) max(1, ceil($total / $perPage)),
            ],
        ];
    }

    public function createTask(array $data): array
    {
        $normalized = $this->normalizeTaskInput($data, false);

        $task = DB::transaction(function () use ($normalized) {
            $task = Task::create($normalized);
            $this->queue->initializeTaskSchedule($task->id);

            if (($normalized['status'] ?? null) !== 'active') {
                $task->update([
                    'schedule_enabled' => 0,
                    'next_run_at'      => null,
                ]);
            }
            return $task;
        });

        return $this->getTask($task->id);
    }

    public function getTask(int $taskId): array
    {
        $task = $this->mustFindTask($taskId);

        $queueAgg = JobQueue::where('task_id', $taskId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_jobs,
                COALESCE(SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END), 0) AS running_jobs
            ")
            ->first();

        $lastJob = JobQueue::where('task_id', $taskId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first(['id', 'status']);

        $articleAgg = Article::query()
            ->where('task_id', $taskId)
            ->selectRaw("
                COUNT(*) FILTER (WHERE deleted_at IS NULL) AS total_count,
                COUNT(*) FILTER (WHERE deleted_at IS NULL AND status = 'draft') AS draft_count,
                COUNT(*) FILTER (WHERE deleted_at IS NULL AND status = 'published') AS published_count
            ")
            ->first();

        return [
            'id'                   => (int) $task->id,
            'name'                 => $task->name,
            'status'               => $task->status,
            'schedule_enabled'     => (int) ($task->schedule_enabled ?? 1),
            'title_library_id'     => $task->title_library_id,
            'prompt_id'            => $task->prompt_id,
            'ai_model_id'          => $task->ai_model_id,
            'knowledge_base_id'    => $task->knowledge_base_id,
            'author_id'            => $task->author_id,
            'image_library_id'     => $task->image_library_id,
            'image_count'          => (int) ($task->image_count ?? 0),
            'need_review'          => (int) ($task->need_review ?? 1),
            'publish_interval'     => (int) ($task->publish_interval ?? 3600),
            'auto_keywords'        => (int) ($task->auto_keywords ?? 1),
            'auto_description'     => (int) ($task->auto_description ?? 1),
            'draft_limit'          => (int) ($task->draft_limit ?? 10),
            'is_loop'              => (int) ($task->is_loop ?? 0),
            'model_selection_mode' => $task->model_selection_mode ?? 'fixed',
            'category_mode'        => $task->category_mode ?? 'smart',
            'fixed_category_id'    => $task->fixed_category_id,
            'created_count'        => (int) ($task->created_count ?? 0),
            'published_count'      => (int) ($task->published_count ?? 0),
            'queue_summary'        => [
                'pending_jobs'    => (int) ($queueAgg->pending_jobs ?? 0),
                'running_jobs'    => (int) ($queueAgg->running_jobs ?? 0),
                'last_job_id'     => $lastJob?->id,
                'last_job_status' => $lastJob?->status,
            ],
            'article_summary'      => [
                'draft_count'     => (int) ($articleAgg->draft_count ?? 0),
                'published_count' => (int) ($articleAgg->published_count ?? 0),
                'total_count'     => (int) ($articleAgg->total_count ?? 0),
            ],
            'last_run_at'  => optional($task->last_run_at)->toDateTimeString(),
            'next_run_at'  => optional($task->next_run_at)->toDateTimeString(),
            'created_at'   => optional($task->created_at)->toDateTimeString(),
            'updated_at'   => optional($task->updated_at)->toDateTimeString(),
        ];
    }

    public function updateTask(int $taskId, array $data): array
    {
        $this->mustFindTask($taskId);
        $normalized = $this->normalizeTaskInput($data, true);
        if (empty($normalized)) {
            throw new ApiException('validation_failed', '没有可更新的字段', 422);
        }

        $status = $normalized['status'] ?? null;
        unset($normalized['status']);

        DB::transaction(function () use ($taskId, $normalized, $status) {
            if (!empty($normalized)) {
                Task::whereKey($taskId)->update(array_merge($normalized, ['updated_at' => Carbon::now()]));
            }
            if ($status === 'active') {
                $this->activateTask($taskId, false);
            } elseif ($status === 'paused') {
                $this->pauseTask($taskId);
            }
        });

        return $this->getTask($taskId);
    }

    public function startTask(int $taskId, bool $enqueueNow = false): array
    {
        $this->mustFindTask($taskId);

        $jobId = null;
        DB::transaction(function () use ($taskId, $enqueueNow, &$jobId) {
            $this->activateTask($taskId, true);
            if ($enqueueNow) {
                $jobId = $this->queue->enqueueTaskJob($taskId, 'generate_article', ['source' => 'api_manual_start']);
            }
        });

        $task = $this->getTask($taskId);
        if ($jobId !== null) {
            $task['started_job_id'] = $jobId;
        }
        return $task;
    }

    public function stopTask(int $taskId): array
    {
        $this->mustFindTask($taskId);

        $cancelled = 0;
        $running = 0;
        DB::transaction(function () use ($taskId, &$cancelled, &$running) {
            $cancelled = $this->pauseTask($taskId);
            $running = JobQueue::where('task_id', $taskId)->where('status', 'running')->count();
        });

        $task = $this->getTask($taskId);
        $task['cancelled_jobs'] = $cancelled;
        $task['running_jobs']   = $running;
        return $task;
    }

    public function enqueueTask(int $taskId, string $jobType = 'generate_article', array $payload = []): array
    {
        $task = Task::query()->whereKey($taskId)->first(['id', 'status', 'schedule_enabled']);
        if (!$task) {
            throw ApiException::notFound('task_not_found', '任务不存在');
        }
        if (($task->status ?? 'paused') !== 'active' || (int) ($task->schedule_enabled ?? 1) !== 1) {
            throw new ApiException('task_not_active', '任务未启用，无法入队', 409);
        }

        $jobId = $this->queue->enqueueTaskJob($taskId, $jobType, $payload);
        if ($jobId === null) {
            throw new ApiException('job_already_exists', '任务已处于排队或执行中', 409);
        }

        return [
            'task_id' => $taskId,
            'job_id'  => $jobId,
            'status'  => 'pending',
        ];
    }

    public function listTaskJobs(int $taskId, ?string $status = null, int $limit = 20): array
    {
        $this->mustFindTask($taskId);
        $limit = max(1, min(100, $limit));

        $q = JobQueue::where('task_id', $taskId);
        if ($status !== null && $status !== '') {
            $q->where('status', $status);
        }
        $items = $q->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id', 'task_id', 'job_type', 'status', 'attempt_count', 'max_attempts',
                'worker_id', 'claimed_at', 'finished_at', 'error_message',
                'created_at', 'updated_at',
            ])
            ->toArray();

        return ['items' => $items];
    }

    public function getJob(int $jobId): array
    {
        $job = JobQueue::find($jobId);
        if (!$job) {
            throw ApiException::notFound('job_not_found', 'Job 不存在');
        }

        $run = TaskRun::where('job_id', $jobId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['article_id', 'duration_ms', 'meta', 'status', 'error_message']);

        return [
            'id'              => (int) $job->id,
            'task_id'         => (int) $job->task_id,
            'job_type'        => $job->job_type,
            'status'          => $job->status,
            'attempt_count'   => (int) ($job->attempt_count ?? 0),
            'max_attempts'    => (int) ($job->max_attempts ?? 0),
            'worker_id'       => $job->worker_id !== '' ? $job->worker_id : null,
            'claimed_at'      => optional($job->claimed_at)->toDateTimeString(),
            'finished_at'     => optional($job->finished_at)->toDateTimeString(),
            'error_message'   => (string) ($job->error_message ?? ''),
            'payload'         => $this->decodeJsonField($job->payload ?? ''),
            'task_run_summary' => $run ? [
                'article_id'    => $run->article_id !== null ? (int) $run->article_id : null,
                'duration_ms'   => (int) ($run->duration_ms ?? 0),
                'status'        => $run->status,
                'error_message' => (string) ($run->error_message ?? ''),
                'meta'          => $this->decodeJsonField((string) ($run->meta ?? '')),
            ] : null,
        ];
    }

    // ---- 内部 helpers ----

    private function mustFindTask(int $taskId): Task
    {
        $task = Task::find($taskId);
        if (!$task) {
            throw ApiException::notFound('task_not_found', '任务不存在');
        }
        return $task;
    }

    private function activateTask(int $taskId, bool $resetNextRun): void
    {
        DB::statement('
            UPDATE tasks
            SET status = ?, schedule_enabled = 1,
                next_run_at = CASE WHEN ? = 1 THEN CURRENT_TIMESTAMP ELSE COALESCE(next_run_at, CURRENT_TIMESTAMP) END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ', ['active', $resetNextRun ? 1 : 0, $taskId]);
        $this->queue->initializeTaskSchedule($taskId);
    }

    private function pauseTask(int $taskId, string $reason = '任务已暂停'): int
    {
        Task::whereKey($taskId)->update([
            'status'           => 'paused',
            'schedule_enabled' => 0,
            'next_run_at'      => null,
            'updated_at'       => Carbon::now(),
        ]);

        $cancelled = DB::affectingStatement('
            UPDATE job_queue
            SET status = ?, finished_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP, error_message = ?
            WHERE task_id = ? AND status = ?
        ', ['cancelled', $reason, $taskId, 'pending']);

        return (int) $cancelled;
    }

    private function decodeJsonField(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 任务字段规范化 + 校验。与老 normalizeTaskInput 行为对齐。
     */
    private function normalizeTaskInput(array $data, bool $isUpdate): array
    {
        $output = [];
        $errors = [];

        // name
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            $name === '' ? $errors['name'] = '任务名称不能为空' : $output['name'] = $name;
        } elseif (!$isUpdate) {
            $errors['name'] = '任务名称不能为空';
        }

        // 关联字段
        $references = [
            'title_library_id'   => ['model' => TitleLibrary::class, 'msg' => '选择的标题库不存在',   'required' => !$isUpdate, 'extra' => null],
            'image_library_id'   => ['model' => ImageLibrary::class, 'msg' => '选择的图片库不存在',   'required' => false,      'extra' => null],
            'prompt_id'          => ['model' => Prompt::class,       'msg' => '选择的内容提示词不存在', 'required' => !$isUpdate, 'extra' => fn ($q) => $q->where('type', 'content')],
            'ai_model_id'        => ['model' => AiModel::class,      'msg' => '选择的AI模型不存在或未激活', 'required' => !$isUpdate,
                                     'extra' => fn ($q) => $q->where('status', 'active')->whereRaw("COALESCE(NULLIF(model_type, ''), 'chat') = 'chat'")],
            'author_id'          => ['model' => Author::class,       'msg' => '选择的作者不存在',       'required' => false, 'extra' => null],
            'knowledge_base_id'  => ['model' => KnowledgeBase::class,'msg' => '选择的知识库不存在',     'required' => false, 'extra' => null],
            'fixed_category_id'  => ['model' => Category::class,     'msg' => '固定分类不存在',         'required' => false, 'extra' => null],
        ];
        foreach ($references as $field => $cfg) {
            if (!array_key_exists($field, $data)) {
                if (!$isUpdate && $cfg['required']) {
                    $errors[$field] = '缺少必填字段';
                }
                continue;
            }
            $value = $data[$field];
            if ($value === null || $value === '' || (int) $value <= 0) {
                $output[$field] = null;
                if (!$isUpdate && $cfg['required']) {
                    $errors[$field] = '缺少必填字段';
                }
                continue;
            }
            $id = (int) $value;
            $q = $cfg['model']::query()->whereKey($id);
            if ($cfg['extra']) {
                $cfg['extra']($q);
            }
            if (!$q->exists()) {
                $errors[$field] = $cfg['msg'];
            } else {
                $output[$field] = $id;
            }
        }

        // 0/1 flags
        $flagFields = ['need_review', 'auto_keywords', 'auto_description', 'is_loop'];
        foreach ($flagFields as $f) {
            if (array_key_exists($f, $data)) {
                $output[$f] = $this->toFlag($data[$f]);
            } elseif (!$isUpdate) {
                $output[$f] = in_array($f, ['need_review', 'auto_keywords', 'auto_description'], true) ? 1 : 0;
            }
        }

        // 数值字段
        if (array_key_exists('image_count', $data)) {
            $output['image_count'] = max(0, (int) $data['image_count']);
        } elseif (!$isUpdate) {
            $output['image_count'] = 0;
        }
        if (array_key_exists('publish_interval', $data)) {
            $output['publish_interval'] = max(60, (int) $data['publish_interval']);
        } elseif (!$isUpdate) {
            $output['publish_interval'] = 3600;
        }
        if (array_key_exists('draft_limit', $data)) {
            $output['draft_limit'] = max(1, (int) $data['draft_limit']);
        } elseif (!$isUpdate) {
            $output['draft_limit'] = 10;
        }

        // 枚举字段
        $enumFields = [
            'category_mode'        => ['allowed' => ['smart', 'fixed'],          'default' => 'smart', 'msg' => '分类模式无效'],
            'model_selection_mode' => ['allowed' => ['fixed', 'smart_failover'], 'default' => 'fixed', 'msg' => '模型选择模式无效'],
            'status'               => ['allowed' => ['active', 'paused'],        'default' => 'active','msg' => '任务状态无效'],
        ];
        foreach ($enumFields as $f => $cfg) {
            if (array_key_exists($f, $data)) {
                $v = trim((string) $data[$f]);
                if (!in_array($v, $cfg['allowed'], true)) {
                    $errors[$f] = $cfg['msg'];
                } else {
                    $output[$f] = $v;
                }
            } elseif (!$isUpdate) {
                $output[$f] = $cfg['default'];
            }
        }

        // 固定分类模式必须选分类
        $effectiveCategoryMode = $output['category_mode']
            ?? (($data['category_mode'] ?? 'smart') ?: 'smart');
        if ($effectiveCategoryMode === 'fixed') {
            $fixed = $output['fixed_category_id'] ?? null;
            if ($fixed === null || $fixed <= 0) {
                $errors['fixed_category_id'] = '固定分类模式下必须选择一个分类';
            }
        }

        if ($errors) {
            throw ApiException::validationFailed($errors);
        }
        return $output;
    }

    private function toFlag(mixed $v): int
    {
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        if (is_numeric($v)) {
            return (int) $v > 0 ? 1 : 0;
        }
        $v = strtolower(trim((string) $v));
        return in_array($v, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }
}
