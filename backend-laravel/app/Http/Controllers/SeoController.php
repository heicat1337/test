<?php

namespace App\Http\Controllers;

use App\Models\NavCategory;
use App\Models\NavSite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * 爬虫专属 SSR。与老 backend render_seo.php 对齐 3 个路由：
 *   GET /__seo/home              首页（所有分类 + 项目列表）
 *   GET /__seo/category/{slug}   分类页（单个分类下的所有项目）
 *   GET /__seo/project/{id}      项目详情页（单个 NavSite + JSON-LD）
 *
 * nginx 通过 UA 检测命中爬虫后内部 rewrite 到 /__seo/*，转发到这里。
 * 真实用户走 Vue SPA，互不影响。
 *
 * 复用 NavCategory/NavSite 模型，不需要单独序列化 helper。
 */
class SeoController extends Controller
{
    private const CACHE_TTL = 300; // 5 分钟

    public function home(Request $request): Response
    {
        $baseUrl = rtrim(env('APP_URL', 'https://xuaweb3.com'), '/');
        $cats = $this->loadCategories();

        $totalSites = array_sum(array_map(fn ($c) => count($c['sites']), $cats));

        $jsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => '玄猫Web3',
                'url'      => $baseUrl . '/',
                'description' => '专业的 Web3 行业资讯与导航平台',
                'potentialAction' => [
                    '@type'        => 'SearchAction',
                    'target'       => $baseUrl . '/?q={search_term_string}',
                    'query-input'  => 'required name=search_term_string',
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type'    => 'CollectionPage',
                'name'     => '玄猫Web3 导航',
                'url'      => $baseUrl . '/',
                'hasPart'  => array_map(fn ($c) => [
                    '@type'         => 'ItemList',
                    'name'          => $c['name'],
                    'url'           => $baseUrl . '/c/' . $c['slug'],
                    'numberOfItems' => count($c['sites']),
                ], $cats),
            ],
        ];

