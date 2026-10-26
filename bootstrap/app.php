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
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) || getenv($key) !== false) {
            continue; // Deployment environment takes precedence over a local file.
        }
        if (str_starts_with($value, '"')) {
            $decoded = json_decode($value, true);
            if (!is_string($decoded)) {
                continue;
            }
            $value = $decoded;
        } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
$timezone = getenv('APP_TIMEZONE') ?: 'UTC';
if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
    $timezone = 'UTC';
}
date_default_timezone_set($timezone);
require_once $root . '/app/Core/Application.php';
