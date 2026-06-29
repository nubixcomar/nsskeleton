<?php

declare(strict_types=1);

namespace Core;

/**
 * Representa la petición HTTP entrante.
 */
final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Soporte de method override (formularios con _method=PUT/PATCH/DELETE).
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);

        // Normaliza el path quitando el subdirectorio donde vive index.php
        // (ej. /skeleton/system/public) para que las rutas sean relativas.
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $path = '/' . trim($uri, '/');

        // Cuerpo: JSON o form-data.
        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        return new self($method, $path, $_GET, $body, $headers);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function header(string $key, mixed $default = null): mixed
    {
        foreach ($this->headers as $name => $value) {
            if (strcasecmp($name, $key) === 0) {
                return $value;
            }
        }
        return $default;
    }

    public function isAjax(): bool
    {
        return strcasecmp((string) $this->header('X-Requested-With', ''), 'XMLHttpRequest') === 0;
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept', ''), 'application/json') || $this->isAjax();
    }
}
