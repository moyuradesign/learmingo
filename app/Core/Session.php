<?php
declare(strict_types=1);

namespace Learmingo\Core;

final class Session
{
    public const IDLE_SECONDS = 1800;
    public const ABSOLUTE_SECONDS = 43200;
    public const RENEW_SECONDS = 900;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new \RuntimeException('PHP sessions are required.');
        }
        $name = Environment::get('SESSION_NAME') ?: 'learmingo_session';
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $name)) {
            throw new \RuntimeException('Invalid session configuration.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_trans_sid', '0');
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Security::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('Unable to initialize session.');
        }
        $now = time();
        $created = (int) ($_SESSION['_created_at'] ?? $now);
        $seen = (int) ($_SESSION['_last_seen_at'] ?? $now);
        if (($now - $seen > self::IDLE_SECONDS) || ($now - $created > self::ABSOLUTE_SECONDS)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $created = $now;
        } elseif ($now - (int) ($_SESSION['_renewed_at'] ?? $now) >= self::RENEW_SECONDS) {
            session_regenerate_id(true);
            $_SESSION['_renewed_at'] = $now;
        }
        $_SESSION['_created_at'] = $created;
        $_SESSION['_last_seen_at'] = $now;
        $_SESSION['_renewed_at'] ??= $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_seen_at'] = time();
        $_SESSION['_renewed_at'] = time();
    }
}
