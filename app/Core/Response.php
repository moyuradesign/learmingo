<?php
declare(strict_types=1);

namespace Learmingo\Core;

final class Response
{
    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    public static function redirect(string $url): never
    {
        // Redirects from application routes must remain on the same origin.
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')
            || preg_match('/[\x00-\x1f\x7f\\\\]/', $url)) {
            throw new \InvalidArgumentException('Unsafe redirect target.');
        }
        header('Location: ' . $url, true, 303);
        exit;
    }

    public static function tooManyRequests(int $retryAfter): void
    {
        header('Retry-After: ' . max(1, $retryAfter));
        self::html('Too many requests. Please try again later.', 429);
    }
}
