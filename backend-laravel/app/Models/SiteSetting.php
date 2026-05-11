<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * 站点设置：单一 key-value 表，老 backend 通过 get_setting()/set_setting() 函数访问。
 *
 * 用法：
 *   SiteSetting::value('site_name', 'fallback');
 *   SiteSetting::put('site_name', '新站名');
 */
class SiteSetting extends Model
{
    use HasFactory;

    protected $table = 'site_settings';

    public $timestamps = true;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];

    public static function value(string $key, string $default = ''): string
    {
        return Cache::remember("site_setting:{$key}", 60, function () use ($key, $default) {
            $row = static::where('setting_key', $key)->first();
            return $row ? (string) $row->setting_value : $default;
        });
    }

    public static function put(string $key, string $value): void
    {
        static::updateOrCreate(['setting_key' => $key], ['setting_value' => $value]);
        Cache::forget("site_setting:{$key}");
    }

    protected static function booted(): void
    {
        static::saved(fn (SiteSetting $s) => Cache::forget("site_setting:{$s->setting_key}"));
        static::deleted(fn (SiteSetting $s) => Cache::forget("site_setting:{$s->setting_key}"));
    }
}
