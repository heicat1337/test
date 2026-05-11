<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keyword extends Model
{
    use HasFactory;

    protected $table = 'keywords';

    public $timestamps = false;

    protected $fillable = ['library_id', 'keyword', 'used_count', 'usage_count'];

    protected function casts(): array
    {
        return [
            'library_id'  => 'int',
            'used_count'  => 'int',
            'usage_count' => 'int',
            'created_at'  => 'datetime',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(KeywordLibrary::class, 'library_id');
    }
}
