<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * 文章（articles 表，23 列）。
 *
 * GEOFlow 设计要点：
 *   - 软删除：deleted_at 列存在，用 SoftDeletes
 *   - 工作流：status（draft/published/...）+ review_status（pending/approved/rejected）
 *   - SEO：keywords (CSV)、meta_description、original_keyword
 *   - AI：is_ai_generated (int 0/1)、task_id（关联 tasks 生成任务）
 *   - 没有真正的 tags 关系表——keywords 字段即用作标签
 */
class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'articles';

    public $timestamps = true;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category_id',
        'author_id',
        'task_id',
        'original_keyword',
        'keywords',
        'meta_description',
        'status',
        'review_status',
        'view_count',
        'is_ai_generated',
        'published_at',
        'is_featured',
        'like_count',
        'comment_count',
        'featured_image',
    ];

    // 与 PG 列默认值对齐——避免 create() 后读 model 内存属性是 null
    protected $attributes = [
        'excerpt'          => '',
        'original_keyword' => '',
        'keywords'         => '',
        'meta_description' => '',
        'status'           => 'draft',
        'review_status'    => 'pending',
        'view_count'       => 0,
        'is_ai_generated'  => 0,
        'is_featured'      => 0,
        'like_count'       => 0,
        'comment_count'    => 0,
        'featured_image'   => '',
    ];

    protected function casts(): array
    {
        return [
            'category_id'     => 'int',
            'author_id'       => 'int',
            'task_id'         => 'int',
            'view_count'      => 'int',
            'is_ai_generated' => 'int',     // 老 schema 是 INTEGER 0/1，保留 int 类型保兼容
            'is_featured'     => 'int',
            'like_count'      => 'int',
            'comment_count'   => 'int',
            'published_at'    => 'datetime',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
            'deleted_at'      => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    // task() 关系待 Phase 4 AI/任务组迁好后再补

    // ---- Scopes ----

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopeOfStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeOfReviewStatus(Builder $q, string $reviewStatus): Builder
    {
        return $q->where('review_status', $reviewStatus);
    }

    // ---- Helpers ----

    /**
     * keywords 在 PG 是 text CSV；这里 mutator 桥接成 PHP array。
     * 既兼容 TagsInput（输入数组）也兼容老 backend 直接传 CSV 字符串。
     */
    protected function keywords(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') {
                    return [];
                }
                if (is_array($value)) {
                    return $value;
                }
                return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($t) => $t !== ''));
            },
            set: function ($value) {
                if (is_string($value)) {
                    return ['keywords' => $value];
                }
                if (!is_array($value)) {
                    return ['keywords' => ''];
                }
                $clean = array_values(array_unique(array_filter(
                    array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value),
                    fn ($t) => $t !== ''
                )));
                return ['keywords' => implode(',', $clean)];
            }
        );
    }

    public function tagList(): array
    {
        return $this->keywords;  // 通过 mutator 已经是 array
    }

    /**
     * slug 自动生成（如果用户没提供）。要么源自 title 拼音化，要么 fallback 'article-{id}'。
     * 这里简化：如果空就用随机 8 字节 hex。生产可接 \Illuminate\Support\Str::slug() + pinyin。
     */
    public static function generateUniqueSlug(string $base): string
    {
        $candidate = Str::slug($base) ?: 'article-' . Str::random(8);
        $i = 0;
        while (self::withTrashed()->where('slug', $candidate)->exists()) {
            $i++;
            $candidate = Str::slug($base) . '-' . $i;
        }
        return $candidate;
    }
}
