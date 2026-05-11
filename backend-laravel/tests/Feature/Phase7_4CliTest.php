<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Title;
use App\Models\TitleLibrary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

describe('geoflow:auto-publish', function () {
    it('publishes draft articles whose review_status is approved', function () {
        $cat = Category::firstOrCreate(['slug' => 'gen-' . uniqid()], ['name' => 'G']);
        $au  = Author::firstOrCreate(['name' => 'A_' . uniqid()]);

        $a = Article::create([
            'title' => 'Auto_' . uniqid(), 'slug' => 'ap-' . uniqid(),
            'content' => 'x', 'category_id' => $cat->id, 'author_id' => $au->id,
            'status' => 'draft', 'review_status' => 'approved',
        ]);

        $this->artisan('geoflow:auto-publish')->assertSuccessful();

        $fresh = $a->fresh();
        expect($fresh->status)->toBe('published');
        expect($fresh->published_at)->not->toBeNull();
    });

    it('skips draft+pending (not approved)', function () {
        $cat = Category::firstOrCreate(['slug' => 'gen2-' . uniqid()], ['name' => 'G']);
        $au  = Author::firstOrCreate(['name' => 'A2_' . uniqid()]);
        $a = Article::create([
            'title' => 'Pend_' . uniqid(), 'slug' => 'pn-' . uniqid(),
            'content' => 'x', 'category_id' => $cat->id, 'author_id' => $au->id,
            'status' => 'draft', 'review_status' => 'pending',
        ]);

        $this->artisan('geoflow:auto-publish')->assertSuccessful();
        expect($a->fresh()->status)->toBe('draft');
    });

    it('respects --limit', function () {
        $cat = Category::firstOrCreate(['slug' => 'gen3-' . uniqid()], ['name' => 'G']);
        $au  = Author::firstOrCreate(['name' => 'A3_' . uniqid()]);
        for ($i = 0; $i < 3; $i++) {
            Article::create([
                'title' => 'L_' . uniqid(), 'slug' => 'lim-' . uniqid(),
                'content' => 'x', 'category_id' => $cat->id, 'author_id' => $au->id,
                'status' => 'draft', 'review_status' => 'approved',
            ]);
        }

        $this->artisan('geoflow:auto-publish', ['--limit' => 2])->assertSuccessful();
        // 仅发布 2 篇，剩 1 篇仍 draft
        expect(Article::where('status', 'draft')->where('review_status', 'approved')->count())->toBe(1);
    });
});

describe('geoflow:health-check', function () {
    it('runs successfully and prints stats', function () {
        $this->artisan('geoflow:health-check')->assertSuccessful();
    });
});

describe('geoflow:db-maintenance', function () {
    it('check action outputs current_database + table_count', function () {
        $this->artisan('geoflow:db-maintenance', ['action' => 'check'])
            ->expectsOutputToContain('driver: pgsql')
            ->expectsOutputToContain('connection_check: ok')
            ->assertSuccessful();
    });

    it('cleanup action runs without crash', function () {
        $this->artisan('geoflow:db-maintenance', ['action' => 'cleanup'])->assertSuccessful();
    });

    it('rejects unknown action', function () {
        $this->artisan('geoflow:db-maintenance', ['action' => 'bogus'])->assertFailed();
    });
});

describe('geoflow:rss-fetch', function () {
    it('dry-run does not insert any titles', function () {
        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><rss><channel><item><title>Hello</title><pubDate>' . now()->toRfc2822String() . '</pubDate></item></channel></rss>', 200),
        ]);

        $lib = TitleLibrary::firstOrCreate(['name' => 'Web3 RSS 自动抓取']);
        $before = Title::where('library_id', $lib->id)->count();

        $this->artisan('geoflow:rss-fetch', ['--library-id' => $lib->id, '--dry-run' => true])->assertSuccessful();

        expect(Title::where('library_id', $lib->id)->count())->toBe($before);
    });

    it('inserts fresh items into the specified library', function () {
        $freshDate = now()->subMinutes(10)->toRfc2822String();
        Http::fake([
            '*' => Http::response(
                '<?xml version="1.0"?><rss><channel>'
                . '<item><title>Fresh Title A</title><pubDate>' . $freshDate . '</pubDate><description>body</description></item>'
                . '<item><title>Fresh Title B</title><pubDate>' . $freshDate . '</pubDate><description>body</description></item>'
                . '</channel></rss>',
                200
            ),
        ]);

        $lib = TitleLibrary::create(['name' => 'Test_' . uniqid()]);
        $this->artisan('geoflow:rss-fetch', ['--library-id' => $lib->id])->assertSuccessful();

        // 8 个 RSS 源都返回同样 2 条 → 第一源插 2 条，后续 7 源全部命中去重
        $count = Title::where('library_id', $lib->id)->count();
        expect($count)->toBe(2);
        expect(Title::where('library_id', $lib->id)->pluck('title')->all())
            ->toContain('Fresh Title A', 'Fresh Title B');
    });

    it('rejects stale items outside fresh window', function () {
        $oldDate = now()->subDays(5)->toRfc2822String();
        Http::fake([
            '*' => Http::response(
                '<?xml version="1.0"?><rss><channel>'
                . '<item><title>Old News</title><pubDate>' . $oldDate . '</pubDate></item>'
                . '</channel></rss>',
                200
            ),
        ]);

        $lib = TitleLibrary::create(['name' => 'Test2_' . uniqid()]);
        $this->artisan('geoflow:rss-fetch', ['--library-id' => $lib->id])->assertSuccessful();

        expect(Title::where('library_id', $lib->id)->count())->toBe(0);
    });
});
