<?php
declare(strict_types=1);

namespace Learmingo\Helpers;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $config = require dirname(__DIR__, 2) . '/config/app.php';
    return $config['url'] . '/' . ltrim($path, '/');
}
