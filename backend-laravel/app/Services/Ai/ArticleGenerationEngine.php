<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\Article;
use App\Models\Task;
use App\Models\Title;
use App\Services\Articles\ArticleWorkflow;
use App\Services\Knowledge\KnowledgeRetrievalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 文章生成流水线。与老 backend includes/ai_engine.php::executeTask 对齐主流程：
 *
 *   1. 加载 task（含 prompt / model / knowledge_base / image_library）
 *   2. 校验 task 状态 + 草稿数限制
 *   3. 取下个标题（24h 窗口内未用 / 循环模式 fallback）
 *   4. 调 AiService::generateContent 生成正文
 *   5. saveArticle（去重 + workflow + ai_generated 标记 + 计数）
 *   6. 标记标题已用 + 更新 task / model 计数
 *
 * 当前 Phase 6.2 实现了核心 5 步。下面 3 个 hook 暂留 stub（未来按需补）：
 *   - applyImageLibrary($content, $task)        图片库注入
 *   - applyKnowledgeContext($prompt, $task, $title)  知识库 RAG 注入
 *   - applyFailoverModelSelection($task)        模型失效切换链
 */
class ArticleGenerationEngine
{
    private const FRESH_TITLE_WINDOW_HOURS = 24;
    private const MAX_TITLE_SKIP_ATTEMPTS = 50;
    private const MIN_CONTENT_LENGTH = 200;

    public function __construct(
        private readonly AiService $ai = new AiService(),
        private readonly KnowledgeRetrievalService $knowledge = new KnowledgeRetrievalService(),
    ) {}

