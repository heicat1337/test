<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'TEST_CAT_' . uniqid(),
        'slug' => 'test-cat-' . uniqid(),
    ]);
    $this->author = Author::create([
        'name' => 'TEST_AUTHOR_' . uniqid(),
    ]);
});

function makeArticle(array $overrides = []): Article
{
    return Article::create(array_merge([
        'title'         => 'Test ' . uniqid(),
        'slug'          => 'test-' . uniqid(),
        'content'       => '正文 ' . uniqid(),
        'excerpt'       => '摘要',
        'category_id'   => test()->category->id,
        'author_id'     => test()->author->id,
        'status'        => 'published',
        'review_status' => 'approved',
        'published_at'  => now()->subMinute(),
    ], $overrides));
}

describe('GET /api/v1/articles', function () {
    it('returns published list with pagination structure', function () {
        makeArticle();
        makeArticle();

        $r = $this->getJson('/api/v1/articles?status=published&per_page=10');
        $r->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items'      => ['*' => ['id', 'title', 'slug', 'excerpt', 'category_name', 'author_name', 'view_count', 'is_featured', 'tags', 'published_at']],
                    'pagination' => ['page', 'per_page', 'total', 'total_pages'],
                ],
                'error',
                'meta' => ['request_id', 'timestamp'],
            ])
            ->assertJsonPath('success', true);
    });

    it('hides draft articles by default (status=published)', function () {
        makeArticle(['title' => 'PublishedA', 'slug' => 'pub-a', 'status' => 'published']);
        makeArticle(['title' => 'DraftB',     'slug' => 'draft-b', 'status' => 'draft']);

        $r = $this->getJson('/api/v1/articles?status=published');
        $titles = collect($r->json('data.items'))->pluck('title')->all();
        expect($titles)->toContain('PublishedA');
        expect($titles)->not->toContain('DraftB');
    });

    it('paginates correctly', function () {
        for ($i = 0; $i < 5; $i++) {
            makeArticle();
        }
        $r = $this->getJson('/api/v1/articles?status=published&per_page=2&page=1');
        expect($r->json('data.pagination.per_page'))->toBe(2);
        expect(count($r->json('data.items')))->toBe(2);
        expect((int) $r->json('data.pagination.total'))->toBeGreaterThanOrEqual(5);
    });

    it('orders featured first then by published_at desc', function () {
        $a = makeArticle(['title' => 'NotFeatured', 'slug' => 'nf', 'is_featured' => 0]);
        $b = makeArticle(['title' => 'Featured',    'slug' => 'f',  'is_featured' => 1, 'published_at' => now()->subHour()]);
        $r = $this->getJson('/api/v1/articles?status=published');
        $items = collect($r->json('data.items'))->pluck('title')->all();
        $idxF  = array_search('Featured', $items);
        $idxNF = array_search('NotFeatured', $items);
        expect($idxF)->toBeLessThan($idxNF);
    });
});

describe('GET /api/v1/articles/by-slug/{slug}', function () {
    it('returns full article with nested category and author', function () {
        $a = makeArticle(['title' => 'Detail', 'slug' => 'detail-' . uniqid()]);

        $r = $this->getJson("/api/v1/articles/by-slug/{$a->slug}");
        $r->assertOk()
            ->assertJsonPath('data.title', 'Detail')
            ->assertJsonPath('data.category.id', $this->category->id)
            ->assertJsonPath('data.author.id', $this->author->id)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'slug', 'content', 'excerpt', 'category', 'author', 'tags', 'view_count'],
            ]);
    });

    it('increments view_count on every fetch', function () {
        $a = makeArticle(['slug' => 'views-' . uniqid()]);
        expect($a->view_count)->toBe(0);

        $this->getJson("/api/v1/articles/by-slug/{$a->slug}");
        $this->getJson("/api/v1/articles/by-slug/{$a->slug}");
        $r = $this->getJson("/api/v1/articles/by-slug/{$a->slug}");

        expect($r->json('data.view_count'))->toBe(3);
        expect($a->fresh()->view_count)->toBe(3);
    });

    it('returns 404 for unknown slug', function () {
        $r = $this->getJson('/api/v1/articles/by-slug/does-not-exist-' . uniqid());
        $r->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    });

    it('returns 404 for draft article slug', function () {
        $a = makeArticle(['slug' => 'draft-' . uniqid(), 'status' => 'draft']);
        $r = $this->getJson("/api/v1/articles/by-slug/{$a->slug}");
        $r->assertStatus(404);
    });
});

describe('keywords mutator', function () {
    it('stores array as CSV in PG', function () {
        $a = Article::create([
            'title' => 'KW', 'slug' => 'kw-' . uniqid(),
            'content' => 'x', 'category_id' => $this->category->id, 'author_id' => $this->author->id,
            'status' => 'draft',
            'keywords' => ['alpha', 'beta', 'gamma'],
        ]);

        $raw = (string) \DB::table('articles')->where('id', $a->id)->value('keywords');
        expect($raw)->toBe('alpha,beta,gamma');
    });

    it('reads CSV from PG as array', function () {
        $id = \DB::table('articles')->insertGetId([
            'title' => 'CSV-In', 'slug' => 'csv-in-' . uniqid(),
            'content' => 'x', 'category_id' => $this->category->id, 'author_id' => $this->author->id,
            'status' => 'draft', 'review_status' => 'pending',
            'keywords' => 'one,two , three',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $a = Article::find($id);
        expect($a->keywords)->toBe(['one', 'two', 'three']);
    });

    it('dedupes and trims on save', function () {
        $a = Article::create([
            'title' => 'D', 'slug' => 'd-' . uniqid(),
            'content' => 'x', 'category_id' => $this->category->id, 'author_id' => $this->author->id,
            'status' => 'draft',
            'keywords' => ['a', '  b ', 'a', '  '],
        ]);
        expect($a->keywords)->toBe(['a', 'b']);
    });
});

describe('soft delete', function () {
    it('trashed articles do not appear in default list', function () {
        $a = makeArticle();
        $a->delete();
        $r = $this->getJson('/api/v1/articles?status=published');
        $ids = collect($r->json('data.items'))->pluck('id')->all();
        expect($ids)->not->toContain($a->id);
    });

    it('by-slug returns 404 for trashed', function () {
        $a = makeArticle(['slug' => 'trash-' . uniqid()]);
        $a->delete();
        $r = $this->getJson("/api/v1/articles/by-slug/{$a->slug}");
        $r->assertStatus(404);
    });
});
