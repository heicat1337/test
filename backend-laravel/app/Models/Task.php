<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 内容生成任务（tasks，34 列）。是 GEOFlow 最复杂的实体。
 *
 * 核心字段：
 *   - title_library_id / image_library_id / knowledge_base_id / prompt_id / content_prompt_id / ai_model_id
 *   - author_type (random/fixed) + custom_author_id
 *   - category_mode (smart/fixed) + fixed_category_id
 *   - status (idle/running/completed/error/paused)
 *   - 计数：created_count, published_count, loop_count, draft_limit
 *   - 调度：schedule_enabled, publish_interval (秒), is_loop, max_retry_count
 *   - 时间戳：last_run_at, next_run_at, last_success_at, last_error_at, last_error_message
 */
class Task extends Model
{
    use HasFactory;

    protected $table = 'tasks';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'title_library_id',
        'image_library_id',
        'image_count',
        'prompt_id',
        'content_prompt_id',
        'ai_model_id',
        'author_id',
        'need_review',
        'publish_interval',
        'author_type',
        'custom_author_id',
        'auto_keywords',
        'auto_description',
        'draft_limit',
        'is_loop',
        'model_selection_mode',
        'status',
        'created_count',
        'published_count',
        'loop_count',
        'knowledge_base_id',
        'category_mode',
        'fixed_category_id',
        'last_run_at',
        'next_run_at',
        'last_success_at',
        'last_error_at',
        'last_error_message',
        'schedule_enabled',
        'max_retry_count',
    ];

    protected function casts(): array
    {
        return [
            'title_library_id'  => 'int',
            'image_library_id'  => 'int',
            'image_count'       => 'int',
            'prompt_id'         => 'int',
            'content_prompt_id' => 'int',
            'ai_model_id'       => 'int',
            'author_id'         => 'int',
            'need_review'       => 'int',
            'publish_interval'  => 'int',
            'custom_author_id'  => 'int',
            'auto_keywords'     => 'int',
            'auto_description'  => 'int',
            'draft_limit'       => 'int',
            'is_loop'           => 'int',
            'created_count'     => 'int',
            'published_count'   => 'int',
            'loop_count'        => 'int',
            'knowledge_base_id' => 'int',
            'fixed_category_id' => 'int',
            'schedule_enabled'  => 'int',
            'max_retry_count'   => 'int',
            'last_run_at'       => 'datetime',
            'next_run_at'       => 'datetime',
            'last_success_at'   => 'datetime',
            'last_error_at'     => 'datetime',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    protected $attributes = [
        'status'               => 'idle',
        'image_count'          => 0,
        'need_review'          => 1,
        'publish_interval'     => 0,
        'auto_keywords'        => 1,
        'auto_description'     => 1,
        'draft_limit'          => 0,
        'is_loop'              => 0,
        'model_selection_mode' => 'fixed',
        'created_count'        => 0,
        'published_count'      => 0,
        'loop_count'           => 0,
        'category_mode'        => 'smart',
        'author_type'          => 'random',
        'schedule_enabled'     => 1,
        'max_retry_count'      => 3,
    ];

    // ---- 关系 ----

    public function titleLibrary(): BelongsTo
    {
        return $this->belongsTo(TitleLibrary::class, 'title_library_id');
    }

    public function imageLibrary(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'image_library_id');
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'knowledge_base_id');
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'prompt_id');
    }

    public function contentPrompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'content_prompt_id');
    }

    public function customAuthor(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'custom_author_id');
    }

    public function fixedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'fixed_category_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TaskRun::class, 'task_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'task_id');
    }
}
