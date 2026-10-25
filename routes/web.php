<?php
declare(strict_types=1);

use Learmingo\Core\Response;

$router = $app->getRouter();

$router->get('/', static function (): void {
    $config = require dirname(__DIR__) . '/config/app.php';
    Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8') . '</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><main class="container py-5"><h1 class="display-5">Learmingo</h1><p class="lead">Learning Management System infrastructure is ready.</p><span class="badge text-bg-primary">v0.0.1</span></main></body></html>');
});
