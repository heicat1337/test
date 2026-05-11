<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * URL 批量导入任务（url_import_jobs）。options_json / result_json 是 JSON 字符串。
 */
class UrlImportJob extends Model
{
    use HasFactory;

    protected $table = 'url_import_jobs';

    public $timestamps = true;

    protected $fillable = [
        'url', 'normalized_url', 'source_domain', 'page_title',
        'status', 'current_step', 'progress_percent',
        'options_json', 'result_json', 'error_message',
        'created_by', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'int',
            'options_json'     => 'array',  // text 列存 JSON，array cast 兼容
            'result_json'      => 'array',
            'started_at'       => 'datetime',
            'finished_at'      => 'datetime',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }
}
