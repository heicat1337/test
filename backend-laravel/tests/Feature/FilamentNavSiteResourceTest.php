<?php

use App\Filament\Resources\NavSites\Pages\CreateNavSite;
use App\Filament\Resources\NavSites\Pages\EditNavSite;
use App\Filament\Resources\NavSites\Pages\ListNavSites;
use App\Models\Admin;
use App\Models\NavCategory;
use App\Models\NavSite;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use function Pest\Livewire\livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->actingAs(
        Admin::firstOrCreate(
            ['username' => 'pest-admin'],
            [
                'password'     => bcrypt('test'),
                'email'        => 'pest-admin@xuaweb3.local',
                'display_name' => 'Pest Admin',
                'role'         => 'super_admin',
                'status'       => 'active',
            ]
        )
    );

    $this->category = NavCategory::create([
        'name'       => 'PESTCAT_' . uniqid(),
        'slug'       => 'pest-cat-' . uniqid(),
        'icon'       => '🧪',
        'sort_order' => 9999,
    ]);
});

/**
 * 注：Filament 4 + Livewire 3 这组合下，pest-plugin-livewire 的 fillForm()
 * 在某些场景下不会把 state 透传到 livewire data 数组里。这里改用
 * Livewire 原生 set('data.field', $value) 直填，行为可靠。
 */

describe('ListNavSites page', function () {
    it('renders for admin', function () {
        livewire(ListNavSites::class)->assertOk();
    });

    it('shows existing site row', function () {
        // 用 sort_order=0 让它在默认 sort 下排在前面，避免分页隐藏；
        // 用名字唯一前缀方便直接 assertSeeText 命中。
        $name = 'PestListed' . uniqid();
        NavSite::create([
            'category_id' => $this->category->id,
            'name'        => $name,
            'url'         => 'https://list.test',
            'sort_order'  => 0,
        ]);
        livewire(ListNavSites::class)
            ->searchTable($name)        // 用 Filament 表格搜索框定位到这条
            ->assertSeeText($name);
    });
});

describe('CreateNavSite form', function () {
    it('creates a site with tags + social_links + rating', function () {
        livewire(CreateNavSite::class)
            ->set('data.category_id', $this->category->id)
            ->set('data.name', 'PestCreated')
            ->set('data.url', 'https://create.test')
            ->set('data.description', 'desc')
            ->set('data.icon', '🆕')
            ->set('data.sort_order', 7)
            ->set('data.is_recommended', true)
            ->set('data.tags', ['phase1', 'pest', 'auto'])
            ->set('data.rating', 4.5)
            ->set('data.social_links', ['twitter' => 'https://x.com/p'])
            ->set('data.screenshot_url', 'https://create.test/shot.png')
            ->call('create')
            ->assertHasNoFormErrors();

        $site = NavSite::where('name', 'PestCreated')->firstOrFail();
        expect($site->tags)->toBe(['phase1', 'pest', 'auto']);
        expect($site->rating)->toBe(4.5);
        expect($site->social_links)->toBe(['twitter' => 'https://x.com/p']);
        expect($site->is_recommended)->toBeTrue();
        expect($site->screenshot_url)->toBe('https://create.test/shot.png');
    });

    it('rejects missing required fields', function () {
        livewire(CreateNavSite::class)
            ->set('data.name', '')
            ->set('data.url', '')
            ->call('create')
            ->assertHasFormErrors(['name', 'url', 'category_id']);
    });

    it('rejects invalid url', function () {
        livewire(CreateNavSite::class)
            ->set('data.category_id', $this->category->id)
            ->set('data.name', 'X')
            ->set('data.url', 'not-a-url')
            ->call('create')
            ->assertHasFormErrors(['url']);
    });
});

describe('EditNavSite form', function () {
    it('loads existing site state into form', function () {
        $site = NavSite::create([
            'category_id'  => $this->category->id,
            'name'         => 'PestEdit',
            'url'          => 'https://edit.test',
            'sort_order'   => 1,
            'tags'         => ['orig1', 'orig2'],
            'social_links' => ['twitter' => 'https://x.com/o'],
            'rating'       => 3.3,
        ]);

        $t = livewire(EditNavSite::class, ['record' => $site->getKey()]);
        $data = $t->get('data');

        // 简单字段直接对照
        expect($data['name'])->toBe('PestEdit');
        expect($data['url'])->toBe('https://edit.test');
        expect($data['tags'])->toBe(['orig1', 'orig2']);
        expect((float) $data['rating'])->toBe(3.3);

        // KeyValue 组件在 form 表态是 list-of-kv 而不是 assoc array：
        //   [['key' => 'twitter', 'value' => 'https://x.com/o']]
        // 这是 Filament 的内部约定，提交时会还原成 assoc array。这里验证内容存在即可。
        expect($data['social_links'])->toBeArray();
        $flat = collect($data['social_links'])->mapWithKeys(
            fn ($r) => is_array($r) && isset($r['key'], $r['value'])
                ? [$r['key'] => $r['value']]
                : []
        )->all();
        expect($flat)->toBe(['twitter' => 'https://x.com/o']);
    });

    it('updates tags / social_links / rating', function () {
        $site = NavSite::create([
            'category_id'  => $this->category->id,
            'name'         => 'PestEdit2',
            'url'          => 'https://edit2.test',
            'sort_order'   => 1,
            'tags'         => ['old'],
            'social_links' => [],
        ]);

        livewire(EditNavSite::class, ['record' => $site->getKey()])
            ->set('data.tags', ['new1', 'new2'])
            ->set('data.social_links', ['discord' => 'https://discord.gg/x'])
            ->set('data.rating', 4.9)
            ->call('save')
            ->assertHasNoFormErrors();

        $site->refresh();
        expect($site->tags)->toBe(['new1', 'new2']);
        expect($site->social_links)->toBe(['discord' => 'https://discord.gg/x']);
        expect($site->rating)->toBe(4.9);
    });

    it('writing via Filament also bumps shared cache version', function () {
        $site = NavSite::create([
            'category_id' => $this->category->id,
            'name' => 'CacheBump', 'url' => 'https://cb.test',
            'sort_order' => 1,
        ]);
        $before = (int) DB::selectOne('SELECT version FROM nav_cache_version WHERE id=1')->version;

        livewire(EditNavSite::class, ['record' => $site->getKey()])
            ->set('data.rating', 4.0)
            ->call('save')
            ->assertHasNoFormErrors();

        $after = (int) DB::selectOne('SELECT version FROM nav_cache_version WHERE id=1')->version;
        expect($after)->toBeGreaterThan($before);
    });
});
