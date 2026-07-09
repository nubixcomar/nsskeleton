<?php

declare(strict_types=1);

namespace Core\Webhooks;

use Core\Security\Hmac;

/**
 * Verificación de firma de webhooks ENTRANTES (v1.18 #2, hallazgo 1.10).
 *
 * El core ya firma los salientes (`App\Services\Webhook::sign`), pero cada connector/pasarela
 * verificaba el HMAC entrante a mano (Mercado Pago/Stripe `x-signature`). Es la capa
 * especializada en webhooks sobre la primitiva `Core\Security\Hmac`. Soporta:
 *  - firma simple estilo GitHub: `sha256=<hex>` (round-trip con `Webhook::sign`);
 *  - firma con timestamp estilo Stripe/Mercado Pago: `t=...,v1=...` con manifest configurable.
 */
final class SignatureVerifier
{
    /** Firma canónica de un payload: `<algo>=<hexhmac>` (igual que Webhook::sign). */
    public static function sign(string $payload, string $secret, string $algo = 'sha256'): string
    {
        return $algo . '=' . Hmac::sign($payload, $secret, $algo);
    }

    /**
     * Verifica una firma simple. Acepta el hex crudo o con prefijo `sha256=`.
     */
    public static function verify(string $payload, string $signature, string $secret, string $algo = 'sha256'): bool
    {
        return Hmac::verify($payload, $signature, $secret, $algo);
    }

    /**
     * Verifica una firma con timestamp (Stripe / Mercado Pago).
     *
     * El header trae pares `k=v` separados por coma, p. ej. `t=1699999999,v1=abcd...`.
     * Se calcula el HMAC sobre un template con `{ts}` y `{payload}` y se compara constant-time;
     * opcionalmente se valida que el timestamp esté dentro de `tolerance` segundos.
     *
     * @param array{ts_key?:string,sig_key?:string,template?:string,tolerance?:int,algo?:string,now?:int} $opts
     */
    public static function verifyTimestamped(string $payload, string $header, string $secret, array $opts = []): bool
    {
        if ($header === '' || $secret === '') {
            return false;
        }
        $tsKey     = (string) ($opts['ts_key'] ?? 't');
        $sigKey    = (string) ($opts['sig_key'] ?? 'v1');
        $template  = (string) ($opts['template'] ?? '{ts}.{payload}');
        $tolerance = (int) ($opts['tolerance'] ?? 300);
        $algo      = (string) ($opts['algo'] ?? 'sha256');
        $now       = (int) ($opts['now'] ?? time());

        $parts = self::parseHeader($header);
        $ts  = $parts[$tsKey] ?? null;
        $sig = $parts[$sigKey] ?? null;
        if ($ts === null || $sig === null) {
            return false;
        }

        if ($tolerance > 0 && abs($now - (int) $ts) > $tolerance) {
            return false; // fuera de ventana → replay
        }

        $signed = strtr($template, ['{ts}' => $ts, '{payload}' => $payload]);
        return Hmac::equals(Hmac::sign($signed, $secret, $algo), $sig);
    }

    /**
     * Parsea un header de pares `k=v` separados por coma (o punto y coma).
     * @return array<string,string>
     */
    private static function parseHeader(string $header): array
    {
        $out = [];
        foreach (preg_split('/[,;]/', $header) ?: [] as $pair) {
            $pair = trim($pair);
            if ($pair === '' || !str_contains($pair, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $pair, 2);
            $out[trim($k)] = trim($v);
        }
        return $out;
    }
}
