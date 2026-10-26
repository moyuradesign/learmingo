<?php
declare(strict_types=1);

namespace Learmingo\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(mixed $token): bool
    {
        Session::start();
        return is_string($token) && strlen($token) === 64
            && isset($_SESSION['_csrf']) && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function rotate(): void
    {
        Session::start();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
