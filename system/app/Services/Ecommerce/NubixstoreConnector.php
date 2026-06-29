<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

/**
 * Driver de la API de **nubixstore** (CakePHP 2). Es el driver de referencia,
 * el más completo: el skeleton está pensado en primera instancia para construir
 * software que se integre con nubixstore.
 *
 * Manual de la API: `agentic/knowledge/nubixstore/manual-api-nubixstore.md`
 * (mantenido por el agente `api-nubixstore`). Skill: `nubixstore-api`.
 *
 * Modelo de auth (manual §2):
 *   1. POST {base}/usuarios/token  con { USER, PASSWORD, API_KEY }  → access_token + expires_in
 *   2. Endpoints api_*: GET/POST {base}/api/{ctrl}/{fn}  con header Authorization: Bearer {token}
 *
 * `{base}` es la URL de la tienda SIN `/api` (p. ej. https://www.cliente.com.ar).
 * Maneja login lazy + cacheo del token (renueva al expirar).
 *
 * Credenciales esperadas (array $credentials):
 *   base_url  → URL de la tienda sin /api
 *   user      → login del usuario API   (USER)
 *   password  → contraseña en texto plano (PASSWORD; la API la hashea con md5)
 *   api_key   → API Key del entorno      (API_KEY)
 */
final class NubixstoreConnector extends AbstractStoreConnector
{
    /** Versión del manual de la API contra la que está escrito este driver. */
    public const API_MANUAL_VERSION = '2.1';

    private ?string $token = null;
    private int $tokenExpiresAt = 0; // timestamp UNIX

    public function platform(): string
    {
        return 'nubixstore';
    }

    /** Los endpoints api_* cuelgan de {base}/api. */
    protected function apiBase(): string
    {
        return $this->baseUrl . '/api';
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    /**
     * Obtiene un access_token (manual §2.2) y lo cachea con su expiración.
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function login(): array
    {
        $res = $this->request('POST', $this->baseUrl . '/usuarios/token', [], [
            'USER'     => (string) ($this->credentials['user'] ?? ''),
            'PASSWORD' => (string) ($this->credentials['password'] ?? ''),
            'API_KEY'  => (string) ($this->credentials['api_key'] ?? ''),
        ]);

        // El login responde HTTP 200 incluso con credenciales inválidas (el error
        // va en el body). El único indicador confiable de éxito es access_token.
        $body  = is_array($res['data']) ? $res['data'] : [];
        $token = $body['access_token'] ?? null;
        if (is_string($token) && $token !== '') {
            $this->token          = $token;
            $this->tokenExpiresAt = (int) ($body['expires_in'] ?? (time() + 1800));
            return ['ok' => true, 'status' => $res['status'], 'data' => $body, 'error' => null];
        }

        return [
            'ok'     => false,
            'status' => $res['status'],
            'data'   => $body,
            'error'  => (string) ($body['msg'] ?? $res['error'] ?? 'Login fallido.'),
        ];
    }

    /** Garantiza un token vigente (login lazy + renovación 30s antes de expirar). */
    private function ensureToken(): bool
    {
        if ($this->token !== null && $this->tokenExpiresAt > time() + 30) {
            return true;
        }
        return $this->login()['ok'];
    }

    /** @return array<int,string> */
    protected function authHeaders(): array
    {
        return $this->token !== null ? ['Authorization: Bearer ' . $this->token] : [];
    }

    /**
     * Override de get()/post() para asegurar el token antes de cada llamada
     * a un endpoint api_*.
     */
    public function get(string $path, array $query = []): array
    {
        if (!$this->ensureToken()) {
            return $this->fail(401, 'No se pudo autenticar (login fallido).');
        }
        return parent::get($path, $query);
    }

    public function post(string $path, array $body = [], array $query = []): array
    {
        if (!$this->ensureToken()) {
            return $this->fail(401, 'No se pudo autenticar (login fallido).');
        }
        return parent::post($path, $body, $query);
    }

    /**
     * La API de nubixstore es inconsistente: algunos endpoints usan status numérico
     * y code 900 para éxito, otros "EXITO"/"ERROR". El indicador confiable es HTTP
     * 2xx salvo que el body diga explícitamente status: "ERROR".
     * @param array<string,mixed> $data
     */
    protected function errorFrom(int $status, array $data): string
    {
        return (string) ($data['msg'] ?? "HTTP {$status}");
    }

    // ── Operaciones genéricas → rutas nubixstore ─────────────────────────────

    /** Health check de conectividad (manual §5.18 `GET /api/utils/connect`). */
    public function ping(): array
    {
        return $this->get('utils/connect');
    }

    /** Catálogo (manual §5.1 `GET /api/articulos/items`). */
    public function getProducts(array $filters = []): array
    {
        return $this->get('articulos/items', $filters);
    }

    /** Un artículo por id/sku/code (manual §5.1 `GET /api/articulos/item`). */
    public function getProduct(string|int $id, array $params = []): array
    {
        return $this->get('articulos/item', array_merge(['id' => $id], $params));
    }

    /** Stock actual (manual §5.1 `GET /api/articulos/stock`). */
    public function getStock(array $filters = []): array
    {
        return $this->get('articulos/stock', $filters);
    }

    /**
     * Lista de órdenes con filtros (manual §5.14 `GET /api/pedidos/orders`).
     * @param array{page?:int,limit?:int,status?:string,from?:string,to?:string,marketplace?:string} $filters
     */
    public function getOrders(array $filters = []): array
    {
        return $this->get('pedidos/orders', $filters);
    }

    /** Detalle de una orden (manual §5.14 `GET /api/pedidos/order/:id`). */
    public function getOrder(string|int $id, array $params = []): array
    {
        return $this->get('pedidos/order/' . $id, $params);
    }

    /** Clientes de la tienda (manual §5.17 `GET /api/usuarios/clients`). */
    public function getCustomers(array $filters = []): array
    {
        return $this->get('usuarios/clients', $filters);
    }
}
