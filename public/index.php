<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use Learmingo\Core\Application;

$app = Application::boot(dirname(__DIR__));
$app->run();
