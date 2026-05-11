<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImageLibrary extends Model
{
    use HasFactory;

    protected $table = 'image_libraries';

    public $timestamps = true;

    protected $fillable = ['name', 'description', 'image_count', 'used_task_count'];

    protected $attributes = [
        'image_count'     => 0,
        'used_task_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'image_count'     => 'int',
            'used_task_count' => 'int',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'library_id');
    }
}
