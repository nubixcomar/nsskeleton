<?php

declare(strict_types=1);

namespace App\Services;

use Core\Env;

/**
 * Cron master: configuración del disparador externo del cron (v1.19 fase 1).
 *
 * Portado del scheduler de nsCentral. Permite disparar el cron desde un servicio externo
 * (EasyCron o `curl` del servidor) pegando a `/cron/run?key=SECRETO` en vez de depender del
 * cron del sistema operativo — clave en hostings compartidos.
 *
 * Config en la tabla `settings` (grupo `cron`): habilitado, secreto (cifrado en reposo),
 * allowlist de IP e intervalo mínimo anti-martilleo (este último ya lo usa `CronRunner`).
 */
final class CronMaster
{
    public const KEY_ENABLED   = 'cron.master_enabled';
    public const KEY_SECRET    = 'cron.secret';
    public const KEY_IPS       = 'cron.ip_allowlist';
    public const KEY_INTERVAL  = 'cron.min_interval';

    public static function enabled(): bool
    {
        return (string) Settings::get(self::KEY_ENABLED, '0') === '1';
    }

    public static function setEnabled(bool $on): void
    {
        Settings::set(self::KEY_ENABLED, $on ? '1' : '0', 'cron');
    }

    public static function secret(): string
    {
        return (string) Settings::get(self::KEY_SECRET, '');
    }

    public static function hasSecret(): bool
    {
        return self::secret() !== '';
    }

    /** Genera/rota el secreto (32 bytes → 64 hex), cifrado en reposo. */
    public static function rotateSecret(): string
    {
        $secret = bin2hex(random_bytes(32));
        Settings::setSecret(self::KEY_SECRET, $secret, 'cron');
        return $secret;
    }

    /** Verifica el secreto provisto (constant-time). Sin secreto o vacío → rechaza. */
    public static function verifyKey(?string $provided): bool
    {
        $secret = self::secret();
        if ($secret === '' || !is_string($provided) || $provided === '') {
            return false;
        }
        return hash_equals($secret, $provided);
    }

    /** @return list<string> IPs de la allowlist (vacío = sin filtro). */
    public static function ipAllowlist(): array
    {
        $raw = (string) Settings::get(self::KEY_IPS, '');
        if (trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $ips = [];
        foreach ($parts as $p) {
            if (filter_var($p, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $p;
            }
        }
        return array_values(array_unique($ips));
    }

    public static function setIpAllowlist(string $raw): void
    {
        // Normaliza a una IP por línea (solo IPs válidas).
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $ips = [];
        foreach ($parts as $p) {
            if (filter_var($p, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $p;
            }
        }
        Settings::set(self::KEY_IPS, implode("\n", array_unique($ips)), 'cron');
    }

    /** ¿La IP está permitida? Allowlist vacía = todas. */
    public static function ipAllowed(string $ip): bool
    {
        $list = self::ipAllowlist();
        return $list === [] || in_array($ip, $list, true);
    }

    public static function minInterval(): int
    {
        return max(0, (int) Settings::get(self::KEY_INTERVAL, 60));
    }

    public static function setMinInterval(int $seconds): void
    {
        Settings::set(self::KEY_INTERVAL, (string) max(0, $seconds), 'cron');
    }

    /** URL protegida del cron master (absoluta). */
    public static function url(): string
    {
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($base === '') {
            $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $key = self::secret();
        return $base . '/cron/run?key=' . ($key !== '' ? $key : '<sin-secreto>');
    }

    /** Comando curl sugerido para el crontab del servidor (cada 5 min). */
    public static function curlCommand(): string
    {
        return '*/5 * * * * curl -fsS "' . self::url() . '"';
    }
}
