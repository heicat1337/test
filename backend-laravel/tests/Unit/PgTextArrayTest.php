<?php

use App\Casts\PgTextArray;
use Illuminate\Database\Eloquent\Model;

/** @var PgTextArray $cast */
beforeEach(function () {
    $this->cast = new PgTextArray();
    $this->model = new class extends Model {};
});

describe('set: PHP -> PG 数组字面量', function () {
    it('writes empty array as {}', function () {
        $r = $this->cast->set($this->model, 'tags', [], []);
        expect($r['tags'])->toBe('{}');
    });

    it('writes plain elements quoted', function () {
        $r = $this->cast->set($this->model, 'tags', ['a', 'b', 'c'], []);
        expect($r['tags'])->toBe('{"a","b","c"}');
    });

    it('escapes commas inside elements', function () {
        $r = $this->cast->set($this->model, 'tags', ['hello,world', 'plain'], []);
        expect($r['tags'])->toBe('{"hello,world","plain"}');
    });

    it('escapes double quotes', function () {
        $r = $this->cast->set($this->model, 'tags', ['it"s', 'plain'], []);
        expect($r['tags'])->toBe('{"it\"s","plain"}');
    });

    it('escapes backslashes', function () {
        $r = $this->cast->set($this->model, 'tags', ['a\\b'], []);
        expect($r['tags'])->toBe('{"a\\\\b"}');
    });

    it('strips empty / whitespace-only elements', function () {
        $r = $this->cast->set($this->model, 'tags', ['valid', '', '  ', 'kept'], []);
        expect($r['tags'])->toBe('{"valid","kept"}');
    });

    it('dedupes case-sensitively, preserves order', function () {
        $r = $this->cast->set($this->model, 'tags', ['a', 'b', 'a', 'A'], []);
        expect($r['tags'])->toBe('{"a","b","A"}');
    });

    it('accepts CSV string for legacy form input compat', function () {
        $r = $this->cast->set($this->model, 'tags', 'foo, bar , baz', []);
        expect($r['tags'])->toBe('{"foo","bar","baz"}');
    });

    it('handles unicode and emoji', function () {
        $r = $this->cast->set($this->model, 'tags', ['标签', '🦄'], []);
        expect($r['tags'])->toBe('{"标签","🦄"}');
    });

    it('returns {} for non-array, non-string input', function () {
        expect($this->cast->set($this->model, 'tags', null, [])['tags'])->toBe('{}');
        expect($this->cast->set($this->model, 'tags', 42, [])['tags'])->toBe('{}');
    });
});

describe('get: PG 字面量 -> PHP 数组', function () {
    it('parses empty {} as []', function () {
        expect($this->cast->get($this->model, 'tags', '{}', []))->toBe([]);
    });

    it('parses unquoted simple elements', function () {
        expect($this->cast->get($this->model, 'tags', '{a,b,c}', []))->toBe(['a', 'b', 'c']);
    });

    it('parses quoted elements with commas', function () {
        expect($this->cast->get($this->model, 'tags', '{"hello,world",plain}', []))->toBe(['hello,world', 'plain']);
    });

    it('unescapes double quotes', function () {
        expect($this->cast->get($this->model, 'tags', '{"it\\"s",plain}', []))->toBe(['it"s', 'plain']);
    });

    it('returns [] for null/empty', function () {
        expect($this->cast->get($this->model, 'tags', null, []))->toBe([]);
        expect($this->cast->get($this->model, 'tags', '', []))->toBe([]);
    });

    it('returns [] for malformed input', function () {
        expect($this->cast->get($this->model, 'tags', 'not-pg-array', []))->toBe([]);
    });

    it('passes through PHP array unchanged', function () {
        expect($this->cast->get($this->model, 'tags', ['x', 'y'], []))->toBe(['x', 'y']);
    });
});

it('round-trips set->get for typical input', function () {
    $input = ['exchange', 'cex', 'spot', 'futures'];
    $stored = $this->cast->set($this->model, 'tags', $input, [])['tags'];
    expect($this->cast->get($this->model, 'tags', $stored, []))->toBe($input);
});

it('round-trips with special chars', function () {
    $input = ['has,comma', 'has"quote', 'plain'];
    $stored = $this->cast->set($this->model, 'tags', $input, [])['tags'];
    expect($this->cast->get($this->model, 'tags', $stored, []))->toBe($input);
});
