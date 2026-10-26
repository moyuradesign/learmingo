<?php
declare(strict_types=1);
// CLI-server-only router for HTTP smoke tests. Not a production router.
$uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$public = dirname(__DIR__) . '/public';
if ($uri === '/install' || $uri === '/install/') {
    require $public . '/install/index.php';
    return true;
}
$file = realpath($public . '/' . ltrim($uri, '/'));
if ($file !== false && str_starts_with($file, $public . '/') && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
require $public . '/index.php';
