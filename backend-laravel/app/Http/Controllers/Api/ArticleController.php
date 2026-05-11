<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 公开文章 API（无需鉴权，对应老 backend api/v1/index.php 中的 articles 公开路由）。
 *
 * 响应格式与老 backend 完全一致：
 *   { "success": true, "data": ..., "error": null, "meta": { "request_id", "timestamp" } }
 */
class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $status  = $request->query('status', 'published');

        $q = Article::query()
            ->select([
                'articles.id', 'articles.title', 'articles.slug', 'articles.excerpt',
                'articles.category_id', 'articles.author_id', 'articles.status',
                'articles.review_status', 'articles.view_count', 'articles.is_featured',
                'articles.keywords', 'articles.published_at',
                'articles.created_at', 'articles.updated_at',
            ])
            ->with([
                'category:id,name,slug',
                'author:id,name,avatar',
            ]);

        if ($status && $status !== 'all') {
            $q->where('articles.status', $status);
        }

        $total = (clone $q)->count();
        $items = $q->orderBy('articles.is_featured', 'desc')
            ->orderBy('articles.published_at', 'desc')
            ->orderBy('articles.created_at', 'desc')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Article $a) => $this->serializeListItem($a))
            ->values()
            ->all();

        return $this->success([
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) max(1, ceil($total / $perPage)),
            ],
        ], 30, $request);
    }

    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        $article = Article::with(['category:id,name,slug', 'author:id,name,avatar,bio'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$article) {
            return $this->error('not_found', '文章不存在', 404, $request);
        }

        // 阅读量 +1（老 backend 行为一致；不走 saved hook 避免触发 nav cache bump）
        Article::where('id', $article->id)->increment('view_count');
        $article->view_count = ($article->view_count ?? 0) + 1;

        return $this->success($this->serializeFull($article), 30, $request);
    }

    private function serializeListItem(Article $a): array
    {
        return [
            'id'             => (int) $a->id,
            'title'          => (string) $a->title,
            'slug'           => (string) $a->slug,
            'excerpt'        => (string) ($a->excerpt ?? ''),
            'category_id'    => $a->category_id !== null ? (int) $a->category_id : null,
            'category_name'  => $a->category?->name,
            'author_id'      => $a->author_id !== null ? (int) $a->author_id : null,
            'author_name'    => $a->author?->name,
            'status'         => (string) $a->status,
            'review_status'  => (string) $a->review_status,
            'view_count'     => (int) ($a->view_count ?? 0),
            'is_featured'    => (bool) $a->is_featured,
            'tags'           => $a->keywords,  // mutator → array
            'published_at'   => optional($a->published_at)->toAtomString(),
            'created_at'     => optional($a->created_at)->toAtomString(),
            'updated_at'     => optional($a->updated_at)->toAtomString(),
        ];
    }

    private function serializeFull(Article $a): array
    {
        $base = $this->serializeListItem($a);
        return array_merge($base, [
            'content'          => (string) $a->content,
            'meta_description' => (string) ($a->meta_description ?? ''),
            'original_keyword' => (string) ($a->original_keyword ?? ''),
            'is_ai_generated'  => (bool) $a->is_ai_generated,
            'featured_image'   => (string) ($a->featured_image ?? ''),
            'like_count'       => (int) ($a->like_count ?? 0),
            'comment_count'    => (int) ($a->comment_count ?? 0),
            'category'         => $a->category ? [
                'id'   => (int) $a->category->id,
                'name' => $a->category->name,
                'slug' => $a->category->slug,
            ] : null,
            'author'           => $a->author ? [
                'id'     => (int) $a->author->id,
                'name'   => $a->author->name,
                'avatar' => (string) ($a->author->avatar ?? ''),
                'bio'    => (string) ($a->author->bio ?? ''),
            ] : null,
        ]);
    }

    private function success(mixed $data, int $cacheSeconds, Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => [
                'request_id' => $this->requestId($request),
                'timestamp'  => now()->toAtomString(),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', "public, max-age={$cacheSeconds}, stale-while-revalidate=300");
    }

    private function error(string $code, string $message, int $status, Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data'    => null,
            'error'   => ['code' => $code, 'message' => $message],
            'meta'    => [
                'request_id' => $this->requestId($request),
                'timestamp'  => now()->toAtomString(),
            ],
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function requestId(Request $request): string
    {
        return $request->header('X-Request-Id', (string) Str::uuid());
    }
}
