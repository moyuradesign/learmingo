<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Learmingo',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim(getenv('APP_URL') ?: 'http://localhost', '/'),
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
];
