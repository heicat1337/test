<?php

use App\Exceptions\Api\ApiException;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ArticleReview;
use App\Models\Author;
use App\Models\Category;
use App\Services\Articles\ArticleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->service = new ArticleService();
    $this->category = Category::create(['name' => 'C_' . uniqid(), 'slug' => 'c-' . uniqid()]);
    $this->author = Author::create(['name' => 'A_' . uniqid()]);
    $this->admin = Admin::firstOrCreate(['username' => 'pest-admin'], [
        'password' => 'x', 'role' => 'super_admin', 'status' => 'active',
    ]);
});

describe('createArticle', function () {
    it('creates a draft article with sensible defaults', function () {
        $a = $this->service->createArticle([
            'title'       => 'Hello',
            'content'     => 'Body content here',
            'category_id' => $this->category->id,
            'author_id'   => $this->author->id,
        ]);
        expect($a['title'])->toBe('Hello');
        expect($a['status'])->toBe('draft');
        expect($a['review_status'])->toBe('pending');
        expect($a['published_at'])->toBeNull();
        expect(strlen($a['slug']))->toBe(8);
        expect($a['excerpt'])->toBe('Body content here');   // 自动从 content 截
    });

    it('rejects missing title/content', function () {
        expect(fn () => $this->service->createArticle([
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]))->toThrow(ApiException::class);
    });

    it('rejects unknown category_id', function () {
        expect(fn () => $this->service->createArticle([
            'title' => 'x', 'content' => 'y',
            'category_id' => 9999999, 'author_id' => $this->author->id,
        ]))->toThrow(ApiException::class);
    });

    it('accepts custom slug when available', function () {
        $slug = 'custom-' . uniqid();
        $a = $this->service->createArticle([
            'title' => 'X', 'content' => 'Y',
            'slug'  => $slug,
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        expect($a['slug'])->toBe($slug);
    });

    it('rejects duplicate slug', function () {
        $slug = 'dup-' . uniqid();
        Article::create([
            'title' => 'E', 'slug' => $slug, 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        expect(fn () => $this->service->createArticle([
            'title' => 'X', 'content' => 'Y', 'slug' => $slug,
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]))->toThrow(ApiException::class);
    });
});

describe('updateArticle', function () {
    it('updates title and regenerates slug on title change', function () {
        $orig = $this->service->createArticle([
            'title' => 'Orig', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);

        $updated = $this->service->updateArticle($orig['id'], ['title' => 'Renamed']);
        expect($updated['title'])->toBe('Renamed');
        expect($updated['slug'])->not->toBe($orig['slug']);
    });

    it('keeps slug when only updating non-title fields', function () {
        $orig = $this->service->createArticle([
            'title' => 'X', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        $updated = $this->service->updateArticle($orig['id'], ['excerpt' => 'new-excerpt']);
        expect($updated['slug'])->toBe($orig['slug']);
        expect($updated['excerpt'])->toBe('new-excerpt');
    });

    it('throws 404 for missing article', function () {
        expect(fn () => $this->service->updateArticle(99999999, ['title' => 'X']))
            ->toThrow(ApiException::class);
    });
});

describe('reviewArticle', function () {
    it('approves article and writes audit row', function () {
        $a = $this->service->createArticle([
            'title' => 'R', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);

        $reviewed = $this->service->reviewArticle($a['id'], 'approved', 'looks good', $this->admin->id);

        expect($reviewed['review_status'])->toBe('approved');
        expect(ArticleReview::where('article_id', $a['id'])->count())->toBe(1);
        expect(ArticleReview::where('article_id', $a['id'])->value('review_note'))->toBe('looks good');
    });

    it('rejects bad review_status', function () {
        $a = $this->service->createArticle([
            'title' => 'R', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        expect(fn () => $this->service->reviewArticle($a['id'], 'bogus', '', $this->admin->id))
            ->toThrow(ApiException::class);
    });

    it('auto_approved sets status=published', function () {
        $a = $this->service->createArticle([
            'title' => 'R', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);

        $reviewed = $this->service->reviewArticle($a['id'], 'auto_approved', '', $this->admin->id);
        expect($reviewed['status'])->toBe('published');
        expect($reviewed['published_at'])->not->toBeNull();
    });
});

describe('publishArticle', function () {
    it('publishes an approved article', function () {
        $a = $this->service->createArticle([
            'title' => 'P', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        $this->service->reviewArticle($a['id'], 'approved', '', $this->admin->id);

        $pub = $this->service->publishArticle($a['id']);
        expect($pub['status'])->toBe('published');
        expect($pub['published_at'])->not->toBeNull();
    });

    it('refuses to publish pending article (409)', function () {
        $a = $this->service->createArticle([
            'title' => 'P2', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        try {
            $this->service->publishArticle($a['id']);
            expect(false)->toBeTrue('Should have thrown');
        } catch (ApiException $e) {
            expect($e->getHttpStatus())->toBe(409);
            expect($e->getErrorCode())->toBe('article_not_publishable');
        }
    });
});

describe('trashArticle', function () {
    it('soft-deletes via deleted_at', function () {
        $a = $this->service->createArticle([
            'title' => 'T', 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        $r = $this->service->trashArticle($a['id']);
        expect($r)->toBe(['id' => $a['id'], 'trashed' => true]);

        // 默认查询看不到
        expect(Article::find($a['id']))->toBeNull();
        // withTrashed 可见
        expect(Article::withTrashed()->find($a['id']))->not->toBeNull();
    });

    it('throws 404 on missing article', function () {
        expect(fn () => $this->service->trashArticle(99999999))->toThrow(ApiException::class);
    });
});

describe('listArticles', function () {
    it('paginates and filters by status', function () {
        $this->service->createArticle([
            'title' => 'L1', 'content' => 'c', 'status' => 'draft',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        $this->service->createArticle([
            'title' => 'L2', 'content' => 'c', 'status' => 'draft',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);

        $r = $this->service->listArticles(1, 10, ['status' => 'draft']);
        expect($r['pagination']['total'])->toBeGreaterThanOrEqual(2);
        expect(collect($r['items'])->pluck('title')->all())->toContain('L1', 'L2');
    });

    it('filters by search', function () {
        $unique = 'NEEDLE_' . uniqid();
        $this->service->createArticle([
            'title' => $unique, 'content' => 'c',
            'category_id' => $this->category->id, 'author_id' => $this->author->id,
        ]);
        $r = $this->service->listArticles(1, 10, ['search' => $unique]);
        $titles = collect($r['items'])->pluck('title')->all();
        expect($titles)->toContain($unique);
    });
});
