<?php

use App\Casts\PgJsonbObject;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->cast = new PgJsonbObject();
    $this->model = new class extends Model {};
});

describe('set: empty -> object literal {}', function () {
    it('writes empty array as {}', function () {
        expect($this->cast->set($this->model, 'social_links', [], [])['social_links'])->toBe('{}');
    });

    it('writes null as {}', function () {
        expect($this->cast->set($this->model, 'social_links', null, [])['social_links'])->toBe('{}');
    });

    it('writes non-array scalar as {}', function () {
        expect($this->cast->set($this->model, 'social_links', 'foo', [])['social_links'])->toBe('{}');
    });
});

describe('set: assoc array -> JSON object', function () {
    it('encodes typical social map', function () {
        $r = $this->cast->set($this->model, 'social_links', [
            'twitter' => 'https://x.com/foo',
            'discord' => 'https://discord.gg/bar',
        ], []);
        expect($r['social_links'])->toBeString();
        expect(json_decode($r['social_links'], true))->toBe([
            'twitter' => 'https://x.com/foo',
            'discord' => 'https://discord.gg/bar',
        ]);
    });

    it('does not escape slashes', function () {
        $r = $this->cast->set($this->model, 'social_links', ['site' => 'https://x.com/p'], []);
        expect($r['social_links'])->toContain('https://x.com/p');
    });

    it('preserves unicode', function () {
        $r = $this->cast->set($this->model, 'social_links', ['key' => '中文'], []);
        expect($r['social_links'])->toContain('中文');
    });
});

describe('get: JSON string -> PHP array', function () {
    it('parses empty object', function () {
        expect($this->cast->get($this->model, 'social_links', '{}', []))->toBe([]);
    });

    it('parses populated object', function () {
        expect($this->cast->get($this->model, 'social_links', '{"twitter":"x"}', []))
            ->toBe(['twitter' => 'x']);
    });

    it('returns [] for null/empty', function () {
        expect($this->cast->get($this->model, 'social_links', null, []))->toBe([]);
        expect($this->cast->get($this->model, 'social_links', '', []))->toBe([]);
    });

    it('returns [] for malformed JSON', function () {
        expect($this->cast->get($this->model, 'social_links', 'not-json', []))->toBe([]);
    });

    it('passes PHP array through', function () {
        expect($this->cast->get($this->model, 'social_links', ['k' => 'v'], []))->toBe(['k' => 'v']);
    });
});

it('round-trips empty as {}', function () {
    $stored = $this->cast->set($this->model, 'social_links', [], [])['social_links'];
    expect($stored)->toBe('{}');
    expect($this->cast->get($this->model, 'social_links', $stored, []))->toBe([]);
});

it('round-trips populated', function () {
    $input = ['twitter' => 'https://x.com/a', 'github' => 'https://github.com/b'];
    $stored = $this->cast->set($this->model, 'social_links', $input, [])['social_links'];
    expect($this->cast->get($this->model, 'social_links', $stored, []))->toBe($input);
});
