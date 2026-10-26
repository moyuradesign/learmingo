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
        require_once $basePath . '/app/Helpers/functions.php';
        ini_set('display_errors', '0');
        set_exception_handler(static function (\Throwable $exception): void {
            error_log('Learmingo unhandled exception: ' . get_class($exception));
            if (!headers_sent()) {
                Response::html('An unexpected error occurred.', 500);
            }
        });
        Session::start();
        // PHP's session cache limiter may replace Cache-Control during session_start.
        Security::sendHeaders();
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
        if (Security::postTooLarge()) {
            Response::html('Request body too large', 413);
            return;
        }
        $this->router->dispatch(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
        );
    }
}
