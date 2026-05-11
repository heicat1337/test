<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 作者表（authors）。老 backend 的 social_links 列是 TEXT 存 JSON 字符串，
 * 这里用 'array' cast 自动 json_encode/decode（PG text 列接受任意字符串）。
 *
 * 不同于 nav_sites 我们升级到 jsonb——这里保持 text 兼容老 backend 不动。
 */
class Author extends Model
{
    use HasFactory;

    protected $table = 'authors';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'bio',
        'email',
        'avatar',
        'website',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',  // 自动 JSON 编解码，text 列存 JSON 字符串
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }
}
