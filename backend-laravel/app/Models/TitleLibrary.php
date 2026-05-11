<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TitleLibrary extends Model
{
    use HasFactory;

    protected $table = 'title_libraries';

    public $timestamps = true;

    protected $fillable = [
        'name', 'description', 'title_count',
        'generation_type', 'keyword_library_id',
        'ai_model_id', 'prompt_id', 'generation_rounds',
        'is_ai_generated',
    ];

    protected function casts(): array
    {
        return [
            'title_count'        => 'int',
            'keyword_library_id' => 'int',
            'ai_model_id'        => 'int',
            'prompt_id'          => 'int',
            'generation_rounds'  => 'int',
            'is_ai_generated'    => 'int',
            'created_at'         => 'datetime',
            'updated_at'         => 'datetime',
        ];
    }

    public function titles(): HasMany
    {
        return $this->hasMany(Title::class, 'library_id');
    }
}
