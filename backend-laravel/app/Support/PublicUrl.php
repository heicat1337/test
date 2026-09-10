<?php

namespace App\Support;

class PublicUrl
{
    public static function base(): string
    {
        $raw = rtrim((string) config('app.url', 'https://xuaweb3.com'), '/');
        $host = strtolower((string) parse_url($raw, PHP_URL_HOST));
        $local = ['', 'localhost', '127.0.0.1', '0.0.0.0', '::1'];

        if (in_array($host, $local, true) || str_ends_with($host, '.localhost')) {
            return 'https://xuaweb3.com';
        }

        if ($host === 'www.xuaweb3.com' || $host === 'xuaweb3.com') {
            return 'https://xuaweb3.com';
        }

        $scheme = parse_url($raw, PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$host;
    }

    public static function of(string $path = '/'): string
    {
        $base = self::base();
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return $base.'/';
        }

        return $base.'/'.ltrim($path, '/');
    }
}
