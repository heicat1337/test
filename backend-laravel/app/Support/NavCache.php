<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 导航缓存键统一管理 + 双跑版本号同步。
 *
 * 版本号位于 PG 表 nav_cache_version（单行）。任何一方修改导航数据都 bump 一次，
 * 对方读路径感知到版本号变化后旧缓存自然失效。
 *
 *   - 老 backend   → includes/nav_cache.php::invalidate() 里 UPDATE nav_cache_version
 *   - Laravel/Filament → NavCategory/NavSite 模型 saved/deleted 钩子调 bump()
 *
 * 缓存 key 形如：nav.v{N}.categories / nav.v{N}.sites.{cat_id} / nav.v{N}.site.{id}
 *
 * 性能：version() 用 1 秒本地缓存避免每请求都查 PG（nav 写入低频，1s 延迟可接受）。
 */
class NavCache
{
    private const VERSION_LOCAL_KEY = 'nav.cache_version_cached';
    private const VERSION_LOCAL_TTL = 1;  // 秒

    public static function version(): int
    {
        return (int) Cache::remember(self::VERSION_LOCAL_KEY, self::VERSION_LOCAL_TTL, function () {
            try {
                $row = DB::selectOne('SELECT version FROM nav_cache_version WHERE id = 1');
                return $row ? (int) $row->version : 1;
            } catch (Throwable $e) {
                // 表不存在或连接异常时退化到 1（Laravel 启动早期/migration 未跑）
                return 1;
            }
        });
    }

    public static function bump(): void
    {
        try {
            DB::statement('UPDATE nav_cache_version SET version = version + 1, updated_at = NOW() WHERE id = 1');
        } catch (Throwable $e) {
            // 表不存在时退化（极少发生）
            return;
        }
        // 立即清掉本地 1s 缓存，让自身后续读到新版本号
        Cache::forget(self::VERSION_LOCAL_KEY);
    }

    public static function key(string $suffix): string
    {
        return 'nav.v' . self::version() . '.' . $suffix;
    }
}
