<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeywordLibrary extends Model
{
    use HasFactory;

    protected $table = 'keyword_libraries';

    public $timestamps = true;

    protected $fillable = ['name', 'description', 'keyword_count'];

    protected function casts(): array
    {
        return [
            'keyword_count' => 'int',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'library_id');
    }
}
