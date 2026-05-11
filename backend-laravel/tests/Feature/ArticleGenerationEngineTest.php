<?php

use App\Models\AiModel;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Services\Ai\ArticleGenerationEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

function p62_fixture(array $taskOverrides = []): Task
{
    $tl = TitleLibrary::create(['name' => 'TL_' . uniqid()]);
    $pr = Prompt::create([
        'name' => 'P_' . uniqid(), 'type' => 'content',
        'content' => "Write about {{title}} using {{keyword}}",
    ]);
    $ai = AiModel::create([
        'name' => 'AI_' . uniqid(), 'model_id' => 'gpt-test', 'model_type' => 'chat',
        'api_url' => 'https://api.test', 'api_key' => 'k', 'status' => 'active',
        'daily_limit' => 0, 'used_today' => 0, 'total_used' => 0,
    ]);
    // 必须有 Category + Author 作为 fallback
    Category::firstOrCreate(['slug' => 'general'], ['name' => 'General', 'sort_order' => 0]);
    Author::firstOrCreate(['name' => 'System'], []);

    return Task::create(array_merge([
        'name' => 'T_' . uniqid(),
        'title_library_id' => $tl->id,
        'prompt_id'        => $pr->id,
        'ai_model_id'      => $ai->id,
        'status'           => 'active',
        'draft_limit'      => 0,
        'is_loop'          => 0,
        'need_review'      => 1,
    ], $taskOverrides));
}

function p62_addTitles(int $libraryId, int $count = 3): array
{
    $created = [];
    for ($i = 0; $i < $count; $i++) {
        $t = Title::create([
            'library_id' => $libraryId,
            'title'      => 'Title ' . uniqid(),
            'keyword'    => 'kw' . $i,
            'used_count' => 0,
            'usage_count' => 0,
            'created_at' => now(),
        ]);
        $created[] = $t;
    }
    return $created;
}

function p62_mockAi(string $content): void
{
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $content]]],
        ], 200),
    ]);
}

beforeEach(function () {
    $this->engine = new ArticleGenerationEngine();
});

describe('happy path', function () {
    it('generates and saves an article (draft + pending review)', function () {
        $task = p62_fixture();
        $titles = p62_addTitles($task->title_library_id, 1);
        $longContent = str_repeat('正文段落内容。', 50);  // > MIN_CONTENT_LENGTH (200 字符)
        p62_mockAi($longContent);

        $r = $this->engine->executeTask($task->id);

        expect($r['success'])->toBeTrue();
        expect($r['title'])->toBe($titles[0]->title);

        $article = Article::find($r['article_id']);
        expect($article)->not->toBeNull();
        expect($article->status)->toBe('draft');
        expect($article->review_status)->toBe('pending');
        expect($article->is_ai_generated)->toBe(1);
        expect($article->task_id)->toBe($task->id);

        // 标题被标记已用
        expect($titles[0]->fresh()->used_count)->toBe(1);
        // 任务 created_count + 1
        expect($task->fresh()->created_count)->toBe(1);
    });

    it('publishes immediately when need_review=0', function () {
        $task = p62_fixture(['need_review' => 0]);
        p62_addTitles($task->title_library_id, 1);
        p62_mockAi(str_repeat('正文段落内容。', 50));

        $r = $this->engine->executeTask($task->id);
        $a = Article::find($r['article_id']);

        expect($a->status)->toBe('published');
        expect($a->review_status)->toBe('auto_approved');
        expect($a->published_at)->not->toBeNull();
        expect($task->fresh()->published_count)->toBe(1);
    });
});

