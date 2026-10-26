<?php
declare(strict_types=1);

return [
    'name' => \Learmingo\Core\Environment::get('APP_NAME') ?: 'Learmingo',
    'env' => \Learmingo\Core\Environment::get('APP_ENV') ?: 'production',
    'debug' => filter_var(\Learmingo\Core\Environment::get('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim(\Learmingo\Core\Environment::get('APP_URL') ?: 'http://localhost', '/'),
    'timezone' => \Learmingo\Core\Environment::get('APP_TIMEZONE') ?: 'UTC',
];
