<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\NavCategory;
use App\Models\NavSite;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function sitemapArticleCategory(): Category
{
    return Category::create([
        'name' => 'ArticleCat_' . uniqid(),
        'slug' => 'article-cat-' . uniqid(),
        'description' => '',
        'sort_order' => 0,
    ]);
}

function sitemapAuthor(): Author
{
    return Author::create([
        'name' => 'Author_' . uniqid(),
        'email' => 'author-' . uniqid() . '@example.test',
        'bio' => '',
        'avatar' => '',
    ]);
}

describe('GET /sitemap.xml', function () {
    it('renders XML with static urls, nav categories, and published articles', function () {
        config(['app.url' => 'https://xuaweb3.test']);

        $navCat = NavCategory::create([
            'name' => 'NavCat_' . uniqid(),
            'slug' => 'nav-' . uniqid(),
            'icon' => '🐱',
            'sort_order' => 0,
        ]);
        NavSite::create([
            'category_id' => $navCat->id,
            'name' => 'Site_' . uniqid(),
            'url' => 'https://example.test',
            'description' => 'desc',
            'icon' => '🧭',
            'sort_order' => 1,
        ]);

        $articleCat = sitemapArticleCategory();
        $author = sitemapAuthor();
        $published = Article::create([
            'title' => 'Published Sitemap Article',
            'slug' => 'published-sitemap-' . uniqid(),
            'content' => 'content',
            'category_id' => $articleCat->id,
            'author_id' => $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);
        $draft = Article::create([
            'title' => 'Draft Sitemap Article',
            'slug' => 'draft-sitemap-' . uniqid(),
            'content' => 'content',
            'category_id' => $articleCat->id,
            'author_id' => $author->id,
            'status' => 'draft',
        ]);

        $r = $this->get('/sitemap.xml');

        $r->assertOk();
        expect($r->headers->get('Content-Type'))->toContain('application/xml');
        expect($r->headers->get('Cache-Control'))->toContain('max-age=600');

        $r->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
            ->assertSee('<loc>https://xuaweb3.test/</loc>', false)
            ->assertSee('<loc>https://xuaweb3.test/articles</loc>', false)
            ->assertSee('<loc>https://xuaweb3.test/c/' . $navCat->slug . '</loc>', false)
            ->assertSee('<loc>https://xuaweb3.test/articles/' . $published->slug . '</loc>', false)
            ->assertDontSee($draft->slug);
    });
});
