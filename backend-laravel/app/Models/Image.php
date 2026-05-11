<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    use HasFactory;

    protected $table = 'images';

    public $timestamps = false;

    protected $fillable = [
        'library_id', 'filename', 'original_name', 'file_path', 'file_size',
        'mime_type', 'used_count', 'file_name', 'width', 'height', 'tags', 'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'library_id'  => 'int',
            'file_size'   => 'int',
            'used_count'  => 'int',
            'usage_count' => 'int',
            'width'       => 'int',
            'height'      => 'int',
            'created_at'  => 'datetime',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'library_id');
    }
}