    /**
     * 跑一次文章生成。
     *
     * @return array{success:bool, article_id?:int, title?:string, message?:string, error?:string}
     */
    public function executeTask(int $taskId): array
    {
        try {
            $task = Task::with(['prompt', 'aiModel', 'knowledgeBase'])
                ->whereKey($taskId)
                ->first();
            if (!$task) {
                throw new RuntimeException('任务不存在');
            }
            if ($task->status !== 'active') {
                throw new RuntimeException('任务未激活');
            }
            if ($this->isDraftLimitReached($task)) {
                throw new RuntimeException('草稿数量已达上限，暂停生成');
            }
            if (!$task->prompt || !$task->aiModel) {
                throw new RuntimeException('任务未配置 prompt 或 AI 模型');
            }

            // 取下一个可用标题（含循环重置 + 已存在跳过）
            $title = $this->pickNextTitle($task);
            if (!$title) {
                throw new RuntimeException('近 24 小时内无新抓取标题，等待下次触发');
            }

            // 生成正文
            $prompt = $this->applyKnowledgeContext($task->prompt->content, $task, $title);
            $r = $this->ai->generateContent($task->aiModel->id, $prompt, [
                'title'   => $title->title,
                'keyword' => $title->keyword ?? '',
            ]);
            if (!$r['success']) {
                throw new RuntimeException('AI 生成失败: ' . ($r['error'] ?? 'unknown'));
            }
            $content = $this->applyImageLibrary((string) $r['content'], $task);
            $this->assertContentValid($content, $title->title);

            // 保存文章
            $article = DB::transaction(function () use ($task, $title, $content) {
                $finalTitle = $title->title;

                if ($this->articleTitleExists($finalTitle)) {
                    $this->markTitleUsed($title);
                    throw new RuntimeException("文章标题重复，跳过: {$finalTitle}");
                }

                $needReview   = (int) ($task->need_review ?? 1);
                $status       = $needReview ? 'draft' : 'published';
                $reviewStatus = $needReview ? 'pending' : 'auto_approved';
                $publishedAt  = $needReview ? null : Carbon::now();

                $article = Article::create([
                    'title'            => $finalTitle,
                    'slug'             => $this->generateUniqueSlug(),
                    'excerpt'          => mb_substr(strip_tags($content), 0, 200),
                    'content'          => $content,
                    'category_id'      => $task->fixed_category_id ?: $this->fallbackCategoryId(),
                    'author_id'        => $task->custom_author_id ?: $task->author_id ?: $this->fallbackAuthorId(),
                    'task_id'          => $task->id,
                    'original_keyword' => $title->keyword ?? '',
                    'keywords'         => '',
                    'meta_description' => '',
                    'status'           => $status,
                    'review_status'    => $reviewStatus,
                    'is_ai_generated'  => 1,
                    'published_at'     => $publishedAt,
                ]);

                // 计数 + 标题已用
                $this->markTitleUsed($title);
                $task->increment('created_count');
                if (!$needReview) {
                    $task->increment('published_count');
                }
                $this->ai->updateModelUsage($task->aiModel->id);

                return $article;
            });

            return [
                'success'    => true,
                'article_id' => $article->id,
                'title'      => $article->title,
                'message'    => '文章生成成功',
            ];
        } catch (\Throwable $e) {
            Log::error('ArticleGenerationEngine::executeTask', [
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ---- 标题选择 ----

    private function pickNextTitle(Task $task): ?Title
    {
        $libId = $task->title_library_id;
        if (!$libId) {
            return null;
        }

        $threshold = Carbon::now()->subHours(self::FRESH_TITLE_WINDOW_HOURS);

        $picker = function () use ($libId, $threshold, $task): ?Title {
            // 优先取未使用且 24h 内的标题
            $title = Title::query()
                ->where('library_id', $libId)
                ->where('used_count', 0)
                ->where('created_at', '>=', $threshold)
                ->orderBy('id')
                ->first();
            if ($title) {
                return $title;
            }
            // 循环模式：用次数最少的（仍在 24h 窗口）
            if ($task->is_loop) {
                return Title::query()
                    ->where('library_id', $libId)
                    ->where('created_at', '>=', $threshold)
                    ->orderBy('used_count')
                    ->orderBy('id')
                    ->first();
            }
            return null;
        };

        $title = $picker();

        // 找不到时如果是循环模式则全库重置 used_count，再取一次
        if (!$title && $task->is_loop) {
            Title::where('library_id', $libId)->update(['used_count' => 0]);
            $task->increment('loop_count');
            $title = $picker();
        }

        // 标题已存在于 articles 则跳过（标记已用），最多跳 N 次防死循环
        $skip = 0;
        while ($title && $this->articleTitleExists($title->title) && $skip < self::MAX_TITLE_SKIP_ATTEMPTS) {
            $this->markTitleUsed($title);
            $title = $picker();
            $skip++;
        }

        return $title ?: null;
    }

    private function markTitleUsed(Title $title): void
    {
        Title::whereKey($title->id)->update([
            'used_count'  => DB::raw('used_count + 1'),
            'usage_count' => DB::raw('COALESCE(usage_count, 0) + 1'),
        ]);
    }

    // ---- 校验与去重 ----

    private function isDraftLimitReached(Task $task): bool
    {
        $limit = (int) ($task->draft_limit ?? 0);
        if ($limit <= 0) {
            return false;
        }
        $count = Article::query()
            ->where('task_id', $task->id)
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->count();
        return $count >= $limit;
    }

    private function articleTitleExists(string $title): bool
    {
        return Article::query()->where('title', $title)->exists();
    }

    private function assertContentValid(string $content, string $title): void
    {
        $textLength = mb_strlen(strip_tags($content));
        if ($textLength <= 0) {
            throw new RuntimeException("《{$title}》生成失败：AI 返回空正文");
        }
        if ($textLength < self::MIN_CONTENT_LENGTH) {
            throw new RuntimeException("《{$title}》生成内容过短（{$textLength} 字符 < " . self::MIN_CONTENT_LENGTH . "）");
        }
    }

    private function generateUniqueSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(8));
        } while (Article::withTrashed()->where('slug', $slug)->exists());
        return $slug;
    }

    private function fallbackCategoryId(): int
    {
        $id = (int) \App\Models\Category::query()->orderBy('sort_order')->orderBy('id')->value('id');
        if ($id === 0) {
            throw new RuntimeException('系统未配置任何文章分类，无法保存文章');
        }
        return $id;
    }

    private function fallbackAuthorId(): int
    {
        $id = (int) \App\Models\Author::query()->orderBy('id')->value('id');
        if ($id === 0) {
            throw new RuntimeException('系统未配置任何作者，无法保存文章');
        }
        return $id;
    }

    // ---- 高级特性 hooks（Phase 6.3 / 后续阶段实现）----

    /**
     * 知识库 RAG 注入：用标题 + 关键词作为查询，从 knowledge_chunks 取最相关的
     * 片段拼成 context，渲染到 prompt 的 {{Knowledge}} 占位。
     *
     * 没绑定知识库 / 检索失败时静默退化为原 prompt（保留 {{Knowledge}} 占位由
     * AiService 的 processPromptVariables 处理成空串）。
     */
    protected function applyKnowledgeContext(string $prompt, Task $task, Title $title): string
    {
        if (!$task->knowledge_base_id) {
            return $prompt;
        }
        try {
            $query = trim($title->title . ' ' . ($title->keyword ?? ''));
            $result = $this->knowledge->fetchContext((int) $task->knowledge_base_id, $query);
            if ($result['context'] !== '') {
                return str_replace('{{Knowledge}}', $result['context'], $prompt);
            }
        } catch (\Throwable $e) {
            Log::warning('knowledge context fetch failed, skipping', [
                'task_id'   => $task->id,
                'kb_id'     => $task->knowledge_base_id,
                'error'     => $e->getMessage(),
            ]);
        }
        return $prompt;
    }

    /**
     * 图片库注入。当前 stub：原样返回。后续阶段实现：根据 image_count 从
     * image_libraries 取图，按段落分布插入 markdown <img>。
     */
    protected function applyImageLibrary(string $content, Task $task): string
    {
        // TODO: 图片库与 RSS 配图注入逻辑
        return $content;
    }
}
