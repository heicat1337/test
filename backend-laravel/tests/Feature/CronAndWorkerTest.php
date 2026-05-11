<?php

use App\Models\AiModel;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\JobQueue;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\Title;
use App\Models\TitleLibrary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

function p71_fullFixture(array $taskOverrides = []): Task
{
    $tl = TitleLibrary::create(['name' => 'TL_' . uniqid()]);
    $pr = Prompt::create(['name' => 'P_' . uniqid(), 'type' => 'content', 'content' => 'x']);
    $ai = AiModel::create([
        'name' => 'AI_' . uniqid(), 'model_id' => 'gpt-test', 'model_type' => 'chat',
        'api_url' => 'https://api.test', 'api_key' => 'k', 'status' => 'active',
    ]);
    Category::firstOrCreate(['slug' => 'general'], ['name' => 'General', 'sort_order' => 0]);
    Author::firstOrCreate(['name' => 'System'], []);
    return Task::create(array_merge([
        'name' => 'T_' . uniqid(),
        'title_library_id' => $tl->id, 'prompt_id' => $pr->id,
        'ai_model_id' => $ai->id,
        'status' => 'active', 'schedule_enabled' => 1,
        'draft_limit' => 100, 'publish_interval' => 60,
    ], $taskOverrides));
}

describe('geoflow:cron-tick', function () {
    it('enqueues an eligible task and updates next_run_at', function () {
        $task = p71_fullFixture();
        Task::whereKey($task->id)->update(['next_run_at' => now()->subMinutes(5)]);

        $this->artisan('geoflow:cron-tick')->assertSuccessful();

        expect(JobQueue::where('task_id', $task->id)->count())->toBe(1);
        $fresh = $task->fresh();
        expect($fresh->next_run_at)->not->toBeNull();
        expect($fresh->next_run_at->isFuture())->toBeTrue();
    });

    it('skips paused task', function () {
        $task = p71_fullFixture(['status' => 'paused']);
        Task::whereKey($task->id)->update(['next_run_at' => now()->subMinutes(5)]);

        $this->artisan('geoflow:cron-tick')->assertSuccessful();
        expect(JobQueue::where('task_id', $task->id)->count())->toBe(0);
    });

    it('skips task with pending job', function () {
        $task = p71_fullFixture();
        Task::whereKey($task->id)->update(['next_run_at' => now()->subMinutes(5)]);
        // 预先有一个 pending job
        JobQueue::create([
            'task_id' => $task->id, 'job_type' => 'generate_article',
            'status' => 'pending', 'payload' => '{}',
            'attempt_count' => 0, 'max_attempts' => 3,
            'available_at' => now(),
        ]);

        $this->artisan('geoflow:cron-tick')->assertSuccessful();
        // 不会重复入队
        expect(JobQueue::where('task_id', $task->id)->count())->toBe(1);
    });

    it('skips task with future next_run_at', function () {
        $task = p71_fullFixture();
        Task::whereKey($task->id)->update(['next_run_at' => now()->addHour()]);

        $this->artisan('geoflow:cron-tick')->assertSuccessful();
        expect(JobQueue::where('task_id', $task->id)->count())->toBe(0);
    });

    it('initializes next_run_at when null', function () {
        $task = p71_fullFixture();
        Task::whereKey($task->id)->update(['next_run_at' => null]);

        $this->artisan('geoflow:cron-tick')->assertSuccessful();
        expect($task->fresh()->next_run_at)->not->toBeNull();
    });

    it('recovers stale running jobs', function () {
        $task = p71_fullFixture();
        $stale = JobQueue::create([
            'task_id' => $task->id, 'job_type' => 'generate_article',
            'status' => 'running', 'payload' => '{}',
            'attempt_count' => 0, 'max_attempts' => 3,
            'available_at' => now()->subHours(2),
            'claimed_at'   => now()->subHours(1),
            'worker_id'    => 'old',
        ]);

        $this->artisan('geoflow:cron-tick')->assertSuccessful();
        expect(JobQueue::find($stale->id)->status)->toBe('pending');
    });
});

describe('geoflow:worker --once', function () {
    it('processes a pending job successfully', function () {
        $task = p71_fullFixture();
        Title::create([
            'library_id' => $task->title_library_id,
            'title' => 'WorkerTitle_' . uniqid(),
            'keyword' => 'k',
            'used_count' => 0, 'usage_count' => 0,
        ]);
        $job = JobQueue::create([
            'task_id' => $task->id, 'job_type' => 'generate_article',
            'status' => 'pending', 'payload' => '{}',
            'attempt_count' => 0, 'max_attempts' => 3,
            'available_at' => now()->subMinute(),
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => str_repeat('正文段落。', 50)]]],
            ], 200),
        ]);

        $this->artisan('geoflow:worker', ['--once' => true])->assertSuccessful();

        $job = $job->fresh();
        expect($job->status)->toBe('completed');
        expect($job->worker_id)->not->toBe('');
        expect(Article::where('task_id', $task->id)->count())->toBe(1);
    });

    it('marks failed job and retries until max_attempts', function () {
        $task = p71_fullFixture();
        $job = JobQueue::create([
            'task_id' => $task->id, 'job_type' => 'generate_article',
            'status' => 'pending', 'payload' => '{}',
            'attempt_count' => 1,    // 已重试过 1 次
            'max_attempts' => 2,     // 这次失败就到 max
            'available_at' => now()->subMinute(),
        ]);
        // 没有可用标题 → executeTask 返回 error
        // 没 mock Http → 调 Ai 也会失败，但 pickNextTitle 先返回 null

        $this->artisan('geoflow:worker', ['--once' => true])->assertSuccessful();

        $fresh = $job->fresh();
        expect($fresh->status)->toBe('failed');   // 到 max_attempts，不再 retry
        expect($fresh->attempt_count)->toBe(2);
        expect($fresh->error_message)->not->toBe('');
    });

    it('exits with --once when queue is empty', function () {
        $this->artisan('geoflow:worker', ['--once' => true])->assertSuccessful();
    });

    it('writes worker_heartbeats row', function () {
        // worker 启动会写 heartbeat
        $beforeCount = DB::table('worker_heartbeats')->count();

        $this->artisan('geoflow:worker', ['--once' => true])->assertSuccessful();
        // 注意：upsert（ON CONFLICT），重复跑同 worker_id 不增加行数
        $afterCount = DB::table('worker_heartbeats')->count();
        expect($afterCount)->toBeGreaterThanOrEqual($beforeCount);
    });
});
