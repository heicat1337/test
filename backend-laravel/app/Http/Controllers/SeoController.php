<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\NavCategory;
use App\Models\NavSite;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 爬虫专属 SSR。与老 backend render_seo.php 对齐，覆盖 4 个路由：
 *   GET /__seo/home              首页（所有分类 + 项目列表）
 *   GET /__seo/category/{slug}   分类页（单个分类下的所有项目）
 *   GET /__seo/project/{id}      项目详情页（单个 NavSite + JSON-LD）
 *   GET /__seo/articles          文章列表页（最新发布文章 + 自指 canonical）
 *   GET /__seo/article/{slug}    文章详情页（单篇 Article + 自指 canonical）
 *
 * nginx 通过 UA 检测命中爬虫后内部 rewrite 到 /__seo/*，转发到这里。
 * 真实用户走 Vue SPA，互不影响。
 *
 * 复用 NavCategory/NavSite/Article 模型，不需要单独序列化 helper。
 */
class SeoController extends Controller
{
    private const CACHE_TTL = 300; // 5 分钟

    public function home(Request $request): Response
    {
        $baseUrl = PublicUrl::base();
        $cats = $this->loadCategories();
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $cats = array_values(array_filter(array_map(function (array $c) use ($needle) {
                $sites = array_values(array_filter($c['sites'], function ($s) use ($needle, $c) {
                    $hay = mb_strtolower($c['name'].' '.$s['name'].' '.($s['description'] ?? ''));
                    return mb_strpos($hay, $needle) !== false;
                }));
                if ($sites === [] && mb_strpos(mb_strtolower($c['name']), $needle) === false) {
                    return null;
                }
                if ($sites !== []) {
                    $c['sites'] = $sites;
                }
                return $c;
            }, $cats)));
        }

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
                '@type'    => 'Organization',
                'name'     => '玄猫Web3',
                'url'      => $baseUrl . '/',
                'logo'     => $baseUrl . '/og/default.svg',
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
            'ogImage'    => PublicUrl::of('/og/default.svg'),
            'jsonLd'     => $jsonLd,
            'cats'       => $cats,
            'totalSites' => $totalSites,
            'q'          => trim((string) $request->query('q', '')),
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600')
          ->header('X-Robots-Tag', 'index,follow');
    }

    public function category(Request $request, string $slug): Response
    {
        $baseUrl = PublicUrl::base();
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
        $baseUrl = PublicUrl::base();
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

    public function articleIndex(Request $request): Response
    {
        $baseUrl = PublicUrl::base();
        $articles = $this->loadLatestArticles();
        $canonical = $baseUrl . '/articles';

        $title = 'Web3 文章｜玄猫Web3';
        $description = '玄猫Web3 Web3 文章频道，持续更新区块链、DeFi、NFT、加密货币、交易所、钱包、L2、跨链桥等行业资讯、技术分析与深度研究。';

        $jsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $baseUrl . '/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => '文章', 'item' => $canonical],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => '玄猫Web3 文章',
                'url' => $canonical,
                'description' => $description,
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'numberOfItems' => count($articles),
                    'itemListElement' => array_map(fn ($i, $article) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'url' => $baseUrl . '/articles/' . $article['slug'],
                        'name' => $article['title'],
                        'description' => $article['description'],
                    ], array_keys($articles), $articles),
                ],
            ],
        ];

        return response()->view('seo.articles', [
            'baseUrl'     => $baseUrl,
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'jsonLd'      => $jsonLd,
            'articles'    => $articles,
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600')
          ->header('X-Robots-Tag', 'index,follow');
    }

    /**
     * 文章详情页 SSR。这是 P0 的核心缺口：sitemap 提交了全部已发布 /articles/{slug}，
     * 但此前没有任何爬虫路由，爬虫拿到的是 index.html 空壳 + 首页 canonical，
     * 等于把 2000 篇文章全判成首页副本。这里补上真 title / 正文 / 自指 canonical。
     */
    public function article(Request $request, string $slug): Response
    {
        $baseUrl = PublicUrl::base();
        $article = $this->loadArticle($slug);

        // 未发布 / 不存在 → 404 + noindex，绝不吐首页骨架冒充文章。
        if (!$article) {
            return response()->view('seo.notfound', [
                'baseUrl' => $baseUrl,
                'message' => '未找到文章',
                'title'   => '未找到文章 - 玄猫Web3',
            ], 404)->header('X-Robots-Tag', 'noindex,follow');
        }

        // 自指 canonical —— 验收硬指标（Nova #73）：彻底断掉从 index.html 继承的首页 canonical。
        $canonical = $baseUrl . '/articles/' . $article['slug'];

        // 标题：文章标题已含关键词（按 original_keyword 生成）。全角 32 内挂品牌后缀，超了只留标题。
        $title = mb_strlen($article['title']) > 32
            ? $article['title']
            : $article['title'] . '｜玄猫Web3';

        // description：第一个非空者胜 —— meta_description → excerpt → 正文摘要。
        // 正文兜底保证唯一性（Atlas #77 gate 1）：每篇 description 取本文字段，无全局模板，
        // 2000 篇不会塌成同一句稀薄模板。
        $description = $this->firstNonEmpty([
            $article['meta_description'],
            $article['excerpt'],
            $article['content_text'],
            $article['original_keyword'],   // 三者全空时的最后兜底，绝不吐空 description
        ]);
        $description = trim(preg_replace('/\s+/u', ' ', $description));

        // 含词保证（Atlas #77 gate 2）：description 必须带 original_keyword。
        // 命中 meta_description/excerpt 分支时词可能丢，这里补；已含则不动，保持自然语义。
        $kw = trim($article['original_keyword']);
        if ($kw !== '' && mb_stripos($description, $kw) === false) {
            $description = $kw . '：' . $description;
        }
        $description = Str::limit($description, 155);

        // OG 图：优先文章自带 featured_image，否则回退到模板卡（Iris 那张）。
        // 同一 pass 接掉 og:image / twitter:image，不留到「美化 later」（Iris #69）。
        // 兜底卡 = Iris #75 定的静态品牌卡（1200×630，部署到 nginx root /og/default.png）。
        $ogImage = $this->absoluteImage($article['featured_image'], $baseUrl)
            ?: PublicUrl::of('/og/default.svg');

        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $baseUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => '文章', 'item' => $baseUrl . '/articles'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $canonical],
        ];

        $jsonLd = [
            ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs],
            array_filter([
                '@context'         => 'https://schema.org',
                '@type'            => 'Article',
                'headline'         => $article['title'],
                'description'      => $description,
                'image'            => $ogImage,
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
                'datePublished'    => $article['published_at'],
                'dateModified'     => $article['updated_at'] ?: $article['published_at'],
                'author'           => $article['author']
                    ? ['@type' => 'Person', 'name' => $article['author']]
                    : ['@type' => 'Organization', 'name' => '玄猫Web3'],
                'publisher'        => [
                    '@type' => 'Organization',
                    'name'  => '玄猫Web3',
                    'url'   => $baseUrl . '/',
                    'logo'  => ['@type' => 'ImageObject', 'url' => $baseUrl . '/og/default.svg'],
                ],
                'keywords'         => $article['keywords'] ? implode(',', $article['keywords']) : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        return response()->view('seo.article', [
            'baseUrl'     => $baseUrl,
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'ogImage'     => $ogImage,
            'ogType'      => 'article',
            'jsonLd'      => $jsonLd,
            'article'     => $article,
            'related'     => $this->loadRelatedArticles((int) $article['id'], $article['category_id'] ?? null),
            'relatedSites'=> $this->loadRelatedSites(),
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600')
          ->header('X-Robots-Tag', 'index,follow');
    }

    private function loadArticle(string $slug): ?array
    {
        return Cache::remember('seo.article.' . $slug, self::CACHE_TTL, function () use ($slug): ?array {
            $a = Article::with(['author', 'category'])
                ->published()
                ->where('slug', $slug)
                ->first();
            if (!$a) {
                return null;
            }

            return [
                'id'               => (int) $a->id,
                'slug'             => (string) $a->slug,
                'title'            => (string) $a->title,
                'excerpt'          => (string) ($a->excerpt ?? ''),
                'content'          => (string) ($a->content ?? ''),
                'content_text'     => trim(strip_tags((string) ($a->content ?? ''))),
                'meta_description' => (string) ($a->meta_description ?? ''),
                'original_keyword' => (string) ($a->original_keyword ?? ''),
                'keywords'         => $a->tagList(),
                'featured_image'   => (string) ($a->featured_image ?? ''),
                'author'           => $a->author?->name ?? '',
                'published_at'     => optional($a->published_at)->toIso8601String() ?? '',
                'updated_at'       => optional($a->updated_at)->toIso8601String() ?? '',
                'published_human'  => $a->published_at ? Carbon::parse($a->published_at)->translatedFormat('Y年n月j日') : '',
                'category_id'      => $a->category_id !== null ? (int) $a->category_id : null,
                'category_name'    => $a->category?->name ?? '',
            ];
        });
    }

    private function loadLatestArticles(): array
    {
        return Cache::remember('seo.articles.latest', self::CACHE_TTL, function (): array {
            return Article::with(['author', 'category'])
                ->published()
                ->orderByDesc('published_at')
                ->orderByDesc('updated_at')
                ->limit(30)
                ->get()
                ->map(function (Article $a): array {
                    $description = $this->firstNonEmpty([
                        (string) ($a->meta_description ?? ''),
                        (string) ($a->excerpt ?? ''),
                        trim(strip_tags((string) ($a->content ?? ''))),
                        (string) ($a->original_keyword ?? ''),
                    ]);

                    return [
                        'slug'            => (string) $a->slug,
                        'title'           => (string) $a->title,
                        'description'     => Str::limit(trim(preg_replace('/\s+/u', ' ', $description)), 120),
                        'category'        => $a->category?->name ?? '',
                        'author'          => $a->author?->name ?? '',
                        'published_at'    => optional($a->published_at)->toIso8601String() ?? '',
                        'published_human' => $a->published_at ? Carbon::parse($a->published_at)->translatedFormat('Y年n月j日') : '',
                    ];
                })
                ->all();
        });
    }

    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                return $c;
            }
        }
        return '';
    }

    private function absoluteImage(string $path, string $baseUrl): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        return $baseUrl . '/' . ltrim($path, '/');
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

    public function legal(Request $request, string $page): Response
    {
        $pages = $this->legalCopy();
        if (!isset($pages[$page])) {
            return response()->view('seo.notfound', [
                'baseUrl' => PublicUrl::base(),
                'message' => '页面不存在',
                'title'   => '未找到页面 - 玄猫Web3',
            ], 404)->header('X-Robots-Tag', 'noindex,follow');
        }

        $copy = $pages[$page];
        $baseUrl = PublicUrl::base();
        $canonical = $baseUrl . '/' . $page;

        return response()->view('seo.legal', [
            'baseUrl'     => $baseUrl,
            'title'       => $copy['title'] . ' - 玄猫Web3',
            'description' => $copy['description'],
            'canonical'   => $canonical,
            'ogImage'     => PublicUrl::of('/og/default.svg'),
            'jsonLd'      => [[
                '@context' => 'https://schema.org',
                '@type'    => 'WebPage',
                'name'     => $copy['title'],
                'url'      => $canonical,
            ]],
            'heading'     => $copy['title'],
            'paragraphs'  => $copy['paragraphs'],
        ])->header('Cache-Control', 'public, max-age=3600')
          ->header('X-Robots-Tag', 'index,follow');
    }

    private function loadRelatedArticles(int $articleId, ?int $categoryId): array
    {
        $q = Article::query()->published()->where('id', '!=', $articleId);
        if ($categoryId) {
            $q->where('category_id', $categoryId);
        }
        return $q->orderByDesc('published_at')->limit(4)->get(['title', 'slug', 'excerpt'])->map(fn (Article $a) => [
            'title'   => (string) $a->title,
            'slug'    => (string) $a->slug,
            'excerpt' => Str::limit(trim((string) ($a->excerpt ?? '')), 80),
        ])->all();
    }

    private function loadRelatedSites(): array
    {
        return NavSite::query()
            ->where('is_recommended', true)
            ->orderBy('sort_order')
            ->limit(6)
            ->get(['id', 'name', 'url', 'description'])
            ->map(fn (NavSite $s) => [
                'id'          => (int) $s->id,
                'name'        => $s->name,
                'url'         => $s->url,
                'description' => (string) ($s->description ?? ''),
            ])
            ->all();
    }

    private function legalCopy(): array
    {
        return [
            'about' => [
                'title' => '关于我们',
                'description' => '玄猫Web3 是面向中文用户的 Web3 导航与资讯站点，整理交易所、DeFi、钱包与行业文章。',
                'paragraphs' => [
                    '玄猫Web3（xuaweb3.com）提供 Web3 项目导航、项目资料与行业资讯，帮助读者更快找到交易所、DeFi、钱包、L2 与安全工具。',
                    '导航条目由编辑人工整理并持续更新；资讯内容包含编辑撰稿与经审核的 AI 辅助稿，发布前需核对事实、标题与摘要。',
                    '本站不提供投资建议，亦不托管数字资产。访问外部协议或交易所前，请自行核验域名与合约地址。',
                ],
            ],
            'contact' => [
                'title' => '联系我们',
                'description' => '通过邮箱联系玄猫Web3 编辑团队，反馈导航纠错、合作或内容问题。',
                'paragraphs' => [
                    '内容纠错、导航更新或合作请发送邮件至 hello@xuaweb3.com，并注明页面 URL 与具体问题。',
                    '我们通常在 5 个工作日内回复。请勿在邮件中发送助记词、私钥或验证码。',
                    '如需下架或版权沟通，请使用相同邮箱并附权属说明。',
                ],
            ],
            'privacy' => [
                'title' => '隐私政策',
                'description' => '玄猫Web3 隐私政策：说明访问日志、Cookie 与第三方统计的使用范围。',
                'paragraphs' => [
                    '访问本站时，服务器可能记录 IP、User-Agent 与请求路径，用于故障排查、防滥用与访问统计。',
                    '本地收藏/访问次数保存在你的浏览器 localStorage，不会上传到我们的服务器。',
                    '我们可能使用匿名化流量统计。本站不出售个人数据。如使用 Cloudflare 等 CDN，其日志政策以其官网为准。',
                    '外链跳转至第三方站点后，适用该站自己的隐私政策。',
                ],
            ],
            'terms' => [
                'title' => '使用条款',
                'description' => '玄猫Web3 使用条款：内容仅供参考，不构成投资建议，外链可能含推广关系。',
                'paragraphs' => [
                    '本站内容仅供信息参考，不构成投资、法律或税务建议。数字资产存在损失本金的风险。',
                    '部分外链可能包含推荐/联盟参数。我们会在相关页面标明推广披露；你仍应独立判断。',
                    '禁止利用本站从事非法活动、批量抓取未授权数据或干扰服务。我们可限制滥用访问。',
                    '本条款自发布之日起生效，更新后以本页最新文本为准。',
                ],
            ],
        ];
    }
}
