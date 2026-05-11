<?php

use App\Models\NavCategory;
use App\Models\NavSite;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // 共用生产 PG 容器（同一张表）但事务回滚隔离
    $this->category = NavCategory::create([
        'name'       => 'TEST_CAT_' . uniqid(),
        'slug'       => 'test-cat-' . uniqid(),
        'icon'       => '🧪',
        'sort_order' => 9999,
    ]);
});

describe('GET /api/v1/nav/categories', function () {
    it('returns success envelope with categories array', function () {
        $r = $this->getJson('/api/v1/nav/categories');
        $r->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'icon', 'sort_order', 'sites'],
                ],
                'error',
                'meta' => ['request_id', 'timestamp'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('error', null);
    });

    it('includes nested sites with the new Phase 0 fields', function () {
        $site = NavSite::create([
            'category_id'    => $this->category->id,
            'name'           => 'TestSite',
            'url'            => 'https://example.test',
            'description'    => 'desc',
            'icon'           => '🔧',
            'sort_order'     => 1,
            'is_recommended' => true,
            'tags'           => ['phase0', 'verify'],
            'rating'         => 4.2,
            'social_links'   => ['twitter' => 'https://x.com/t'],
            'screenshot_url' => 'https://example.test/shot.png',
        ]);

        $r = $this->getJson('/api/v1/nav/categories');
        $r->assertOk();

        $cats = collect($r->json('data'));
        $cat = $cats->firstWhere('id', $this->category->id);
        expect($cat)->not->toBeNull();

        $payload = collect($cat['sites'])->firstWhere('id', $site->id);
        expect($payload)->toMatchArray([
            'id'             => $site->id,
            'name'           => 'TestSite',
            'url'            => 'https://example.test',
            'tags'           => ['phase0', 'verify'],
            'rating'         => 4.2,
            'is_recommended' => true,
            'social_links'   => ['twitter' => 'https://x.com/t'],
            'screenshot_url' => 'https://example.test/shot.png',
        ]);
    });
});

describe('GET /api/v1/nav/sites', function () {
    it('lists all sites without filter', function () {
        NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'A', 'url' => 'https://a.test',
            'sort_order' => 1,
        ]);
        $r = $this->getJson('/api/v1/nav/sites');
        $r->assertOk();
        expect($r->json('data'))->toBeArray()->not->toBeEmpty();
    });

    it('filters by category_id', function () {
        $s = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'OnlyMine', 'url' => 'https://b.test',
            'sort_order' => 1,
        ]);
        $r = $this->getJson('/api/v1/nav/sites?category_id=' . $this->category->id);
        $r->assertOk();
        $names = collect($r->json('data'))->pluck('name')->all();
        expect($names)->toContain('OnlyMine');
    });
});

describe('GET /api/v1/nav/sites/{id}', function () {
    it('returns site with nested category', function () {
        $s = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'Detail', 'url' => 'https://d.test',
            'sort_order' => 1,
            'tags' => ['x'],
        ]);
        $r = $this->getJson("/api/v1/nav/sites/{$s->id}");
        $r->assertOk()
            ->assertJsonPath('data.id', $s->id)
            ->assertJsonPath('data.name', 'Detail')
            ->assertJsonPath('data.tags', ['x'])
            ->assertJsonPath('data.category.id', $this->category->id)
            ->assertJsonPath('data.category.slug', $this->category->slug);
    });

    it('returns 404 for non-existing id', function () {
        $r = $this->getJson('/api/v1/nav/sites/999999999');
        $r->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'not_found');
    });
});

describe('GET /api/v1/nav/recommended', function () {
    it('returns only is_recommended sites', function () {
        $rec = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'Featured', 'url' => 'https://f.test',
            'sort_order' => 1,
            'is_recommended' => true,
        ]);
        NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'Regular', 'url' => 'https://r.test',
            'sort_order' => 2,
            'is_recommended' => false,
        ]);
        $r = $this->getJson('/api/v1/nav/recommended');
        $r->assertOk();
        $names = collect($r->json('data'))->pluck('name')->all();
        expect($names)->toContain('Featured');
        expect($names)->not->toContain('Regular');
    });
});

describe('字段格式与老 backend 一致', function () {
    it('rating 在响应中是 number（与老 backend 一致：JSON 不区分 int/float）', function () {
        // 整数 rating
        $s0 = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'X0', 'url' => 'https://x0.test',
            'sort_order' => 1, 'rating' => 0,
        ]);
        $r0 = $this->getJson("/api/v1/nav/sites/{$s0->id}");
        expect($r0->json('data.rating'))->toBeNumeric()->toBe(0);

        // 小数 rating 保留精度
        $s1 = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'X1', 'url' => 'https://x1.test',
            'sort_order' => 1, 'rating' => 4.6,
        ]);
        $r1 = $this->getJson("/api/v1/nav/sites/{$s1->id}");
        expect($r1->json('data.rating'))->toBeNumeric()->toBe(4.6);
    });

    it('social_links 空时输出 [] 而不是 {}', function () {
        $s = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'Y', 'url' => 'https://y.test',
            'sort_order' => 1,
            'social_links' => [],
        ]);
        $r = $this->getJson("/api/v1/nav/sites/{$s->id}");
        // PHP 端 [] 的 JSON 输出是数组字面量
        expect($r->json('data.social_links'))->toBe([]);
        expect(json_encode($r->json('data.social_links')))->toBe('[]');
    });

    it('tags 空时输出 [] 数组', function () {
        $s = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'Z', 'url' => 'https://z.test',
            'sort_order' => 1,
            'tags' => [],
        ]);
        $r = $this->getJson("/api/v1/nav/sites/{$s->id}");
        expect($r->json('data.tags'))->toBe([]);
    });
});

describe('saved hook 触发缓存版本号 bump', function () {
    it('writing a NavSite bumps nav_cache_version', function () {
        $before = (int) DB::selectOne('SELECT version FROM nav_cache_version WHERE id=1')->version;
        NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'BumpTest', 'url' => 'https://bump.test',
            'sort_order' => 1,
        ]);
        $after = (int) DB::selectOne('SELECT version FROM nav_cache_version WHERE id=1')->version;
        expect($after)->toBeGreaterThan($before);
    });
});
