<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\NavCategory;
use App\Models\NavSite;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Cache::flush();   // 清掉 seo.categories / seo.site.* 缓存
});

function seoCat(?string $slug = null): NavCategory
{
    return NavCategory::create([
        'name' => 'CAT_' . uniqid(),
        'slug' => $slug ?? ('cat-' . uniqid()),
        'icon' => '🔥',
        'sort_order' => 0,
    ]);
}

function seoSite(int $catId, array $overrides = []): NavSite
{
    return NavSite::create(array_merge([
        'category_id' => $catId,
        'name'   => 'Site_' . uniqid(),
        'url'    => 'https://example.test/' . uniqid(),
        'description' => 'desc',
        'icon'   => '🦄',
        'sort_order' => 1,
    ], $overrides));
}

function seoArticleCategory(): Category
{
    return Category::create([
        'name' => 'ArticleCat_' . uniqid(),
        'slug' => 'article-cat-' . uniqid(),
        'description' => '',
        'sort_order' => 0,
    ]);
}

function seoAuthor(): Author
{
    return Author::create([
        'name' => 'Author_' . uniqid(),
        'email' => 'author-' . uniqid() . '@example.test',
        'bio' => '',
        'avatar' => '',
    ]);
}

function seoArticle(array $overrides = []): Article
{
    $category = seoArticleCategory();
    $author = seoAuthor();

    return Article::create(array_merge([
        'title'        => 'Test_' . uniqid(),
        'slug'         => 'art-' . uniqid(),
        'content'      => '<p>正文段落内容</p>',
        'category_id'  => $category->id,
        'author_id'    => $author->id,
        'status'       => 'published',
        'published_at' => now(),
    ], $overrides));
}

describe('GET /__seo/home', function () {
    it('renders 200 HTML with JSON-LD blocks', function () {
        $cat = seoCat();
        seoSite($cat->id, ['name' => 'TestSiteA', 'description' => '描述 A']);

        $r = $this->get('/__seo/home');
        $r->assertOk()
            ->assertSee('TestSiteA')
            ->assertSee('描述 A')
            ->assertSee('application/ld+json', false)
            ->assertSee('@context', false)
            ->assertSee('CollectionPage', false);

        expect($r->headers->get('X-Robots-Tag'))->toBe('index,follow');
        expect($r->headers->get('Cache-Control'))->toContain('max-age=300');
    });

    it('shows site count in heading', function () {
        Cache::flush();
        $r = $this->get('/__seo/home');
        $r->assertOk()
            ->assertSeeText('个分类');
    });
});

describe('GET /__seo/category/{slug}', function () {
    it('renders the category page with its sites', function () {
        Cache::flush();
        $cat = seoCat('cat-test-' . uniqid());
        seoSite($cat->id, ['name' => 'MyCatSite']);

        $r = $this->get('/__seo/category/' . $cat->slug);
        $r->assertOk()
            ->assertSee($cat->name)
            ->assertSee('MyCatSite')
            ->assertSee('BreadcrumbList', false);
    });

    it('returns 404 for unknown slug', function () {
        $r = $this->get('/__seo/category/never-exists-' . uniqid());
        $r->assertStatus(404)
            ->assertSee('未找到分类');
    });
});

describe('GET /__seo/project/{id}', function () {
    it('renders the project page with full data', function () {
        Cache::flush();
        $cat = seoCat();
        $site = seoSite($cat->id, [
            'name'   => 'ProjectX',
            'rating' => 4.5,
            'tags'   => ['defi', 'web3'],
            'is_recommended' => true,
        ]);

        $r = $this->get("/__seo/project/{$site->id}");
        $r->assertOk()
            ->assertSee('ProjectX')
            ->assertSee('★ 推荐')
            ->assertSee('4.5 / 5')
            ->assertSee('#defi')
            ->assertSee('#web3')
            ->assertSee('AggregateRating', false);   // JSON-LD
    });

    it('returns 404 for unknown id', function () {
        $r = $this->get('/__seo/project/99999999');
        $r->assertStatus(404)
            ->assertSee('未找到项目');
    });

    it('respects pure number id route constraint', function () {
        $r = $this->get('/__seo/project/abc');
        $r->assertStatus(404);
    });
});

