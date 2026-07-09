<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Estado de un dominio: resolución DNS + certificado TLS. Extracción genérica de nsCheckout
 * (`DomainStatus`): útil para cualquier app con dominios propios (verificar apuntamiento y HTTPS).
 *
 * Los probes DNS/TLS son inyectables (para testear sin red).
 */
final class DomainStatus
{
    /**
     * @param (callable(string):array{resolved:bool,ips:list<string>})|null $dnsProbe
     * @param (callable(string):array{valid:bool,expires:?string,issuer:?string,days_left:?int,error:?string})|null $tlsProbe
     * @return array{domain:string,dns:array{resolved:bool,ips:list<string>},tls:array{valid:bool,expires:?string,issuer:?string,days_left:?int,error:?string},ok:bool}
     */
    public static function check(string $domain, ?callable $dnsProbe = null, ?callable $tlsProbe = null): array
    {
        $domain = self::normalize($domain);
        $dns = ($dnsProbe ?? [self::class, 'probeDns'])($domain);
        $tls = ($tlsProbe ?? [self::class, 'probeTls'])($domain);

        return [
            'domain' => $domain,
            'dns'    => $dns,
            'tls'    => $tls,
            'ok'     => ($dns['resolved'] ?? false) && ($tls['valid'] ?? false),
        ];
    }

    /** Normaliza: quita esquema, path, puerto y espacios; a minúsculas. */
    public static function normalize(string $domain): string
    {
        $d = trim(strtolower($domain));
        $d = preg_replace('#^[a-z]+://#', '', $d) ?? $d; // quita http(s)://
        $d = explode('/', $d)[0];                        // quita path
        $d = explode(':', $d)[0];                        // quita puerto
        return $d;
    }

    /** @return array{resolved:bool,ips:list<string>} */
    public static function probeDns(string $domain): array
    {
        $ips = @gethostbynamel($domain);
        $ips = is_array($ips) ? array_values($ips) : [];
        return ['resolved' => $ips !== [], 'ips' => $ips];
    }

    /** @return array{valid:bool,expires:?string,issuer:?string,days_left:?int,error:?string} */
    public static function probeTls(string $domain, ?int $now = null): array
    {
        $fail = static fn (string $e): array => [
            'valid' => false, 'expires' => null, 'issuer' => null, 'days_left' => null, 'error' => $e,
        ];

        if (!function_exists('stream_socket_client') || !function_exists('openssl_x509_parse')) {
            return $fail('TLS no verificable (falta openssl/stream).');
        }

        $ctx = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'SNI_enabled'       => true,
        ]]);

        $errno = 0;
        $errstr = '';
        $client = @stream_socket_client(
            'ssl://' . $domain . ':443',
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if ($client === false) {
            return $fail($errstr !== '' ? $errstr : 'No se pudo conectar por TLS.');
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($cert === null) {
            return $fail('Sin certificado.');
        }

        $parsed = openssl_x509_parse($cert);
        if (!is_array($parsed)) {
            return $fail('Certificado ilegible.');
        }

        $now ??= time();
        $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
        $validFrom = (int) ($parsed['validFrom_time_t'] ?? 0);
        $valid = $now >= $validFrom && $now <= $validTo;
        $daysLeft = $validTo > 0 ? (int) floor(($validTo - $now) / 86400) : null;
        $issuer = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null);

        return [
            'valid'     => $valid,
            'expires'   => $validTo > 0 ? date('Y-m-d', $validTo) : null,
            'issuer'    => is_string($issuer) ? $issuer : null,
            'days_left' => $daysLeft,
            'error'     => $valid ? null : 'Certificado fuera de vigencia.',
        ];
    }
}
