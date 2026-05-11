<?php

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
