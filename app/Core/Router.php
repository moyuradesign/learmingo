<?php
declare(strict_types=1);

namespace Learmingo\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
            Response::html('Method not allowed', 405);
            return;
        }
        $method = $method === 'HEAD' ? 'GET' : $method;
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            $allowed = [];
            foreach ($this->routes as $verb => $paths) {
                if (isset($paths[$path])) {
                    $allowed[] = $verb;
                }
            }
            if ($allowed) {
                header('Allow: ' . implode(', ', $allowed));
                Response::html('Method not allowed', 405);
                return;
            }
            Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not found</title></head><body><main><h1>404</h1><p>Page not found.</p></main></body></html>', 404);
            return;
        }
        $handler();
    }
}
