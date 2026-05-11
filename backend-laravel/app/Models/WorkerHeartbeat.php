<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Worker 心跳（worker_heartbeats）。表无 id 列，主键是 worker_id。
 * 只读监控用：Filament 展示当前活跃 worker。
 */
class WorkerHeartbeat extends Model
{
    use HasFactory;

    protected $table = 'worker_heartbeats';

    protected $primaryKey = 'worker_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = ['worker_id', 'status', 'current_job_id', 'last_seen_at', 'meta'];

    protected function casts(): array
    {
        return [
            'current_job_id' => 'int',
            'last_seen_at'   => 'datetime',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
        ];
    }
}
