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
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not found</title></head><body><main class="container py-5"><h1>404</h1><p>Page not found.</p></main></body></html>', 404);
            return;
        }
        $handler();
    }
}