describe('GET /__seo/articles', function () {
    it('renders latest published articles with self-referencing canonical', function () {
        Cache::flush();
        $a = seoArticle([
            'slug' => 'latest-web3-news',
            'title' => 'Web3 最新资讯',
            'excerpt' => '这是一篇已发布文章摘要',
        ]);
        seoArticle([
            'slug' => 'draft-list-hidden',
            'title' => '列表不应出现的草稿',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $r = $this->get('/__seo/articles');
        $r->assertOk()
            ->assertSee('Web3 文章')
            ->assertSee('Web3 最新资讯')
            ->assertSee('这是一篇已发布文章摘要')
            ->assertSee('/articles/' . $a->slug, false)
            ->assertSee('rel="canonical" href="' . config('app.url') . '/articles"', false)
            ->assertSee('CollectionPage', false)
            ->assertDontSee('列表不应出现的草稿');

        expect($r->headers->get('X-Robots-Tag'))->toBe('index,follow');
    });
});

describe('GET /__seo/article/{slug}', function () {
    it('renders a published article with self-referencing canonical', function () {
        Cache::flush();
        $a = seoArticle([
            'slug'             => 'my-web3-guide',
            'title'            => '什么是 Layer2',
            'content'          => '<p>Layer2 是扩容方案。</p>',
            'meta_description' => '一文读懂 Layer2 扩容',
        ]);

        $r = $this->get('/__seo/article/' . $a->slug);
        $r->assertOk()
            ->assertSee('什么是 Layer2')
            ->assertSee('Layer2 是扩容方案', false)
            ->assertSee('一文读懂 Layer2 扩容')
            ->assertSee('"@type":"Article"', false)
            ->assertSee('BreadcrumbList', false);

        // 核心验收：canonical 必须自指文章，绝不是首页（Nova #73）。
        $r->assertSee('rel="canonical" href="' . config('app.url') . '/articles/my-web3-guide"', false);
        expect($r->headers->get('X-Robots-Tag'))->toBe('index,follow');
    });

    it('emits og:image / twitter:image (Iris #67)', function () {
        Cache::flush();
        $a = seoArticle(['featured_image' => 'https://cdn.test/x.png']);

        $r = $this->get('/__seo/article/' . $a->slug);
        $r->assertOk()
            ->assertSee('property="og:image" content="https://cdn.test/x.png"', false)
            ->assertSee('name="twitter:image" content="https://cdn.test/x.png"', false)
            ->assertSee('property="og:type" content="article"', false);
    });

    it('falls back description to excerpt then content when meta empty', function () {
        Cache::flush();
        $a = seoArticle([
            'meta_description' => '',
            'excerpt'          => '这是摘要文字',
            'content'          => '<p>这是正文</p>',
        ]);

        $r = $this->get('/__seo/article/' . $a->slug);
        $r->assertOk()->assertSee('这是摘要文字');
    });

    it('keeps original keyword in meta description', function () {
        Cache::flush();
        $a = seoArticle([
            'original_keyword' => '比特币现货ETF',
            'meta_description' => '资金流入推动市场关注度上升',
            'excerpt'          => '',
            'content'          => '<p>正文</p>',
        ]);

        $r = $this->get('/__seo/article/' . $a->slug);
        $r->assertOk()->assertSee('比特币现货ETF：资金流入推动市场关注度上升');
    });

    it('returns 404 + noindex for unpublished or missing article', function () {
        Cache::flush();
        $draft = seoArticle(['status' => 'draft', 'published_at' => null]);

        $r = $this->get('/__seo/article/' . $draft->slug);
        $r->assertStatus(404)->assertSee('未找到文章');
        expect($r->headers->get('X-Robots-Tag'))->toBe('noindex,follow');

        $this->get('/__seo/article/never-exists-' . uniqid())->assertStatus(404);
    });
});
