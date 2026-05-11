<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensitiveWord extends Model
{
    use HasFactory;

    protected $table = 'sensitive_words';

    public $timestamps = false; // 表只有 created_at

    protected $fillable = [
        'word',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
