<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiToken;
use App\Services\RateLimiter;
use Core\Request;
use Core\Response;

/**
 * Base de los controladores de API: autenticación por Bearer token + respuestas JSON.
 */
abstract class ApiController
{
    /**
     * Exige un token válido con el scope dado y aplica rate-limit. Devuelve el contexto
     * del token, o corta con error JSON (401/403/429).
     * @return array{admin:array<string,mixed>,scopes:array<int,string>,token_id:int}
     */
    protected function authenticate(Request $request, string $scope = 'read'): array
    {
        $header = (string) $request->header('Authorization', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $token = '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            $token = trim($m[1]);
        }

        $resolved = ApiToken::resolve($token);
        if ($resolved === null) {
            $this->fail(401, 'No autorizado. Usá un Bearer token válido.');
        }

        if (!in_array($scope, $resolved['scopes'], true)) {
            $this->fail(403, "El token no tiene el permiso requerido ('{$scope}').");
        }

        $cfg = require BASE_PATH . '/config/api.php';
        $limit = (int) ($cfg['rate_limit'] ?? 60);
        $rl = RateLimiter::hit('api:token:' . $resolved['token_id'], $limit);
        if (!$rl['allowed']) {
            $this->fail(429, 'Límite de peticiones excedido. Probá de nuevo en un minuto.');
        }

        return $resolved;
    }

    /** Emite un error JSON y corta la ejecución. */
    protected function fail(int $status, string $message): never
    {
        Response::json(['error' => $message], $status)->send();
        exit;
    }
}
