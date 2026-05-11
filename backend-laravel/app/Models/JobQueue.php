<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 老 backend 的任务队列（不是 Laravel jobs 表）。
 * 仅作为只读资源展示给后台监控；消费仍由老 bin/worker.php 负责。
 */
class JobQueue extends Model
{
    use HasFactory;

    protected $table = 'job_queue';

    public $timestamps = true;

    protected $fillable = [
        'task_id', 'job_type', 'status', 'payload',
        'attempt_count', 'max_attempts',
        'available_at', 'claimed_at', 'finished_at',
        'worker_id', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'task_id'       => 'int',
            'attempt_count' => 'int',
            'max_attempts'  => 'int',
            'available_at'  => 'datetime',
            'claimed_at'    => 'datetime',
            'finished_at'   => 'datetime',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
