<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 后台操作日志（admin_activity_logs）。只读资源——记录由老 backend 与 Laravel 写入，
 * 后台 UI 仅展示与筛选。
 */
class AdminActivityLog extends Model
{
    use HasFactory;

    protected $table = 'admin_activity_logs';

    public $timestamps = false; // 表只有 created_at

    protected $fillable = [
        'admin_id',
        'admin_username',
        'admin_role',
        'action',
        'request_method',
        'page',
        'target_type',
        'target_id',
        'ip_address',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'admin_id'   => 'int',
            'target_id'  => 'int',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
