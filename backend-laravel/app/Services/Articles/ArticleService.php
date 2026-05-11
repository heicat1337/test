<?php

namespace App\Services\Articles;

use App\Exceptions\Api\ApiException;
use App\Models\Article;
use App\Models\ArticleReview;
use App\Models\Author;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 文章管理 service。与老 backend includes/article_service.php 行为对齐：
 *   - listArticles, createArticle, getArticle, updateArticle
 *   - reviewArticle, publishArticle, trashArticle
 *
 * 给鉴权管理 API（/api/v1/articles 写路径）与 Filament Resource 复用。
 *
 * slug 策略沿用老 backend：8 字符随机（不是 title-slug），频繁碰撞概率极低；
 * 碰撞时重生成。前端 URL 跨语言保持稳定。
 */
class ArticleService
{
    /**
     * @return array{items: array, pagination: array{page:int, per_page:int, total:int, total_pages:int}}
     */
    public function listArticles(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $q = Article::query()
            ->select(['id', 'title', 'slug', 'status', 'review_status',
                'task_id', 'author_id', 'category_id', 'published_at',
                'created_at', 'updated_at']);

        // 过滤映射
        $map = [
            'task_id'       => 'task_id',
            'status'        => 'status',
            'review_status' => 'review_status',
            'author_id'     => 'author_id',
        ];
        foreach ($map as $key => $col) {
            if (!empty($filters[$key])) {
                $q->where($col, $filters[$key]);
            }
        }

        if (!empty($filters['search'])) {
            $kw = '%' . $filters['search'] . '%';
            $q->where(fn ($q2) => $q2->where('title', 'LIKE', $kw)->orWhere('content', 'LIKE', $kw));
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get()
            ->toArray();

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

    public function getArticle(int $articleId): array
    {
        $article = Article::with(['task:id,name', 'author:id,name', 'category:id,name'])
            ->whereKey($articleId)
            ->first();

        if (!$article) {
            throw ApiException::notFound('article_not_found', '文章不存在');
        }

        return $this->serializeFull($article);
    }

    public function createArticle(array $data): array
    {
        $normalized = $this->normalizeCreateInput($data);

        $workflow = ArticleWorkflow::normalize(
            $normalized['status'],
            $normalized['review_status'],
            null,
        );

        $slug = $normalized['slug'] ?? $this->generateUniqueSlug();
        $excerpt = $normalized['excerpt'] !== ''
            ? $normalized['excerpt']
            : mb_substr(strip_tags($normalized['content']), 0, 200);

        $article = Article::create([
            'title'            => $normalized['title'],
            'slug'             => $slug,
            'content'          => $normalized['content'],
            'excerpt'          => $excerpt,
            'keywords'         => $normalized['keywords'],
            'meta_description' => $normalized['meta_description'],
            'category_id'      => $normalized['category_id'],
            'author_id'        => $normalized['author_id'],
            'task_id'          => $normalized['task_id'],
            'status'           => $workflow['status'],
            'review_status'    => $workflow['review_status'],
            'is_ai_generated'  => $normalized['is_ai_generated'],
            'published_at'     => $workflow['published_at'],
        ]);

        return $this->getArticle($article->id);
    }

    public function updateArticle(int $articleId, array $data): array
    {
        $existing = $this->mustFindArticle($articleId);
        $normalized = $this->normalizeUpdateInput($data, $existing);
        if ($normalized === []) {
            throw new ApiException('validation_failed', '没有可更新的字段', 422);
        }

        $existing->fill($normalized)->save();

        return $this->getArticle($articleId);
    }

    public function reviewArticle(int $articleId, string $reviewStatus, string $reviewNote, int $auditAdminId): array
    {
        $article = $this->mustFindArticle($articleId);
        $reviewStatus = trim($reviewStatus);

        if (!in_array($reviewStatus, ArticleWorkflow::REVIEW_STATUSES, true)) {
            throw ApiException::validationFailed(['review_status' => '审核状态无效']);
        }

        $desiredStatus = $article->status ?: 'draft';

        if (in_array($reviewStatus, ['approved', 'auto_approved'], true)) {
            $taskNeedReview = 1;
            if ($article->task_id) {
                $taskNeedReview = (int) Task::whereKey($article->task_id)->value('need_review') ?? 1;
            }
            if ($reviewStatus === 'auto_approved' || $taskNeedReview === 0) {
                $desiredStatus = 'published';
            }
        }

        $workflow = ArticleWorkflow::normalize(
            $desiredStatus,
            $reviewStatus,
            optional($article->published_at)->toDateTimeString(),
        );

        DB::transaction(function () use ($article, $workflow, $reviewStatus, $reviewNote, $auditAdminId) {
            $article->update([
                'status'        => $workflow['status'],
                'review_status' => $workflow['review_status'],
                'published_at'  => $workflow['published_at'],
            ]);

            ArticleReview::create([
                'article_id'    => $article->id,
                'admin_id'      => $auditAdminId,
                'review_status' => $reviewStatus,
                'review_note'   => trim($reviewNote),
            ]);
        });

        return $this->getArticle($article->id);
    }

    public function publishArticle(int $articleId): array
    {
        $article = $this->mustFindArticle($articleId);

        if (!in_array($article->review_status, ['approved', 'auto_approved'], true)) {
            throw new ApiException(
                'article_not_publishable',
                '当前文章状态不允许直接发布',
                409,
            );
        }

        $workflow = ArticleWorkflow::normalize(
            'published',
            $article->review_status,
            optional($article->published_at)->toDateTimeString(),
        );

        $article->update([
            'status'        => $workflow['status'],
            'review_status' => $workflow['review_status'],
            'published_at'  => $workflow['published_at'],
        ]);

        return $this->getArticle($article->id);
    }

    /**
     * @return array{id:int, trashed:true}
     */
    public function trashArticle(int $articleId): array
    {
        $article = $this->mustFindArticle($articleId);
        $article->delete();   // SoftDeletes → deleted_at = now()

        return ['id' => $articleId, 'trashed' => true];
    }

    // ---- 内部 helpers ----

    private function mustFindArticle(int $articleId): Article
    {
        $article = Article::whereKey($articleId)->first();
        if (!$article) {
            throw ApiException::notFound('article_not_found', '文章不存在');
        }
        return $article;
    }

    private function normalizeCreateInput(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        $errors = [];
        if ($title === '') {
            $errors['title'] = '文章标题不能为空';
        }
        if ($content === '') {
            $errors['content'] = '文章内容不能为空';
        }
        if ($errors) {
            throw ApiException::validationFailed($errors);
        }

        $normalized = [
            'title'            => $title,
            'content'          => $content,
            'excerpt'          => trim((string) ($data['excerpt'] ?? '')),
            'keywords'         => trim((string) ($data['keywords'] ?? '')),
            'meta_description' => trim((string) ($data['meta_description'] ?? '')),
            'status'           => trim((string) ($data['status'] ?? 'draft')),
            'review_status'    => trim((string) ($data['review_status'] ?? 'pending')),
            'is_ai_generated'  => $this->toFlag($data['is_ai_generated'] ?? 0),
            'slug'             => null,
        ];

        if (!empty($data['slug'])) {
            $slug = trim((string) $data['slug']);
            $this->ensureSlugAvailable($slug);
            $normalized['slug'] = $slug;
        }

        $normalized['category_id'] = $this->mustExistReference(Category::class, $data['category_id'] ?? null, 'category_id');
        $normalized['author_id']   = $this->mustExistReference(Author::class, $data['author_id'] ?? null, 'author_id');
        $normalized['task_id']     = $this->maybeReference(Task::class, $data['task_id'] ?? null, 'task_id');

        return $normalized;
    }

    private function normalizeUpdateInput(array $data, Article $existing): array
    {
        $normalized = [];
        $errors = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            $title === '' ? $errors['title'] = '文章标题不能为空' : $normalized['title'] = $title;
        }
        if (array_key_exists('content', $data)) {
            $content = trim((string) $data['content']);
            $content === '' ? $errors['content'] = '文章内容不能为空' : $normalized['content'] = $content;
        }

        foreach (['excerpt', 'keywords', 'meta_description'] as $f) {
            if (array_key_exists($f, $data)) {
                $normalized[$f] = trim((string) $data[$f]);
            }
        }

        if (array_key_exists('category_id', $data)) {
            $normalized['category_id'] = $this->mustExistReference(Category::class, $data['category_id'], 'category_id');
        }
        if (array_key_exists('author_id', $data)) {
            $normalized['author_id'] = $this->mustExistReference(Author::class, $data['author_id'], 'author_id');
        }
        if (array_key_exists('task_id', $data)) {
            $normalized['task_id'] = $this->maybeReference(Task::class, $data['task_id'], 'task_id');
        }

        if (array_key_exists('slug', $data)) {
            $slug = trim((string) $data['slug']);
            if ($slug === '') {
                $errors['slug'] = 'slug 不能为空';
            } else {
                $this->ensureSlugAvailable($slug, $existing->id);
                $normalized['slug'] = $slug;
            }
        } elseif (isset($normalized['title']) && $normalized['title'] !== $existing->title) {
            $normalized['slug'] = $this->generateUniqueSlug($existing->id);
        }

        if ($errors) {
            throw ApiException::validationFailed($errors);
        }

        return $normalized;
    }

    private function generateUniqueSlug(?int $excludeId = null): string
    {
        do {
            $slug = Str::lower(Str::random(8));
            $q = Article::withTrashed()->where('slug', $slug);
            if ($excludeId !== null) {
                $q->where('id', '!=', $excludeId);
            }
        } while ($q->exists());
        return $slug;
    }

    private function ensureSlugAvailable(string $slug, ?int $excludeId = null): void
    {
        $q = Article::withTrashed()->where('slug', $slug);
        if ($excludeId !== null) {
            $q->where('id', '!=', $excludeId);
        }
        if ($q->exists()) {
            throw ApiException::validationFailed(['slug' => 'slug 已被占用']);
        }
    }

    private function mustExistReference(string $modelClass, mixed $value, string $field): int
    {
        $id = (int) $value;
        if ($id <= 0 || !$modelClass::whereKey($id)->exists()) {
            throw ApiException::validationFailed([$field => '关联记录不存在']);
        }
        return $id;
    }

    private function maybeReference(string $modelClass, mixed $value, string $field): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }
        return $this->mustExistReference($modelClass, $value, $field);
    }

    private function toFlag(mixed $v): int
    {
        return $v ? 1 : 0;
    }

    private function serializeFull(Article $a): array
    {
        return [
            'id'               => (int) $a->id,
            'title'            => $a->title,
            'slug'             => $a->slug,
            'content'          => $a->content,
            'excerpt'          => $a->excerpt,
            'keywords'         => $a->keywords,
            'meta_description' => $a->meta_description,
            'status'           => $a->status,
            'review_status'    => $a->review_status,
            'task_id'          => $a->task_id !== null ? (int) $a->task_id : null,
            'task_name'        => $a->task?->name,
            'author_id'        => $a->author_id !== null ? (int) $a->author_id : null,
            'author_name'      => $a->author?->name,
            'category_id'      => $a->category_id !== null ? (int) $a->category_id : null,
            'category_name'    => $a->category?->name,
            'is_ai_generated'  => (int) ($a->is_ai_generated ?? 0),
            'published_at'     => optional($a->published_at)->toDateTimeString(),
            'created_at'       => optional($a->created_at)->toDateTimeString(),
            'updated_at'       => optional($a->updated_at)->toDateTimeString(),
            'images'           => [],   // 老接口含 article_images 嵌套；Phase 5.1 暂不实现，保留键避免前端报错
        ];
    }
}
