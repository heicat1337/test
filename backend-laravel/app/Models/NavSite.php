<?php

namespace App\Models;

use App\Casts\PgJsonbObject;
use App\Casts\PgTextArray;
use App\Support\NavCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavSite extends Model
{
    use HasFactory;

    protected $table = 'nav_sites';

    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'name',
        'url',
        'description',
        'icon',
        'sort_order',
        'is_recommended',
        'tags',
        'rating',
        'social_links',
        'screenshot_url',
    ];

    protected $casts = [
        'category_id'    => 'int',
        'sort_order'     => 'int',
        'is_recommended' => 'bool',
        'tags'           => PgTextArray::class,    // PG text[] <-> PHP array
        'rating'         => 'float',
        'social_links'   => PgJsonbObject::class,  // PG jsonb  <-> PHP array（空值规约为 '{}' 对象）
        'created_at'     => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(NavCategory::class, 'category_id');
    }

    protected static function booted(): void
    {
        static::saved(fn () => NavCache::bump());
        static::deleted(fn () => NavCache::bump());
    }
}
