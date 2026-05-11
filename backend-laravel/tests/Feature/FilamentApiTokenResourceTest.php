<?php

use App\Filament\Resources\ApiTokens\Pages\CreateApiToken;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\Admin;
use App\Models\ApiToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use function Pest\Livewire\livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->admin = Admin::firstOrCreate(
        ['username' => 'pest-admin'],
        ['password' => 'test', 'role' => 'super_admin', 'status' => 'active']
    );
    $this->actingAs($this->admin);
});

it('lists api tokens page renders', function () {
    livewire(ListApiTokens::class)->assertOk();
});

it('creates token via Filament page and stores only hash', function () {
    $name = 'CI-Test-' . uniqid();

    livewire(CreateApiToken::class)
        ->set('data.name', $name)
        ->set('data.scopes', ['catalog:read', 'tasks:write'])
        ->set('data.status', 'active')
        ->call('create')
        ->assertHasNoFormErrors();

    $token = ApiToken::where('name', $name)->firstOrFail();
    // 数据库里 token_hash 是 SHA256 hex（64 字符）
    expect($token->token_hash)->toBeString()->toHaveLength(64);
    expect($token->scopes)->toBe(['catalog:read', 'tasks:write']);
    expect($token->created_by_admin_id)->toBe($this->admin->id);
    expect($token->status)->toBe('active');
});

it('issue() returns plaintext exactly once with prefix', function () {
    [$plain, $token] = ApiToken::issue('Direct', ['x:y']);
    expect($plain)->toStartWith('xua_');
    expect(strlen($plain))->toBeGreaterThan(40);
    expect($token->token_hash)->toBe(hash('sha256', $plain));
});

it('revoke() flips status without deleting', function () {
    [, $token] = ApiToken::issue('ToRevoke', ['x']);
    expect($token->isUsable())->toBeTrue();

    $token->revoke();

    expect($token->fresh()->status)->toBe('revoked');
    expect($token->fresh()->isUsable())->toBeFalse();
});

it('isExpired honors expires_at', function () {
    [, $past] = ApiToken::issue('Past', ['x'], null, now()->subHour());
    [, $future] = ApiToken::issue('Future', ['x'], null, now()->addHour());
    [, $never] = ApiToken::issue('Never', ['x']);

    expect($past->isExpired())->toBeTrue();
    expect($future->isExpired())->toBeFalse();
    expect($never->isExpired())->toBeFalse();
});
