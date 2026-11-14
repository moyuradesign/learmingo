<?php
declare(strict_types=1);

use Learmingo\Services\ReadinessWorkspace;
use Learmingo\Services\ReadinessSchema;
use Learmingo\Services\ReadinessTemplates;
use Learmingo\Services\ReadinessRegisterExport;
use Learmingo\Core\Csrf;
use Learmingo\Core\Response;

$workspaceStore = static fn(): ReadinessWorkspace => new ReadinessWorkspace(dirname(__DIR__).'/storage/readiness/items.json');
$workspaceTemplates = static fn(): ReadinessTemplates => new ReadinessTemplates(dirname(__DIR__).'/storage/readiness/templates.json');
$workspacePayload = static fn(): array => ['items'=>array_values($workspaceStore()->run()), 'templates'=>array_values($workspaceTemplates()->run())];

$router->get('/admin/readiness', static function () use ($requireRole, $render, $workspacePayload): void {
    $requireRole('admin');
    header('Cache-Control: no-store');
    try {
        $render('readiness/index', $workspacePayload() + ['views'=>ReadinessWorkspace::VIEWS, 'schema'=>ReadinessSchema::views()]);
    } catch (\RuntimeException | \JsonException $error) {
        error_log('Readiness workspace read failed: '.get_class($error));
        Response::html('Readiness storage is unavailable. Existing records have not been reset. Contact the server administrator.', 503);
    }
});

$router->get('/admin/readiness/data', static function () use ($requireRole, $workspacePayload): void {
    $requireRole('admin');
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=UTF-8');
    try {
        echo json_encode($workspacePayload(), JSON_THROW_ON_ERROR);
    } catch (\RuntimeException | \JsonException $error) {
        http_response_code(503);
        echo json_encode(['error'=>'Workspace storage is unavailable. Please try again later.']);
    }
});

$router->get('/admin/readiness/export', static function () use ($requireRole, $workspacePayload): void {
    $requireRole('admin');
    header('Cache-Control: no-store');
    try {
        $json = json_encode($workspacePayload() + ['exported_at'=>gmdate('c'), 'production_enabled'=>false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="learmingo-readiness.json"');
        header('X-Content-Type-Options: nosniff');
        echo $json;
    } catch (\RuntimeException | \JsonException $error) {
        Response::html('Workspace export is unavailable.', 503);
    }
});

$router->get('/admin/readiness/export-csv', static function () use ($requireRole, $workspaceStore): void {
    $requireRole('admin');
    header('Cache-Control: no-store');
    try {
        $csv = ReadinessRegisterExport::csv($workspaceStore()->run());
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="learmingo-task-register.csv"');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
    } catch (\RuntimeException | \JsonException $error) {
        Response::html('Task register export is unavailable.', 503);
    }
});

$router->post('/admin/readiness', static function () use ($requireRole, $workspaceStore, $workspaceTemplates): void {
    $user = $requireRole('admin');
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=UTF-8');
    if (!is_string($_POST['_token'] ?? null) || !Csrf::verify($_POST['_token'])) {
        http_response_code(419);
        echo json_encode(['error'=>'Your session expired. Reload the page before saving.']);
        return;
    }
    try {
        $action = ReadinessSchema::text($_POST, 'action');
        if (in_array($action, ['save-template','delete-template'], true)) {
            $result = ['templates'=>array_values($workspaceTemplates()->run($_POST, (int)$user['id']))];
        } else {
            $result = ['items'=>array_values($workspaceStore()->run($_POST, (int)$user['id']))];
        }
        echo json_encode($result, JSON_THROW_ON_ERROR);
    } catch (\InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['error'=>$error->getMessage()]);
    } catch (\RuntimeException | \JsonException $error) {
        error_log('Readiness workspace save failed: '.get_class($error));
        http_response_code(503);
        echo json_encode(['error'=>'Workspace storage is unavailable. Reload the latest records before retrying.']);
    }
});
