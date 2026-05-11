<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;

uses(DatabaseTransactions::class);

it('logs in via admins.username + bcrypt password', function () {
    Admin::create([
        'username'     => 'auth_test',
        'password'     => 'secret123',  // 'hashed' cast 会自动 hash
        'email'        => 'auth@test.local',
        'display_name' => 'Auth Test',
        'role'         => 'admin',
        'status'       => 'active',
    ]);

    expect(Auth::attempt(['username' => 'auth_test', 'password' => 'secret123']))->toBeTrue();
    expect(Auth::user()?->username)->toBe('auth_test');
    Auth::logout();
});

it('rejects wrong password', function () {
    Admin::create([
        'username' => 'auth_wrong',
        'password' => 'right',
        'role'     => 'admin',
        'status'   => 'active',
    ]);
    expect(Auth::attempt(['username' => 'auth_wrong', 'password' => 'wrong']))->toBeFalse();
});

it('canAccessPanel respects status field', function () {
    $active = Admin::create([
        'username' => 'a_active', 'password' => 'x',
        'role' => 'admin', 'status' => 'active',
    ]);
    $inactive = Admin::create([
        'username' => 'a_inactive', 'password' => 'x',
        'role' => 'admin', 'status' => 'inactive',
    ]);

    $panel = \Filament\Facades\Filament::getPanel('admin');
    expect($active->canAccessPanel($panel))->toBeTrue();
    expect($inactive->canAccessPanel($panel))->toBeFalse();
});
