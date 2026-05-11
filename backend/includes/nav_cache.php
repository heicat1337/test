<?php
/**
 * 导航数据文件缓存
 *
 * 导航数据修改频率极低（管理员手动调整），把 /nav/categories 序列化结果
 * 写到 data/cache/nav_*.json，绕开 PostgreSQL。后台写操作后调用 invalidate()。
 */

if (!defined('FEISHU_TREASURE')) {
    die('Access denied');
}

class NavCache
{
    /**
     * 进程内缓存 PG 版本号，避免单请求多次 nav_cache_version 查询。
     */
    private static ?int $cachedSharedVersion = null;

    private static function dir(): string
    {
        return __DIR__ . '/../data/cache';
    }

    private static function path(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::dir() . '/nav_' . $safeKey . '.json';
    }

    public static function get(string $key, int $ttlSeconds = 60): ?array
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }
        $mtime = @filemtime($path);
        if ($mtime === false || (time() - $mtime) > $ttlSeconds) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $envelope = json_decode($raw, true);
        if (!is_array($envelope)) {
            return null;
        }
        // Phase 1：版本号信封 ['v' => N, 'data' => [...]]；
        // 老格式（裸数据）兼容回退。
        if (isset($envelope['v'], $envelope['data']) && is_array($envelope['data'])) {
            $sharedVer = self::sharedVersion();
            if ($sharedVer !== null && (int) $envelope['v'] < $sharedVer) {
                return null; // 被对方 bump 了，视为过期
            }
            return $envelope['data'];
        }
        return $envelope;
    }

    public static function set(string $key, array $data): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_writable($dir)) {
            return;
        }
        $path = self::path($key);
        $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $envelope = [
            'v'    => self::sharedVersion() ?? 0,
            'data' => $data,
        ];
        $payload = json_encode($envelope, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    /**
     * 读 nav_cache_version 表当前版本号。表不存在 / 异常时返 null（退化到时戳 ttl）。
     */
    private static function sharedVersion(): ?int
    {
        if (self::$cachedSharedVersion !== null) {
            return self::$cachedSharedVersion;
        }
        global $db;
        if (!($db instanceof \PDO)) {
            return null;
        }
        try {
            $stmt = $db->query('SELECT version FROM nav_cache_version WHERE id = 1');
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            if ($row && isset($row['version'])) {
                return self::$cachedSharedVersion = (int) $row['version'];
            }
        } catch (\Throwable $e) {
            // 表不存在等：退化
        }
        return null;
    }

    public static function invalidate(): void
    {
        $files = glob(self::dir() . '/nav_*.json') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }

        // Phase 1：双跑期间通知 Laravel 端缓存失效（共享 PG 版本号）
        self::bumpSharedVersion();
    }

    /**
     * 维护 nav_cache_version 单行版本号；新 Laravel backend 监视它来失效自身缓存。
     * 表不存在（双跑未启用）时静默忽略——老用户单独跑老 backend 不受影响。
     */
    private static function bumpSharedVersion(): void
    {
        global $db;
        if (!($db instanceof \PDO)) {
            return;
        }
        try {
            $db->exec("UPDATE nav_cache_version SET version = version + 1, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
        } catch (\Throwable $e) {
            // 表不存在 / 权限问题 / 等等：忽略，不阻塞老 backend 写入
        }
    }
}
