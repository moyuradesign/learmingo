<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Learmingo\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $root = dirname(__DIR__);
    $locations = [$root . '/app/' . $relative . '.php', $root . '/bootstrap/' . $relative . '.php'];
    foreach ($locations as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
