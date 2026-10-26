<?php
declare(strict_types=1);

namespace Learmingo\Core;

final class Security
{
    public static function isHttps(): bool
    {
        $configuredUrl = getenv('APP_URL') ?: '';
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || str_starts_with(strtolower($configuredUrl), 'https://');
    }

    public static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Permitted-Cross-Domain-Policies: none');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' data:; connect-src 'self'");
        if (self::isHttps() && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function postTooLarge(int $maxBytes = 1048576): bool
    {
        $length = $_SERVER['CONTENT_LENGTH'] ?? null;
        return is_scalar($length) && ctype_digit((string) $length) && (float) $length > $maxBytes;
    }

    public static function clientIp(): string
    {
        // Never trust X-Forwarded-For without an explicitly configured trusted proxy.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
}
