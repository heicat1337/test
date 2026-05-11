<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    use HasFactory;

    protected $table = 'knowledge_bases';

    public $timestamps = true;

    protected $fillable = [
        'name', 'description', 'content', 'character_count',
        'used_task_count', 'file_type', 'file_path', 'word_count', 'usage_count',
    ];

    protected $attributes = [
        'content'         => '',
        'character_count' => 0,
        'used_task_count' => 0,
        'word_count'      => 0,
        'usage_count'     => 0,
        'file_type'       => 'markdown',
    ];

    protected function casts(): array
    {
        return [
            'character_count' => 'int',
            'used_task_count' => 'int',
            'word_count'      => 'int',
            'usage_count'     => 'int',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }
}
