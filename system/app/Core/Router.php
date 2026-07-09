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

    /** Índice method+path → posición en $routes, para override (last-wins). */
    private array $index = [];

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

    /**
     * Registra una ruta. **Override last-wins:** si ya existe una ruta con el mismo
     * `method`+`path`, la nueva la REEMPLAZA (misma posición). Así, cargando las rutas
     * de la app DESPUÉS de las del core, la app puede pisar una ruta del core (incluida
     * la home `/`) sin tocar el core. (Corrige el hallazgo 1.2 de la auditoría de nsCheckout,
     * donde el router antiguo hacía first-match-wins y la app nunca ganaba.)
     */
    public function add(string $method, string $path, mixed $handler): void
    {
        $method = strtoupper($method);
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

        $route = [
            'method' => $method,
            'regex'  => '#^' . $regex . '$#',
            'handler' => $handler,
            'params' => $params,
        ];

        $key = $method . ' ' . $path;
        if (isset($this->index[$key])) {
            $this->routes[$this->index[$key]] = $route; // override en su lugar
            return;
        }
        $this->index[$key] = count($this->routes);
        $this->routes[] = $route;
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
