<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 文章审核流水（article_reviews）。每次 ArticleService::review 写一行。
 */
class ArticleReview extends Model
{
    use HasFactory;

    protected $table = 'article_reviews';

    public $timestamps = false; // 表只有 created_at

    protected $fillable = [
        'article_id',
        'admin_id',
        'review_status',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'article_id' => 'int',
            'admin_id'   => 'int',
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
