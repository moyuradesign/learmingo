<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bootstrap/autoload.php';

$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\"'");
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

require_once $root . '/app/Core/Application.php';
