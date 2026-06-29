<?php

declare(strict_types=1);

namespace Core;

/**
 * Cifrado simétrico autenticado (AES-256-GCM) para secretos en reposo.
 * La clave sale de APP_KEY (.env), en formato `base64:<32 bytes en base64>`.
 *
 * Si no hay APP_KEY válida, encrypt() devuelve el texto plano (degradación
 * controlada) — conviene generar la clave con `php system/console/key.php`.
 */
final class Crypto
{
    private const MARKER = 'enc::';
    private const CIPHER = 'aes-256-gcm';

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public static function key(): ?string
    {
        $k = (string) Env::get('APP_KEY', '');
        if ($k === '') {
            return null;
        }
        if (str_starts_with($k, 'base64:')) {
            $k = substr($k, 7);
        }
        $raw = base64_decode($k, true);
        return ($raw !== false && strlen($raw) === 32) ? $raw : null;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::MARKER);
    }

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        if ($key === null) {
            return $plain; // sin clave: no cifra
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return $plain;
        }
        return self::MARKER . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        if (!self::isEncrypted($payload)) {
            return $payload;
        }
        $key = self::key();
        if ($key === null) {
            return null;
        }
        $raw = base64_decode(substr($payload, strlen(self::MARKER)), true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /** Descifra si el valor está cifrado; si no, lo devuelve tal cual. */
    public static function maybeDecrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return self::isEncrypted($value) ? (self::decrypt($value) ?? '') : $value;
    }
}
