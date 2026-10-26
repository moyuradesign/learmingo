<?php
declare(strict_types=1);

namespace Learmingo\Core;

/** Read application configuration with or without the optional putenv() function. */
final class Environment
{
    /** @var array<string, string> */
    private static array $loaded = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            throw new \RuntimeException('Unable to read application configuration.');
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $key)
                || getenv($key) !== false || isset($_ENV[$key])) {
                continue; // Environment variables supplied by the server take precedence.
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
            self::$loaded[$key] = $value;
            $_ENV[$key] = $value;
            // Some hosting configurations deliberately disable putenv(). The
            // application always uses Environment::get(), not getenv(), below.
            if (function_exists('putenv')) {
                putenv($key . '=' . $value);
            }
        }
    }

    public static function get(string $key): string|false
    {
        $server = getenv($key);
        if ($server !== false) {
            return $server;
        }
        if (array_key_exists($key, self::$loaded)) {
            return self::$loaded[$key];
        }
        return isset($_ENV[$key]) && is_string($_ENV[$key]) ? $_ENV[$key] : false;
    }
}
