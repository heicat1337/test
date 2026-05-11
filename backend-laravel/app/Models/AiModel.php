<?php

namespace App\Models;

use App\Support\GeoFlowCrypt;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AI 模型配置（ai_models）。api_key 用 GeoFlowCrypt 与老 backend 互通加密。
 */
class AiModel extends Model
{
    use HasFactory;

    protected $table = 'ai_models';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'version',
        'api_key',
        'model_id',
        'model_type',
        'api_url',
        'failover_priority',
        'daily_limit',
        'used_today',
        'total_used',
        'status',
    ];

    protected $hidden = [
        'api_key',  // toArray/toJson 不暴露明文
    ];

    protected function casts(): array
    {
        return [
            'failover_priority' => 'int',
            'daily_limit'       => 'int',
            'used_today'        => 'int',
            'total_used'        => 'int',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    /**
     * api_key 读写自动加解密；表单/Filament 看到的总是明文。
     */
    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => GeoFlowCrypt::decrypt($value),
            set: fn ($value) => ['api_key' => GeoFlowCrypt::encrypt((string) $value)],
        );
    }
}
