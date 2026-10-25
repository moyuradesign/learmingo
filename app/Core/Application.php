<?php
declare(strict_types=1);

namespace Learmingo\Core;

final class Application
{
    private Router $router;

    private function __construct(private readonly string $basePath)
    {
        $this->router = new Router();
    }

    public static function boot(string $basePath): self
    {
        require_once $basePath . '/bootstrap/autoload.php';
        require_once $basePath . '/config/app.php';
        require_once $basePath . '/config/database.php';
        require_once $basePath . '/app/Core/Router.php';
        require_once $basePath . '/app/Core/Database.php';
        require_once $basePath . '/app/Core/Response.php';
        require_once $basePath . '/app/Helpers/functions.php';

        $app = new self($basePath);
        require $basePath . '/routes/web.php';
        return $app;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function run(): void
    {
        $this->router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    }
}
