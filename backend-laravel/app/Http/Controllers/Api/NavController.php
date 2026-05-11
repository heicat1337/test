<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NavCategory;
use App\Models\NavSite;
use App\Support\NavCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 导航公共 API（无需鉴权，对应老 backend api/v1/index.php 中的 4 个 nav 路由）。
 *
 * 响应格式与老 backend 完全一致：
 *   { "success": true, "data": ..., "error": null, "meta": { "request_id", "timestamp" } }
 *
 * 缓存：60s 文件缓存（Cache::remember），写入路由由模型事件清掉。
 */
class NavController extends Controller
{
    private const CACHE_TTL = 60;

    public function categories(Request $request): JsonResponse
    {
        $data = Cache::remember(NavCache::key('categories'), self::CACHE_TTL, function () {
            $cats = NavCategory::with(['sites' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return $cats->map(fn (NavCategory $c) => [
                'id'         => (int) $c->id,
                'name'       => $c->name,
                'slug'       => $c->slug ?: 'cat-' . $c->id,
                'icon'       => (string) $c->icon,
                'sort_order' => (int) $c->sort_order,
                'sites'      => $c->sites->map(fn (NavSite $s) => $this->serializeSite($s, $c->id))->all(),
            ])->all();
        });

        return $this->success($data, 60, $request);
    }

    public function sites(Request $request): JsonResponse
    {
        $categoryId = (int) $request->query('category_id', 0);
        $cacheKey   = NavCache::key('sites.' . ($categoryId > 0 ? $categoryId : 'all'));

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($categoryId) {
            $q = NavSite::query()->orderBy('sort_order')->orderBy('id');
            if ($categoryId > 0) {
                $q->where('category_id', $categoryId);
            }
            return $q->get()->map(fn (NavSite $s) => $this->serializeSite($s))->all();
        });

        return $this->success($data, 60, $request);
    }

    public function site(Request $request, int $id): JsonResponse
    {
        $cacheKey = NavCache::key('site.' . $id);

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            $site = NavSite::with('category')->find($id);
            if (!$site) {
                return null;
            }
            $payload = $this->serializeSite($site);
            $payload['category'] = $site->category ? [
                'id'   => (int) $site->category->id,
                'name' => $site->category->name,
                'slug' => $site->category->slug ?: 'cat-' . $site->category->id,
                'icon' => (string) $site->category->icon,
            ] : null;
            return $payload;
        });

        if ($data === null) {
            return $this->error('not_found', '项目不存在', 404, $request);
        }

        return $this->success($data, 60, $request);
    }

    public function recommended(Request $request): JsonResponse
    {
        $data = Cache::remember(NavCache::key('recommended'), self::CACHE_TTL, function () {
            return NavSite::query()
                ->where('is_recommended', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (NavSite $s) => $this->serializeSite($s))
                ->all();
        });

        return $this->success($data, 60, $request);
    }

    private function serializeSite(NavSite $s, ?int $forceCategoryId = null): array
    {
        // 注意 social_links 空值返 []（数组）而不是 {}（对象）。
        // 老 backend 用 PHP json_decode('{}', true) 得到空 PHP 数组、再 json_encode 输出 []，
        // 前端依赖此格式（Object.entries 对两种都安全，但响应字面量必须一致）。
        return [
            'id'             => (int) $s->id,
            'name'           => $s->name,
            'url'            => $s->url,
            'description'    => (string) ($s->description ?? ''),
            'icon'           => (string) ($s->icon ?? ''),
            'sort_order'     => (int) $s->sort_order,
            'category_id'    => $forceCategoryId ?? ($s->category_id !== null ? (int) $s->category_id : null),
            'is_recommended' => (bool) $s->is_recommended,
            'tags'           => $s->tags ?: [],
            'rating'         => (float) ($s->rating ?? 0),
            'social_links'   => $s->social_links ?: [],
            'screenshot_url' => (string) ($s->screenshot_url ?? ''),
        ];
    }

    private function success(mixed $data, int $cacheSeconds, Request $request): JsonResponse
    {
        $payload = [
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => [
                'request_id' => $this->requestId($request),
                'timestamp'  => now()->toAtomString(),
            ],
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
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
