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
        header('Location: ' . $url, true, 302);
        exit;
    }
}
