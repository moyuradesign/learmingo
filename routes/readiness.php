<?php
declare(strict_types=1);
use Learmingo\Services\ReadinessWorkspace;
use Learmingo\Core\Csrf;
use Learmingo\Core\Response;
$workspaceStore = static fn(): ReadinessWorkspace => new ReadinessWorkspace(dirname(__DIR__).'/storage/readiness/items.json');
$router->get('/admin/readiness', static function () use ($requireRole,$render,$workspaceStore): void {
    $requireRole('admin');
    header('Cache-Control: no-store');
    $render('readiness/index', ['items'=>$workspaceStore()->run(), 'views'=>ReadinessWorkspace::VIEWS]);
});
$router->post('/admin/readiness', static function () use ($requireRole,$workspaceStore): void {
    $user=$requireRole('admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request',419); return; }
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    try { echo json_encode(['items'=>$workspaceStore()->run($_POST,(int)$user['id'])], JSON_THROW_ON_ERROR); }
    catch (\InvalidArgumentException $error) { http_response_code(422); echo json_encode(['error'=>$error->getMessage()]); }
});
