<?php

use App\Exceptions\Api\ApiException;
use App\Models\AiModel;
use App\Models\Author;
use App\Models\Category;
use App\Models\JobQueue;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\TitleLibrary;
use App\Services\Catalog\CatalogService;
use App\Services\Tasks\JobQueueService;
use App\Services\Tasks\TaskLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function p5_fixture(): array
{
    $cat = Category::create(['name' => 'C_' . uniqid(), 'slug' => 'c-' . uniqid()]);
    $au  = Author::create(['name' => 'A_' . uniqid()]);
    $tl  = TitleLibrary::create(['name' => 'TL_' . uniqid()]);
    $pr  = Prompt::create(['name' => 'P_' . uniqid(), 'type' => 'content', 'content' => 'x']);
    $ai  = AiModel::create([
        'name' => 'AI_' . uniqid(), 'model_id' => 'gpt-test', 'model_type' => 'chat',
        'api_url' => 'https://x.test', 'api_key' => 'k', 'status' => 'active',
    ]);
    return compact('cat', 'au', 'tl', 'pr', 'ai');
}

describe('JobQueueService', function () {
    it('enqueues a job for an active task', function () {
        $f = p5_fixture();
        $t = Task::create([
            'name' => 'T_' . uniqid(),
            'title_library_id' => $f['tl']->id, 'prompt_id' => $f['pr']->id,
            'ai_model_id' => $f['ai']->id, 'status' => 'active',
            'max_retry_count' => 3,
        ]);

        $svc = new JobQueueService();
        $jobId = $svc->enqueueTaskJob($t->id, 'generate_article', ['source' => 'test']);

        expect($jobId)->toBeInt()->toBeGreaterThan(0);
        $job = JobQueue::find($jobId);
        expect($job->status)->toBe('pending');
        expect($job->task_id)->toBe($t->id);
        expect($job->max_attempts)->toBe(3);
    });

    it('returns null when task already has pending/running job', function () {
        $f = p5_fixture();
        $t = Task::create([
            'name' => 'T_' . uniqid(), 'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'active',
        ]);
        $svc = new JobQueueService();
        $svc->enqueueTaskJob($t->id);

        expect($svc->enqueueTaskJob($t->id))->toBeNull();
    });

    it('claimNextJob picks up pending job and marks running', function () {
        $f = p5_fixture();
        $t = Task::create([
            'name' => 'T_' . uniqid(), 'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'active',
        ]);
        $svc = new JobQueueService();
        $jobId = $svc->enqueueTaskJob($t->id);

        $claimed = $svc->claimNextJob('worker-pest');
        expect($claimed)->not->toBeNull();
        expect($claimed['id'])->toBe($jobId);
        expect($claimed['status'])->toBe('running');
        expect(JobQueue::find($jobId)->worker_id)->toBe('worker-pest');
    });

    it('completeJob writes task_run and updates task last_success_at', function () {
        $f = p5_fixture();
        $t = Task::create([
            'name' => 'T_' . uniqid(), 'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'active',
        ]);
        $svc = new JobQueueService();
        $jobId = $svc->enqueueTaskJob($t->id);
        $svc->claimNextJob('w1');

        $svc->completeJob($jobId, $t->id, articleId: null, durationMs: 250);

        $j = JobQueue::find($jobId);
        expect($j->status)->toBe('completed');
        expect($j->finished_at)->not->toBeNull();

        $tf = $t->fresh();
        expect($tf->last_success_at)->not->toBeNull();
        expect($tf->last_error_message)->toBe('');
    });

    it('failJob retries until max_attempts then marks failed', function () {
        $f = p5_fixture();
        $t = Task::create([
            'name' => 'T_' . uniqid(), 'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'active', 'max_retry_count' => 2,
        ]);
        $svc = new JobQueueService();
        $jobId = $svc->enqueueTaskJob($t->id);

        // Attempt 1 → retry
        $svc->failJob($jobId, $t->id, 'oops', durationMs: 100);
        expect(JobQueue::find($jobId)->status)->toBe('pending');
        expect(JobQueue::find($jobId)->attempt_count)->toBe(1);

        // Attempt 2 → failed
        $svc->failJob($jobId, $t->id, 'still bad', durationMs: 100);
        expect(JobQueue::find($jobId)->status)->toBe('failed');
        expect($t->fresh()->last_error_message)->toBe('still bad');
    });

    it('recoverStaleJobs returns running jobs older than timeout to pending', function () {
        $f = p5_fixture();
        $t = Task::create([
            'name' => 'T_' . uniqid(), 'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'active',
        ]);
        // 直接插入一个 stale running job（claimed 1 小时前）
        $jobId = JobQueue::create([
            'task_id' => $t->id, 'job_type' => 'generate_article',
            'status' => 'running', 'payload' => '{}',
            'attempt_count' => 0, 'max_attempts' => 3,
            'available_at' => now()->subHours(2),
            'claimed_at'   => now()->subHours(1),
            'worker_id'    => 'old-worker',
        ])->id;

        $svc = new JobQueueService();
        $count = $svc->recoverStaleJobs(600);
        expect($count)->toBeGreaterThanOrEqual(1);
        expect(JobQueue::find($jobId)->status)->toBe('pending');
    });
});

