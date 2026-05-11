<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 任务运行记录。只读资源——由老 backend worker.php / Laravel queue worker 写入。
 */
class TaskRun extends Model
{
    use HasFactory;

    protected $table = 'task_runs';

    public $timestamps = false;

    protected $fillable = [
        'task_id', 'job_id', 'status', 'article_id',
        'error_message', 'duration_ms', 'meta',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'task_id'     => 'int',
            'job_id'      => 'int',
            'article_id'  => 'int',
            'duration_ms' => 'int',
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