describe('failure paths', function () {
    it('fails when task not active', function () {
        $task = p62_fixture(['status' => 'paused']);
        $r = $this->engine->executeTask($task->id);
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toBe('任务未激活');
    });

    it('fails when task does not exist', function () {
        $r = $this->engine->executeTask(99999999);
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toBe('任务不存在');
    });

    it('fails when no fresh titles available', function () {
        $task = p62_fixture();
        // Title 的 created_at 不在 fillable 里，Eloquent 会用 PG default = now()。
        // 必须用 raw DB 注入旧 created_at。
        DB::table('titles')->insert([
            'library_id' => $task->title_library_id,
            'title' => 'Old', 'keyword' => 'k',
            'used_count' => 0, 'usage_count' => 0,
            'created_at' => now()->subDays(2),
        ]);

        p62_mockAi(str_repeat('x', 500));
        $r = $this->engine->executeTask($task->id);
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toContain('无新抓取标题');
    });

    it('fails when draft limit reached', function () {
        $task = p62_fixture(['draft_limit' => 1]);
        p62_addTitles($task->title_library_id, 1);
        // 灌一篇 draft
        Article::create([
            'title' => 'DraftAt_' . uniqid(), 'slug' => 'da-' . uniqid(),
            'content' => 'x', 'category_id' => Category::first()->id,
            'author_id' => Author::first()->id, 'task_id' => $task->id,
            'status' => 'draft',
        ]);

        $r = $this->engine->executeTask($task->id);
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toContain('草稿数量已达上限');
    });

    it('rejects too-short AI output', function () {
        $task = p62_fixture();
        p62_addTitles($task->title_library_id, 1);
        p62_mockAi('短');   // < MIN_CONTENT_LENGTH

        $r = $this->engine->executeTask($task->id);
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toContain('生成内容过短');
    });
});

describe('title cycling', function () {
    it('skips title whose name already exists as article', function () {
        $task = p62_fixture();
        $titles = p62_addTitles($task->title_library_id, 2);
        // 让第一个标题已经存在于 articles 表 → 应被跳过
        Article::create([
            'title' => $titles[0]->title, 'slug' => 'exist-' . uniqid(),
            'content' => 'x',
            'category_id' => Category::first()->id,
            'author_id' => Author::first()->id,
        ]);

        p62_mockAi(str_repeat('正文。', 80));

        $r = $this->engine->executeTask($task->id);
        expect($r['success'])->toBeTrue();
        expect($r['title'])->toBe($titles[1]->title);          // 跳到第二个
        expect($titles[0]->fresh()->used_count)->toBe(1);      // 跳过的也标记已用
    });

    it('loop mode bumps loop_count when all titles are stale (outside 24h)', function () {
        $task = p62_fixture(['is_loop' => 1]);
        // 所有标题都不在 24h 窗口 → pickNextTitle 两次查询都 null
        // → reset + loop_count++ → 重 pick 仍 null → executeTask 失败
        DB::table('titles')->insert([
            'library_id' => $task->title_library_id,
            'title' => 'Stale', 'keyword' => 'k',
            'used_count' => 5, 'usage_count' => 5,
            'created_at' => now()->subDays(2),
        ]);

        p62_mockAi(str_repeat('正文。', 80));
        $r = $this->engine->executeTask($task->id);
        expect($r['success'])->toBeFalse();   // 没有可用 title

        expect($task->fresh()->loop_count)->toBe(1);   // 触发了 reset
    });

    it('loop mode picks least-used title within fresh window', function () {
        $task = p62_fixture(['is_loop' => 1]);
        $titles = p62_addTitles($task->title_library_id, 1);
        // 把唯一标题 used_count > 0（即没"未使用"的）；is_loop 应该 fallback 取
        Title::where('id', $titles[0]->id)->update(['used_count' => 5]);

        p62_mockAi(str_repeat('正文。', 80));
        $r = $this->engine->executeTask($task->id);
        expect($r['success'])->toBeTrue();
        expect($r['title'])->toBe($titles[0]->title);
        expect($titles[0]->fresh()->used_count)->toBe(6);   // 在原值上 +1
        // 没触发 reset
        expect($task->fresh()->loop_count)->toBe(0);
    });
});
