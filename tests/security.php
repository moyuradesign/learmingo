<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/app.php';

use Learmingo\Core\Csrf;
use Learmingo\Core\RateLimiter;
use Learmingo\Core\Router;
use Learmingo\Core\Security;
use Learmingo\Core\Session;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "SECURITY TEST FAILED: {$message}\n"); exit(1); }
};
Session::start();
$assert(ini_get('session.use_strict_mode') === '1', 'strict session mode');
$assert(ini_get('session.use_only_cookies') === '1', 'cookie-only sessions');
$assert((bool)session_get_cookie_params()['httponly'], 'HTTP-only session');
$assert(Session::IDLE_SECONDS <= 1800, 'idle timeout');
$assert(Session::ABSOLUTE_SECONDS <= 43200, 'absolute session expiry');
$token = Csrf::token();
$assert(strlen($token) === 64 && Csrf::verify($token), 'valid CSRF');
$assert(!Csrf::verify(['attack']), 'array CSRF rejected');
$assert(!Csrf::verify(''), 'empty CSRF rejected');
$assert(!Csrf::verify(str_repeat('0',64)), 'bad CSRF rejected');
Csrf::rotate();
$assert(!Csrf::verify($token) && Csrf::verify(Csrf::token()), 'CSRF rotates');

$key = bin2hex(random_bytes(12));
$assert(RateLimiter::hit('security-test',$key,2,60) === 0, 'first request');
$assert(RateLimiter::hit('security-test',$key,2,60) === 0, 'second request');
$assert(RateLimiter::hit('security-test',$key,2,60) > 0, 'request limited');
RateLimiter::clear('security-test',$key);
$assert(RateLimiter::hit('security-test',$key,2,60) === 0, 'counter reset');
RateLimiter::clear('security-test',$key);

$_SERVER['REMOTE_ADDR'] = '192.0.2.4';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
$assert(Security::clientIp() === '192.0.2.4', 'spoofed client IP ignored');
$_SERVER['CONTENT_LENGTH'] = '1048577';
$assert(Security::postTooLarge(), 'large request rejected');
$_SERVER['CONTENT_LENGTH'] = '100';
$assert(!Security::postTooLarge(), 'small request accepted');

$router = new Router();
$called = 0;
$router->get('/unit-test', static function () use (&$called): void { $called++; });
ob_start(); $router->dispatch('GET','/unit-test'); ob_end_clean();
$assert($called === 1, 'GET route works');
ob_start(); $router->dispatch('POST','/unit-test'); ob_end_clean();
$assert(http_response_code() === 405, 'wrong method rejected');

$root = dirname(__DIR__);
foreach (['public/.htaccess','public/uploads/.htaccess','public/install/index.php','storage/.gitkeep','docs/SECURITY.md'] as $path) {
    $assert(is_file($root.'/'.$path), 'missing safety artifact '.$path);
}
$routes = file_get_contents($root.'/routes/web.php');
$assert(str_contains($routes, "RateLimiter::hit('login-ip'"), 'login limiter wired');
$assert(str_contains($routes, "RateLimiter::hit('register-ip'"), 'register limiter wired');
$versionService = file_get_contents($root.'/app/Services/CourseVersions.php');
$assert(is_string($versionService) && str_contains($versionService, "Course unavailable for free enrollment.")
    && str_contains($versionService, "!==0.0")
    && str_contains($routes, '->enrollFree('), 'paid course guard');
$assert(str_contains($routes, "r.slug<>'admin'"), 'admin role guard');
$assert(str_contains($routes, 'Csrf::verify('), 'CSRF guard');
$installer = file_get_contents($root.'/install/index.php');
$assert(str_contains($installer, 'setup.token'), 'installer token');
$assert(!str_contains($installer, 'ON DUPLICATE KEY UPDATE'), 'no admin takeover');
$assert(str_contains($installer, "fopen("), 'exclusive setup file writer');
echo "Learmingo security regression checks passed.\n";
