<?php

declare(strict_types=1);

namespace Core\Security;

/**
 * Primitiva genérica de firma HMAC de payloads (extracción de nsCheckout: `CartSignature`).
 *
 * Firma/verifica datos arbitrarios (strings o arrays canónicos) con comparación constant-time.
 * Es la base de más bajo nivel; `Core\Webhooks\SignatureVerifier` la usa para webhooks.
 */
final class Hmac
{
    /** Firma un string → hex. */
    public static function sign(string $data, string $secret, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $data, $secret);
    }

    /** Firma un string → base64 (útil para headers/URLs cortas). */
    public static function signBase64(string $data, string $secret, string $algo = 'sha256'): string
    {
        return base64_encode(hash_hmac($algo, $data, $secret, true));
    }

    /**
     * Verifica una firma hex de un string (constant-time). Acepta la firma con o sin
     * prefijo `algo=` (estilo GitHub `sha256=...`).
     */
    public static function verify(string $data, string $signature, string $secret, string $algo = 'sha256'): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }
        $sig = $signature;
        if (str_starts_with($sig, $algo . '=')) {
            $sig = substr($sig, strlen($algo) + 1);
        }
        return self::equals(self::sign($data, $secret, $algo), $sig);
    }

    /** Comparación constant-time segura (wrapper de hash_equals que no lanza). */
    public static function equals(string $known, string $user): bool
    {
        return $known !== '' && $user !== '' && hash_equals($known, $user);
    }

    /**
     * Serialización canónica de un array (claves ordenadas recursivamente) → JSON estable.
     * Permite firmar estructuras sin depender del orden de las claves (ej. un carrito).
     * @param array<mixed> $data
     */
    public static function canonical(array $data): string
    {
        self::ksortRecursive($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Firma un array por su forma canónica → hex.
     * @param array<mixed> $data
     */
    public static function signArray(array $data, string $secret, string $algo = 'sha256'): string
    {
        return self::sign(self::canonical($data), $secret, $algo);
    }

    /**
     * Verifica la firma de un array por su forma canónica.
     * @param array<mixed> $data
     */
    public static function verifyArray(array $data, string $signature, string $secret, string $algo = 'sha256'): bool
    {
        return self::verify(self::canonical($data), $signature, $secret, $algo);
    }

    /** @param array<mixed> $arr */
    private static function ksortRecursive(array &$arr): void
    {
        foreach ($arr as &$v) {
            if (is_array($v)) {
                self::ksortRecursive($v);
            }
        }
        unset($v);
        if (self::isAssoc($arr)) {
            ksort($arr);
        }
    }

    /** @param array<mixed> $a */
    private static function isAssoc(array $a): bool
    {
        if ($a === []) {
            return false;
        }
        return array_keys($a) !== range(0, count($a) - 1);
    }
}
