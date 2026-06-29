<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

/**
 * Base compartida por todos los drivers de tiendas.
 *
 * Aporta el cliente HTTP crudo (cURL con verificación TLS, sin desactivarla),
 * la normalización de respuestas y los métodos `get()/post()`. Cada driver
 * concreto define `platform()`, la base de la API, sus headers de auth
 * (`authHeaders()`), y el mapeo de las operaciones genéricas (productos,
 * pedidos, clientes) a sus rutas.
 *
 * No depende de la base de datos ni de la capa MVC: se puede usar desde un
 * controlador, un job de la cola o un script CLI.
 */
abstract class AbstractStoreConnector implements StoreConnector
{
    protected string $baseUrl;
    protected int $timeout;

    /** @param array<string,mixed> $credentials */
    public function __construct(
        protected array $credentials = [],
        int $timeout = 20,
    ) {
        $this->baseUrl = rtrim(trim((string) ($credentials['base_url'] ?? '')), '/');
        $this->timeout = $timeout;
    }

    /**
     * Headers de autenticación que cada driver añade a todas las requests
     * autenticadas. Los drivers que usan token dinámico (login lazy) lo
     * resuelven acá.
     * @return array<int,string>
     */
    abstract protected function authHeaders(): array;

    /**
     * Prefijo que se antepone a los paths relativos en get()/post().
     * Por defecto la base de la API; algunos drivers añaden un segmento (`/api`).
     */
    protected function apiBase(): string
    {
        return $this->baseUrl;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $this->resolveUrl($path), $query, null, $this->authHeaders());
    }

    public function post(string $path, array $body = [], array $query = []): array
    {
        return $this->request('POST', $this->resolveUrl($path), $query, $body, $this->authHeaders());
    }

    public function put(string $path, array $body = [], array $query = []): array
    {
        return $this->request('PUT', $this->resolveUrl($path), $query, $body, $this->authHeaders());
    }

    public function delete(string $path, array $query = []): array
    {
        return $this->request('DELETE', $this->resolveUrl($path), $query, null, $this->authHeaders());
    }

    /** Construye la URL absoluta de un path relativo a la API de la plataforma. */
    protected function resolveUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->apiBase() . '/' . ltrim($path, '/');
    }

    /**
     * Request HTTP cruda. Nunca lanza: normaliza todo a la forma estándar.
     *
     * @param array<string,mixed>      $query
     * @param array<string,mixed>|null $body
     * @param array<int,string>        $headers
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    protected function request(string $method, string $url, array $query = [], ?array $body = null, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            return $this->fail(0, 'cURL no disponible en este entorno.');
        }
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $allHeaders = array_merge(['Accept: application/json'], $headers);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_FOLLOWLOCATION => false,
        ];

        // CA bundle: en Windows/XAMPP php.ini suele no traer CA configurado.
        // NUNCA se desactiva la verificación TLS.
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $opts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        foreach ([
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/php/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
        ] as $ca) {
            if (is_file($ca)) {
                $opts[CURLOPT_CAINFO] = $ca;
                break;
            }
        }

        if ($body !== null) {
            $allHeaders[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }
        $opts[CURLOPT_HTTPHEADER] = $allHeaders;

        $ch = curl_init($url);
        curl_setopt_array($ch, $opts);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo  = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);

        if ($errNo !== 0) {
            return $this->fail(0, "cURL: {$errMsg}");
        }

        $data = json_decode((string) $raw, true);
        $httpOk = $status >= 200 && $status < 300;

        return [
            'ok'     => $httpOk,
            'status' => $status,
            'data'   => $data ?? (string) $raw,
            'error'  => $httpOk ? null : $this->errorFrom($status, is_array($data) ? $data : []),
        ];
    }

    /**
     * Extrae un mensaje de error legible del body. Los drivers pueden
     * sobreescribirlo si su API usa otro campo.
     * @param array<string,mixed> $data
     */
    protected function errorFrom(int $status, array $data): string
    {
        foreach (['msg', 'message', 'error', 'errors'] as $k) {
            if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') {
                return $data[$k];
            }
        }
        return "HTTP {$status}";
    }

    /** @return array{ok:false,status:int,data:null,error:string} */
    protected function fail(int $status, string $error): array
    {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $error];
    }

    // ── Operaciones genéricas con default razonable ──────────────────────────
    // Los drivers sobreescriben las que correspondan a sus rutas reales.

    public function getProduct(string|int $id, array $params = []): array
    {
        return $this->get('products/' . $id, $params);
    }

    public function getOrder(string|int $id, array $params = []): array
    {
        return $this->get('orders/' . $id, $params);
    }
}