        return response()->view('seo.home', [
            'baseUrl'    => $baseUrl,
            'title'      => '玄猫Web3 - Web3 行业资讯与导航平台',
            'description' => '玄猫Web3是专业的Web3行业资讯与导航平台，提供区块链、DeFi、NFT、加密货币、交易所、钱包、L2、跨链桥等领域的最新动态、深度分析和项目评测。',
            'canonical'  => $baseUrl . '/',
            'jsonLd'     => $jsonLd,
            'cats'       => $cats,
            'totalSites' => $totalSites,
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600')
          ->header('X-Robots-Tag', 'index,follow');
    }

    public function category(Request $request, string $slug): Response
    {
        $baseUrl = rtrim(env('APP_URL', 'https://xuaweb3.com'), '/');
        $cats = $this->loadCategories();

        $cat = collect($cats)->firstWhere('slug', $slug);
        if (!$cat) {
            return response()->view('seo.notfound', [
                'baseUrl' => $baseUrl,
                'message' => '未找到分类「' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '」',
                'title'   => '未找到分类 - 玄猫Web3',
            ], 404);
        }

        $canonical   = $baseUrl . '/c/' . $cat['slug'];
        $sample      = array_slice(array_column($cat['sites'], 'name'), 0, 5);
        $sampleStr   = implode('、', $sample);
        $title       = $cat['name'] . ' | 玄猫Web3 导航';
        $description = $cat['name'] . '分类下精选 ' . count($cat['sites']) . ' 个 Web3 项目'
            . ($sampleStr !== '' ? '，包含 ' . $sampleStr : '')
            . '。玄猫Web3 持续更新，覆盖区块链全生态。';

        $jsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type'    => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => '首页',         'item' => $baseUrl . '/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $cat['name'],  'item' => $canonical],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type'    => 'ItemList',
                'name'     => $cat['name'] . ' - Web3 工具与项目',
                'numberOfItems' => count($cat['sites']),
                'itemListElement' => array_map(fn ($i, $s) => [
                    '@type'       => 'ListItem',
                    'position'    => $i + 1,
                    'url'         => $s['url'],
                    'name'        => $s['name'],
                    'description' => $s['description'],
                ], array_keys($cat['sites']), $cat['sites']),
            ],
        ];

        return response()->view('seo.category', [
            'baseUrl'     => $baseUrl,
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'jsonLd'      => $jsonLd,
            'cat'         => $cat,
            'allCats'     => $cats,
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600')
          ->header('X-Robots-Tag', 'index,follow');
    }

    public function project(Request $request, int $id): Response
    {
        $baseUrl = rtrim(env('APP_URL', 'https://xuaweb3.com'), '/');
        $site = $this->loadSite($id);
        if (!$site) {
            return response()->view('seo.notfound', [
                'baseUrl' => $baseUrl,
                'message' => '未找到项目',
                'title'   => '未找到项目 - 玄猫Web3',
            ], 404);
        }

        $canonical = $baseUrl . '/project/' . $site['id'];
        $tagPart = !empty($site['tags']) ? '，标签：' . implode('、', $site['tags']) : '';
        $description = $site['name']
            . (!empty($site['category']['name']) ? '（' . $site['category']['name'] . '分类）' : '')
            . ' - ' . ($site['description'] ?: 'Web3 项目')
            . $tagPart . '。在玄猫Web3 一键访问官网。';
        $title = $site['name'] . ' | 玄猫Web3';

        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $baseUrl . '/'],
        ];
        if (!empty($site['category'])) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $site['category']['name'], 'item' => $baseUrl . '/c/' . $site['category']['slug']];
            $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $site['name'], 'item' => $canonical];
        } else {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $site['name'], 'item' => $canonical];
        }

        $product = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $site['name'],
            'url'      => $site['url'],
            'description' => $site['description'],
        ];
        if (!empty($site['rating']) && $site['rating'] > 0) {
            $product['aggregateRating'] = [
                '@type'        => 'AggregateRating',
                'ratingValue'  => $site['rating'],
                'bestRating'   => 5,
                'ratingCount'  => 1,
            ];
        }

        $jsonLd = [
            ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs],
            $product,
        ];

        return response()->view('seo.project', [
            'baseUrl'     => $baseUrl,
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'jsonLd'      => $jsonLd,
            'site'        => $site,
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600')
          ->header('X-Robots-Tag', 'index,follow');
    }

    private function loadCategories(): array
    {
        return Cache::remember('seo.categories', self::CACHE_TTL, function (): array {
            $cats = NavCategory::with([
                    'sites' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return $cats->map(fn (NavCategory $c) => [
                'id'    => (int) $c->id,
                'name'  => $c->name,
                'slug'  => $c->slug ?: 'cat-' . $c->id,
                'icon'  => (string) $c->icon,
                'sites' => $c->sites->map(fn (NavSite $s) => $this->serializeSite($s, $c->id))->all(),
            ])->all();
        });
    }

    private function loadSite(int $id): ?array
    {
        return Cache::remember('seo.site.' . $id, self::CACHE_TTL, function () use ($id): ?array {
            $s = NavSite::with('category')->find($id);
            if (!$s) {
                return null;
            }
            $payload = $this->serializeSite($s);
            $payload['category'] = $s->category ? [
                'id'   => (int) $s->category->id,
                'name' => $s->category->name,
                'slug' => $s->category->slug ?: 'cat-' . $s->category->id,
                'icon' => (string) $s->category->icon,
            ] : null;
            return $payload;
        });
    }

    private function serializeSite(NavSite $s, ?int $forceCatId = null): array
    {
        return [
            'id'             => (int) $s->id,
            'name'           => $s->name,
            'url'            => $s->url,
            'description'    => (string) ($s->description ?? ''),
            'icon'           => (string) ($s->icon ?? ''),
            'sort_order'     => (int) $s->sort_order,
            'category_id'    => $forceCatId ?? $s->category_id,
            'is_recommended' => (bool) $s->is_recommended,
            'tags'           => $s->tags ?: [],
            'rating'         => (float) ($s->rating ?? 0),
            'social_links'   => $s->social_links ?: [],
            'screenshot_url' => (string) ($s->screenshot_url ?? ''),
        ];
    }
}
