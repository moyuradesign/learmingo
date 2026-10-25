<?php
declare(strict_types=1);

use Learmingo\Core\Auth;
use Learmingo\Core\Csrf;
use Learmingo\Core\Response;
use Learmingo\Core\Session;
use Learmingo\Core\View;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        return $config['url'] . '/' . ltrim($path, '/');
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array { return Auth::user(); }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string { return Csrf::token(); }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">'; }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) { Session::put('_flash_' . $key, $value); return $value; }
        $result = Session::get('_flash_' . $key);
        Session::forget('_flash_' . $key);
        return $result;
    }
}
