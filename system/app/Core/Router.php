<?php

declare(strict_types=1);

namespace Core;

use Closure;

/**
 * Enrutador: registra rutas con parámetros {param} y despacha a controladores o closures.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,handler:mixed,params:list<string>}> */
    private array $routes = [];

    public function get(string $path, mixed $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, mixed $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, mixed $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, mixed $handler): void
    {
        $path = '/' . trim($path, '/');
        $params = [];

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex'  => '#^' . $regex . '$#',
            'handler' => $handler,
            'params' => $params,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                return $this->callHandler($route['handler'], $request, $matches);
            }
        }

        if (View::exists('errors/404')) {
            return View::render('errors/404', ['path' => $path], 'layouts/error', 404);
        }
        return Response::html($this->notFoundPage($path), 404);
    }

    private function callHandler(mixed $handler, Request $request, array $args): Response
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $handler = ['App\\Controllers\\' . $class, $method];
        }

        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0](), $handler[1]];
        }

        $result = $handler instanceof Closure
            ? $handler($request, ...array_values($args))
            : $handler[0]->{$handler[1]}($request, ...array_values($args));

        return $result instanceof Response ? $result : Response::html((string) $result);
    }

    private function notFoundPage(string $path): string
    {
        $safe = htmlspecialchars($path, ENT_QUOTES);
        return "<h1>404 — No encontrado</h1><p>No existe la ruta <code>{$safe}</code>.</p>";
    }
}