describe('TaskLifecycleService', function () {
    it('creates task with required references', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();

        $t = $svc->createTask([
            'name' => 'New ' . uniqid(),
            'title_library_id' => $f['tl']->id,
            'prompt_id'        => $f['pr']->id,
            'ai_model_id'      => $f['ai']->id,
            'status'           => 'active',
        ]);

        expect($t['name'])->toStartWith('New');
        expect($t['status'])->toBe('active');
        expect($t['schedule_enabled'])->toBe(1);
        expect(Task::find($t['id']))->not->toBeNull();
    });

    it('rejects missing required references', function () {
        $svc = new TaskLifecycleService();
        expect(fn () => $svc->createTask(['name' => 'X']))->toThrow(ApiException::class);
    });

    it('rejects fixed category mode without fixed_category_id', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();
        expect(fn () => $svc->createTask([
            'name' => 'F', 'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'category_mode' => 'fixed',
        ]))->toThrow(ApiException::class);
    });

    it('getTask returns queue and article summary', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();
        $t = $svc->createTask([
            'name' => 'G_' . uniqid(),
            'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
        ]);
        $r = $svc->getTask($t['id']);
        expect($r['queue_summary'])->toHaveKeys(['pending_jobs', 'running_jobs', 'last_job_id', 'last_job_status']);
        expect($r['article_summary'])->toHaveKeys(['draft_count', 'published_count', 'total_count']);
    });

    it('startTask + enqueueNow inserts a pending job', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();
        $t = $svc->createTask([
            'name' => 'S_' . uniqid(),
            'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'paused',
        ]);
        // start with enqueue
        $r = $svc->startTask($t['id'], enqueueNow: true);

        expect($r['status'])->toBe('active');
        expect($r['schedule_enabled'])->toBe(1);
        expect(isset($r['started_job_id']))->toBeTrue();
        expect(JobQueue::where('task_id', $t['id'])->where('status', 'pending')->count())->toBe(1);
    });

    it('stopTask cancels pending jobs', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();
        $t = $svc->createTask([
            'name' => 'Z_' . uniqid(),
            'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'active',
        ]);
        $jobId = (new JobQueueService())->enqueueTaskJob($t['id']);
        expect($jobId)->toBeInt();

        $r = $svc->stopTask($t['id']);
        expect($r['status'])->toBe('paused');
        expect(JobQueue::find($jobId)->status)->toBe('cancelled');
    });

    it('enqueueTask 409 when task not active', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();
        $t = $svc->createTask([
            'name' => 'P_' . uniqid(),
            'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'paused',
        ]);
        try {
            $svc->enqueueTask($t['id']);
            expect(false)->toBeTrue('Should have thrown');
        } catch (ApiException $e) {
            expect($e->getHttpStatus())->toBe(409);
            expect($e->getErrorCode())->toBe('task_not_active');
        }
    });

    it('updateTask transitions status from paused to active', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();
        $t = $svc->createTask([
            'name' => 'U_' . uniqid(),
            'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'paused',
        ]);
        $r = $svc->updateTask($t['id'], ['status' => 'active']);
        expect($r['status'])->toBe('active');
        expect($r['schedule_enabled'])->toBe(1);
    });

    it('listTaskJobs / getJob return shapes', function () {
        $f = p5_fixture();
        $svc = new TaskLifecycleService();
        $t = $svc->createTask([
            'name' => 'L_' . uniqid(),
            'title_library_id' => $f['tl']->id,
            'prompt_id' => $f['pr']->id, 'ai_model_id' => $f['ai']->id,
            'status' => 'active',
        ]);
        $jobId = (new JobQueueService())->enqueueTaskJob($t['id']);

        $list = $svc->listTaskJobs($t['id'], status: 'pending');
        expect(count($list['items']))->toBe(1);

        $job = $svc->getJob($jobId);
        expect($job['id'])->toBe($jobId);
        expect($job['status'])->toBe('pending');
        expect($job['payload'])->toBeArray();
    });
});

describe('CatalogService', function () {
    it('lists active resources', function () {
        $f = p5_fixture();
        $svc = new CatalogService();
        $cat = $svc->getCatalog();

        expect($cat)->toHaveKeys(['models', 'prompts', 'title_libraries', 'knowledge_bases', 'authors', 'categories']);
        expect(collect($cat['models'])->pluck('id')->all())->toContain($f['ai']->id);
        expect(collect($cat['prompts'])->pluck('id')->all())->toContain($f['pr']->id);
    });
});
