<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * PostgreSQL text[] <-> PHP array.
 *
 * 写：PHP 数组（或 CSV 字符串）-> PG 数组字面量 '{"a","b"}'
 * 读：PG 数组字面量 '{a,b,"with,comma"}' -> PHP 数组
 *
 * Eloquent 默认的 array cast 用 json_encode，PG 不识别 JSON 字面量为 text[]，
 * 所以这里手写。
 */
class PgTextArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return [];
        }
        // 期望形如 '{a,b,"c"}'
        if (!str_starts_with($value, '{') || !str_ends_with($value, '}')) {
            return [];
        }
        $inner = substr($value, 1, -1);
        if ($inner === '') {
            return [];
        }

        // 解析：支持引号包围、内部转义、未引用的简单元素
        preg_match_all('/(?:"((?:[^"\\\\]|\\\\.)*)"|([^,]+))(?:,|$)/', $inner, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $r) {
            $val = isset($r[1]) && $r[1] !== '' ? stripcslashes($r[1]) : ($r[2] ?? '');
            $val = trim($val);
            if ($val !== '' && strcasecmp($val, 'NULL') !== 0) {
                $out[] = $val;
            }
        }
        return $out;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        // CSV 字符串容错（兼容老表单提交格式）
        if (is_string($value)) {
            $value = array_filter(
                array_map('trim', explode(',', $value)),
                fn ($t) => $t !== ''
            );
        }
        if (!is_array($value)) {
            return [$key => '{}'];
        }

        // 去重保序、剔空
        $value = array_values(array_unique(array_filter(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value),
            fn ($t) => $t !== ''
        )));

        if ($value === []) {
            return [$key => '{}'];
        }

        // 构造 PG 数组字面量
        $escaped = array_map(function ($v) {
            $v = str_replace(['\\', '"'], ['\\\\', '\\"'], $v);
            return '"' . $v . '"';
        }, $value);

        return [$key => '{' . implode(',', $escaped) . '}'];
    }
}
