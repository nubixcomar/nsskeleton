<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cliente HTTP del core (cURL con fallback a stream context). Nunca lanza.
 *
 * v1.18 #1 (hallazgo 1.10): centraliza el patrón de "poster inyectable" que los connectors y
 * pasarelas duplicaban. Expone GET/POST/`request()` con timeouts, retries y decodificación
 * JSON, y un **transporte inyectable** (`setTransport`) para testear sin red.
 */
final class Http
{
    /** @var (callable(string,string,?string,array<int,string>,int):array{status:int,body:string,error:string})|null */
    private static $transport = null;

    /**
     * Inyecta un transporte (para tests): fn(method,url,body,headers,timeout) => {status,body,error}.
     * @param (callable(string,string,?string,array<int,string>,int):array{status:int,body:string,error:string})|null $t
     */
    public static function setTransport(?callable $t): void
    {
        self::$transport = $t;
    }

    public static function resetTransport(): void
    {
        self::$transport = null;
    }

    /**
     * Request genérico con retries. Reintenta ante fallo de conexión (status 0) o 5xx.
     *
     * @param array{json?:mixed,body?:string,headers?:array<int,string>,timeout?:int,retries?:int,backoff_ms?:int} $opts
     * @return array{ok:bool,status:int,body:string,data:mixed,error:string,attempts:int}
     */
    public static function request(string $method, string $url, array $opts = []): array
    {
        $headers = $opts['headers'] ?? [];
        $timeout = (int) ($opts['timeout'] ?? 30);
        $retries = max(0, (int) ($opts['retries'] ?? 0));
        $backoff = max(0, (int) ($opts['backoff_ms'] ?? 200));

        $body = $opts['body'] ?? null;
        if (array_key_exists('json', $opts)) {
            $body = json_encode($opts['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
        }

        $attempt = 0;
        $res = ['status' => 0, 'body' => '', 'error' => ''];
        while ($attempt <= $retries) {
            $attempt++;
            $res = self::send(strtoupper($method), $url, $body, $headers, $timeout);
            $retriable = $res['status'] === 0 || $res['status'] >= 500;
            if (!$retriable || $attempt > $retries) {
                break;
            }
            if ($backoff > 0) {
                usleep($backoff * 1000 * $attempt); // backoff lineal
            }
        }

        $data = $res['body'] !== '' ? json_decode($res['body'], true) : null;
        return [
            'ok'       => $res['status'] >= 200 && $res['status'] < 300,
            'status'   => $res['status'],
            'body'     => $res['body'],
            'data'     => $data,
            'error'    => $res['error'] !== '' ? $res['error'] : ($res['status'] >= 400 ? 'HTTP ' . $res['status'] : ''),
            'attempts' => $attempt,
        ];
    }

    /**
     * GET con decodificación JSON.
     * @param array<int,string> $headers
     * @return array{ok:bool,status:int,error:string,data:mixed}
     */
    public static function get(string $url, array $headers = [], int $timeout = 30): array
    {
        $r = self::request('GET', $url, ['headers' => $headers, 'timeout' => $timeout]);
        return ['ok' => $r['ok'], 'status' => $r['status'], 'error' => $r['error'], 'data' => $r['data']];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $headers
     * @return array{ok:bool,status:int,error:string,data:mixed}
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $allHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
        $res = self::send('POST', $url, $body, $allHeaders, $timeout);
        $data = json_decode($res['body'], true);
        return [
            'ok'     => $res['status'] >= 200 && $res['status'] < 300,
            'status' => $res['status'],
            'error'  => $res['error'] !== '' ? $res['error'] : ($res['status'] >= 400 ? 'HTTP ' . $res['status'] : ''),
            'data'   => $data,
        ];
    }

    /**
     * POST de un cuerpo crudo (string) con headers dados. Para webhooks (firma exacta).
     * @param array<int,string> $headers
     * @return array{ok:bool,status:int,body:string,error:string}
     */
    public static function postRaw(string $url, string $body, array $headers = [], int $timeout = 10): array
    {
        $res = self::send('POST', $url, $body, $headers, $timeout);
        return [
            'ok'     => $res['status'] >= 200 && $res['status'] < 300,
            'status' => $res['status'],
            'body'   => $res['body'],
            'error'  => $res['error'] !== '' ? $res['error'] : ($res['status'] >= 400 ? 'HTTP ' . $res['status'] : ''),
        ];
    }

    /**
     * Ejecuta el request vía el transporte inyectado o el default (cURL/stream).
     * @param array<int,string> $headers
     * @return array{status:int,body:string,error:string}
     */
    private static function send(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        if (self::$transport !== null) {
            return (self::$transport)($method, $url, $body, $headers, $timeout);
        }
        return self::defaultTransport($method, $url, $body, $headers, $timeout);
    }

    /**
     * Transporte por defecto: cURL con fallback a stream context.
     * @param array<int,string> $headers
     * @return array{status:int,body:string,error:string}
     */
    private static function defaultTransport(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_CUSTOMREQUEST   => $method,
                CURLOPT_HTTPHEADER      => $headers,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_TIMEOUT         => $timeout,
                CURLOPT_CONNECTTIMEOUT  => min(10, $timeout),
                CURLOPT_SSL_VERIFYPEER  => true,
                CURLOPT_SSL_VERIFYHOST  => 2,
            ];
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
            curl_setopt_array($ch, $opts);
            $resp = curl_exec($ch);
            if ($resp === false) {
                $err = curl_error($ch);
                curl_close($ch);
                return ['status' => 0, 'body' => '', 'error' => $err ?: 'Fallo de conexión'];
            }
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['status' => $status, 'body' => (string) $resp, 'error' => ''];
        }

        $ctx = stream_context_create(['http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'content'       => $body ?? '',
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return ['status' => 0, 'body' => '', 'error' => 'Fallo de conexión'];
        }
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        return ['status' => $status, 'body' => (string) $resp, 'error' => ''];
    }

    /**
     * POST en streaming: llama a $onLine por cada línea recibida (SSE). Sin transporte inyectable.
     * @param array<string,mixed> $payload
     * @param array<int,string> $headers
     * @return array{ok:bool,status:int,error:string}
     */
    public static function postStream(string $url, array $payload, array $headers, callable $onLine, int $timeout = 60): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'error' => 'cURL no disponible para streaming.'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $allHeaders = array_merge(['Content-Type: application/json', 'Accept: text/event-stream'], $headers);
        $buffer = '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_WRITEFUNCTION  => static function ($ch, string $chunk) use (&$buffer, $onLine): int {
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $onLine($line);
                }
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        if ($buffer !== '') {
            $onLine($buffer);
        }
        if ($ok === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'status' => 0, 'error' => $err ?: 'Fallo de conexión (stream).'];
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'error' => $status >= 400 ? ('HTTP ' . $status) : ''];
    }
}
