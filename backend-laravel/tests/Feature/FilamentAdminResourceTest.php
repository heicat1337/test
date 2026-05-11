<?php

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

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

it('lists admins page renders', function () {
    livewire(ListAdmins::class)->assertOk();
});

it('creates admin with hashed password', function () {
    $username = 'new_' . uniqid();

    livewire(CreateAdmin::class)
        ->set('data.username', $username)
        ->set('data.email', $username . '@x.test')
        ->set('data.display_name', '新管理员')
        ->set('data.role', 'admin')
        ->set('data.status', 'active')
        ->set('data.password', 'plaintext-pw-12')
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = Admin::where('username', $username)->firstOrFail();
    expect($admin->password)->not->toBe('plaintext-pw-12');
    expect(Hash::check('plaintext-pw-12', $admin->password))->toBeTrue();
});

it('rejects duplicate username on create', function () {
    Admin::create(['username' => 'dup', 'password' => 'x', 'role' => 'admin', 'status' => 'active']);

    livewire(CreateAdmin::class)
        ->set('data.username', 'dup')
        ->set('data.role', 'admin')
        ->set('data.status', 'active')
        ->set('data.password', 'something')
        ->call('create')
        ->assertHasFormErrors(['username']);
});

it('edit form keeps password unchanged when left blank', function () {
    $admin = Admin::create([
        'username' => 'edit_keep_' . uniqid(),
        'password' => 'orig-pw-99',
        'role'     => 'admin',
        'status'   => 'active',
    ]);
    $origHash = $admin->fresh()->password;

    livewire(EditAdmin::class, ['record' => $admin->getKey()])
        ->set('data.display_name', 'Updated Display')
        ->set('data.password', '')
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->password)->toBe($origHash);
    expect($admin->display_name)->toBe('Updated Display');
});

it('edit form rehashes when password is provided', function () {
    $admin = Admin::create([
        'username' => 'edit_chg_' . uniqid(),
        'password' => 'old-pw',
        'role'     => 'admin',
        'status'   => 'active',
    ]);
    $oldHash = $admin->fresh()->password;

    livewire(EditAdmin::class, ['record' => $admin->getKey()])
        ->set('data.password', 'new-fresh-pw')
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->password)->not->toBe($oldHash);
    expect(Hash::check('new-fresh-pw', $admin->password))->toBeTrue();
});
