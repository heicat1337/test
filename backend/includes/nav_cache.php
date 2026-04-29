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
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
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
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
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

    public static function invalidate(): void
    {
        $files = glob(self::dir() . '/nav_*.json') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }
    }
}
