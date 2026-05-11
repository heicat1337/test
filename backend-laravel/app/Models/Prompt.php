<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 提示词模板（prompts）。type 区分 content / title / meta_description 等用途。
 * variables 是 JSON/CSV 存的模板变量（保留 text 类型不动）。
 */
class Prompt extends Model
{
    use HasFactory;

    protected $table = 'prompts';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'type',
        'content',
        'variables',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
