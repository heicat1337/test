<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use function Pest\Livewire\livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->actingAs(
        Admin::firstOrCreate(
            ['username' => 'pest-admin'],
            ['password' => 'test', 'role' => 'super_admin', 'status' => 'active']
        )
    );

    $this->category = Category::create([
        'name' => 'P3CAT_' . uniqid(),
        'slug' => 'p3-cat-' . uniqid(),
    ]);
    $this->author = Author::create([
        'name' => 'P3Author_' . uniqid(),
    ]);
});

describe('CategoryResource', function () {
    it('list page renders', function () {
        livewire(ListCategories::class)->assertOk();
    });

    it('creates a category', function () {
        $name = 'CatX_' . uniqid();
        $slug = 'cat-x-' . uniqid();
        livewire(CreateCategory::class)
            ->set('data.name', $name)
            ->set('data.slug', $slug)
            ->set('data.description', '说明')
            ->set('data.sort_order', 5)
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Category::where('slug', $slug)->value('name'))->toBe($name);
    });

    it('rejects duplicate slug', function () {
        $slug = 'dup-' . uniqid();
        Category::create(['name' => 'Existing', 'slug' => $slug]);

        livewire(CreateCategory::class)
            ->set('data.name', 'Other')
            ->set('data.slug', $slug)
            ->call('create')
            ->assertHasFormErrors(['slug']);
    });
});

describe('AuthorResource', function () {
    it('list page renders', function () {
        livewire(ListAuthors::class)->assertOk();
    });

    it('creates an author with social links', function () {
        $name = 'AuthorX_' . uniqid();
        livewire(CreateAuthor::class)
            ->set('data.name', $name)
            ->set('data.email', 'a@x.test')
            ->set('data.bio', 'bio')
            ->set('data.website', 'https://example.com')
            ->set('data.social_links', ['twitter' => 'https://x.com/me'])
            ->call('create')
            ->assertHasNoFormErrors();

        $au = Author::where('name', $name)->firstOrFail();
        // social_links 默认 array cast，存的是 JSON 字符串
        expect($au->social_links)->toBe(['twitter' => 'https://x.com/me']);
    });
});

describe('ArticleResource', function () {
    it('list page renders', function () {
        livewire(ListArticles::class)->assertOk();
    });

    it('creates an article with keywords array', function () {
        $title = 'ArtX_' . uniqid();
        $slug  = 'art-x-' . uniqid();

        livewire(CreateArticle::class)
            ->set('data.title', $title)
            ->set('data.slug', $slug)
            ->set('data.content', '正文')
            ->set('data.excerpt', '摘要')
            ->set('data.category_id', $this->category->id)
            ->set('data.author_id', $this->author->id)
            ->set('data.status', 'draft')
            ->set('data.review_status', 'pending')
            ->set('data.keywords', ['web3', 'tutorial'])
            ->call('create')
            ->assertHasNoFormErrors();

        $a = Article::where('slug', $slug)->firstOrFail();
        expect($a->title)->toBe($title);
        expect($a->keywords)->toBe(['web3', 'tutorial']);
        expect($a->status)->toBe('draft');
    });

    it('rejects missing required fields', function () {
        livewire(CreateArticle::class)
            ->set('data.title', '')
            ->call('create')
            ->assertHasFormErrors(['title', 'slug', 'content', 'category_id', 'author_id']);
    });

    it('edit form loads existing keywords array', function () {
        $a = Article::create([
            'title' => 'Edit', 'slug' => 'edit-' . uniqid(),
            'content' => 'x', 'category_id' => $this->category->id, 'author_id' => $this->author->id,
            'status' => 'draft', 'review_status' => 'pending',
            'keywords' => ['kw1', 'kw2'],
        ]);

        $t = livewire(EditArticle::class, ['record' => $a->getKey()]);
        expect($t->get('data')['keywords'])->toBe(['kw1', 'kw2']);
    });

    it('updates status to published sets published_at via mutator', function () {
        $a = Article::create([
            'title' => 'Pub', 'slug' => 'pub-' . uniqid(),
            'content' => 'x', 'category_id' => $this->category->id, 'author_id' => $this->author->id,
            'status' => 'draft', 'review_status' => 'pending',
        ]);

        livewire(EditArticle::class, ['record' => $a->getKey()])
            ->set('data.status', 'published')
            ->set('data.published_at', now()->toDateTimeString())
            ->call('save')
            ->assertHasNoFormErrors();

        $a->refresh();
        expect($a->status)->toBe('published');
        expect($a->published_at)->not->toBeNull();
    });
});
