<?php

use App\Filament\Resources\SensitiveWords\Pages\CreateSensitiveWord;
use App\Filament\Resources\SensitiveWords\Pages\ListSensitiveWords;
use App\Filament\Resources\SiteSettings\Pages\CreateSiteSetting;
use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Filament\Resources\SiteSettings\Pages\ListSiteSettings;
use App\Filament\Resources\AdminActivityLogs\Pages\ListAdminActivityLogs;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\SensitiveWord;
use App\Models\SiteSetting;
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
});

describe('SiteSettingResource', function () {
    it('list page renders', function () {
        livewire(ListSiteSettings::class)->assertOk();
    });

    it('creates a setting', function () {
        $key = 'pest_site_key_' . uniqid();
        livewire(CreateSiteSetting::class)
            ->set('data.setting_key', $key)
            ->set('data.setting_value', 'pest value')
            ->call('create')
            ->assertHasNoFormErrors();

        expect(SiteSetting::where('setting_key', $key)->value('setting_value'))->toBe('pest value');
    });

    it('rejects duplicate key on create', function () {
        $key = 'dup_' . uniqid();
        SiteSetting::create(['setting_key' => $key, 'setting_value' => 'x']);

        livewire(CreateSiteSetting::class)
            ->set('data.setting_key', $key)
            ->set('data.setting_value', 'y')
            ->call('create')
            ->assertHasFormErrors(['setting_key']);
    });

    it('value() / put() helpers cache aware', function () {
        $key = 'helper_' . uniqid();
        expect(SiteSetting::value($key, 'fallback'))->toBe('fallback');

        SiteSetting::put($key, 'set');
        expect(SiteSetting::value($key))->toBe('set');

        SiteSetting::put($key, 'updated');
        expect(SiteSetting::value($key))->toBe('updated');
    });
});

describe('SensitiveWordResource', function () {
    it('list page renders', function () {
        livewire(ListSensitiveWords::class)->assertOk();
    });

    it('creates a word', function () {
        $w = 'pestword_' . uniqid();
        livewire(CreateSensitiveWord::class)
            ->set('data.word', $w)
            ->call('create')
            ->assertHasNoFormErrors();

        expect(SensitiveWord::where('word', $w)->exists())->toBeTrue();
    });

    it('rejects duplicate word', function () {
        $w = 'dup_w_' . uniqid();
        SensitiveWord::create(['word' => $w]);

        livewire(CreateSensitiveWord::class)
            ->set('data.word', $w)
            ->call('create')
            ->assertHasFormErrors(['word']);
    });
});

describe('AdminActivityLogResource (read-only)', function () {
    it('list page renders even with no records', function () {
        livewire(ListAdminActivityLogs::class)->assertOk();
    });

    it('displays an existing log row', function () {
        $log = AdminActivityLog::create([
            'admin_id'       => null,
            'admin_username' => 'pest-admin',
            'admin_role'     => 'admin',
            'action'         => 'pest_action',
            'request_method' => 'POST',
            'page'           => '/test',
            'ip_address'     => '127.0.0.1',
        ]);

        livewire(ListAdminActivityLogs::class)
            ->assertCanSeeTableRecords([$log]);
    });
});
