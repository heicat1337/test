<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Title extends Model
{
    use HasFactory;

    protected $table = 'titles';

    public $timestamps = false;

    protected $fillable = ['library_id', 'title', 'keyword', 'used_count', 'usage_count', 'is_ai_generated'];

    protected function casts(): array
    {
        return [
            'library_id'      => 'int',
            'used_count'      => 'int',
            'usage_count'     => 'int',
            'is_ai_generated' => 'bool',
            'created_at'      => 'datetime',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(TitleLibrary::class, 'library_id');
    }
}
