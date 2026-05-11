<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * PostgreSQL jsonb object <-> PHP associative array.
 *
 * 与默认 array cast 的区别：
 *   - 空值统一存 '{}'（对象字面量）而不是 '[]'（数组字面量），
 *     这样 jsonb_typeof = 'object' 始终成立，jsonb_path_ops GIN 索引
 *     的 @> 包含查询语义稳定。
 *   - 关联数组 -> JSON object；list 数组也强制转成 object（基于上面的约束）。
 *
 * 用法：
 *   protected $casts = ['social_links' => PgJsonbObject::class];
 */
class PgJsonbObject implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (!is_array($value)) {
            return [$key => '{}'];
        }
        if ($value === []) {
            // 空数组也写成 object 字面量，保证 jsonb_typeof 始终 = 'object'
            return [$key => '{}'];
        }
        // (object) [] 强制 stdClass，json_encode 出 {}；
        // 非空关联数组直接 json_encode 出 {"k":"v",...}。
        return [$key => json_encode((object) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
    }
}
