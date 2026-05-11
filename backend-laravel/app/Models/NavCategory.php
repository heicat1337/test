<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavCategory extends Model
{
    use HasFactory;

    protected $table = 'nav_categories';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'int',
        'created_at' => 'datetime',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(NavSite::class, 'category_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\NavCache::bump());
        static::deleted(fn () => \App\Support\NavCache::bump());
    }
}
