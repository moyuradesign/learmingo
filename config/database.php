<?php
declare(strict_types=1);

return [
    'host' => \Learmingo\Core\Environment::get('DB_HOST') ?: '127.0.0.1',
    'port' => \Learmingo\Core\Environment::get('DB_PORT') ?: '3306',
    'database' => \Learmingo\Core\Environment::get('DB_DATABASE') ?: '',
    'username' => \Learmingo\Core\Environment::get('DB_USERNAME') ?: '',
    'password' => \Learmingo\Core\Environment::get('DB_PASSWORD') ?: '',
    'prefix' => \Learmingo\Core\Environment::get('DB_PREFIX') ?: 'lm_',
];
