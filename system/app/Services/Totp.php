<?php

declare(strict_types=1);

namespace App\Services;

/**
 * TOTP (RFC 6238) sin dependencias: secreto base32, código de 6 dígitos, ventana de tiempo.
 * Compatible con Google Authenticator, Authy, etc.
 */
final class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32encode(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $time ??= time();
        return self::hotp($secret, intdiv($time, self::PERIOD));
    }

    public static function verify(string $secret, string $code, ?int $time = null, int $window = 1): bool
    {
        $code = trim($code);
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }
        $time ??= time();
        $counter = intdiv($time, self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $label, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&period=' . self::PERIOD
            . '&digits=' . self::DIGITS;
    }

    private static function hotp(string $secret, int $counter): string
    {
        $key = self::base32decode($secret);
        $bin = pack('N*', 0) . pack('N*', $counter); // contador de 8 bytes big-endian
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0xf;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        $code = $value % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function base32encode(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function base32decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $bits = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
