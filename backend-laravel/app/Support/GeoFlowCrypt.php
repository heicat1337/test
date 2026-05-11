<?php

namespace App\Support;

/**
 * 与老 backend includes/config.php 中的 encrypt_sensitive_value / decrypt_sensitive_value 对齐。
 *
 * 加密格式：'enc:v1:' . base64(IV(16) + AES-256-CBC ciphertext)
 * 密钥：    sha256(APP_SECRET_KEY, raw=true)
 *
 * 用途：ai_models.api_key 在两端读写互通（Laravel Filament 改 key，老 backend AI 服务能解密；反之亦然）。
 */
class GeoFlowCrypt
{
    private const PREFIX = 'enc:v1:';

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public static function encrypt(?string $plaintext): string
    {
        $plaintext = (string) ($plaintext ?? '');
        if ($plaintext === '') {
            return '';
        }
        if (self::isEncrypted($plaintext)) {
            return $plaintext;  // 已是密文，原样返回
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($cipher === false) {
            return $plaintext;  // 加密失败兜底
        }

        return self::PREFIX . base64_encode($iv . $cipher);
    }

    public static function decrypt(?string $stored): string
    {
        $stored = (string) ($stored ?? '');
        if ($stored === '') {
            return '';
        }
        if (!self::isEncrypted($stored)) {
            return $stored;  // 兼容老明文数据
        }

        $payload = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) <= 16) {
            return '';
        }
        $iv = substr($payload, 0, 16);
        $ciphertext = substr($payload, 16);

        $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    private static function key(): string
    {
        // APP_SECRET_KEY 与老 backend env 同源；
        // 老 backend fallback 是 'your-secret-key-change-this-in-production'，
        // Laravel 不沿用这个 fallback——若 .env 没设，加密能 work（用空字符串 hash），但与老 backend 不互通。
        $secret = env('APP_SECRET_KEY', '');
        return hash('sha256', $secret, true);
    }
}
