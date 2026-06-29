<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cliente HTTP minimalista para JSON (cURL con fallback a stream context).
 * Nunca lanza: devuelve ['ok','status','error','data'].
 */
final class Http
{
    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $headers
     * @return array{ok:bool,status:int,error:string,data:mixed}
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $allHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $allHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            ]);
            $resp = curl_exec($ch);
            if ($resp === false) {
                $err = curl_error($ch);
                curl_close($ch);
                return ['ok' => false, 'status' => 0, 'error' => $err ?: 'Fallo de conexión', 'data' => null];
            }
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $allHeaders),
                'content'       => $body,
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($url, false, $ctx);
            if ($resp === false) {
                return ['ok' => false, 'status' => 0, 'error' => 'Fallo de conexión', 'data' => null];
            }
            $status = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
        }

        $data = json_decode((string) $resp, true);
        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'error'  => $status >= 400 ? ('HTTP ' . $status) : '',
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
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL no disponible.'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $err ?: 'Fallo de conexión.'];
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'body'   => (string) $resp,
            'error'  => $status >= 400 ? ('HTTP ' . $status) : '',
        ];
    }

    /**
     * POST en streaming: llama a $onLine por cada línea recibida (SSE).
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
