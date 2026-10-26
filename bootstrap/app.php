<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bootstrap/autoload.php';

\Learmingo\Core\Environment::load($root . '/.env');
$timezone = \Learmingo\Core\Environment::get('APP_TIMEZONE') ?: 'UTC';
if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
    $timezone = 'UTC';
}
date_default_timezone_set($timezone);
require_once $root . '/app/Core/Application.php';
