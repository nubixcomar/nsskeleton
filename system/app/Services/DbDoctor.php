<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;
use Throwable;

/**
 * Diagnóstico de base de datos (v1.18 #3). Deriva del "otros (menores)" de la auditoría:
 * el cliente MariaDB de XAMPP no autentica contra MySQL 8 (`caching_sha2_password`); un
 * `db:doctor` evita el diagnóstico manual.
 *
 *   php system/console/db.php doctor
 */
final class DbDoctor
{
    /**
     * @return list<array{name:string, ok:bool, severity:string, detail:string}>
     */
    public static function checks(): array
    {
        $out = [];

        // 1) Driver PDO MySQL disponible.
        $hasPdo = extension_loaded('pdo');
        $hasDriver = $hasPdo && in_array('mysql', PDO::getAvailableDrivers(), true);
        $out[] = self::r('Driver pdo_mysql disponible', $hasDriver, 'error',
            $hasDriver ? 'ok' : 'falta la extensión pdo_mysql');

        // 2) Conexión.
        $connected = false;
        $pdo = null;
        try {
            $pdo = Database::connection();
            $connected = true;
        } catch (Throwable $e) {
            $out[] = self::r('Conexión a la base', false, 'error', self::friendlyError($e->getMessage()));
        }

        if (!$connected || !$pdo instanceof PDO) {
            return $out; // sin conexión no tiene sentido seguir
        }
        $out[] = self::r('Conexión a la base', true, 'error', 'ok');

        // 3) Versión del servidor + aviso MySQL 8 / caching_sha2_password.
        try {
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        } catch (Throwable) {
            $version = '';
        }
        $isMysql8 = (bool) preg_match('/^8\./', $version);
        $out[] = self::r('Versión del servidor', $version !== '', $version !== '' ? 'info' : 'warn',
            $version !== '' ? $version : 'no se pudo determinar');
        if ($isMysql8) {
            // Si llegamos acá ya autenticamos (mysqlnd soporta caching_sha2_password).
            $out[] = self::r('Auth MySQL 8 (caching_sha2_password)', true, 'info',
                'autenticado OK vía mysqlnd (el cliente MariaDB de XAMPP fallaría acá)');
        }

        // 4) Charset utf8mb4.
        try {
            $charset = (string) $pdo->query("SHOW VARIABLES LIKE 'character_set_database'")->fetch()['Value'] ?? '';
        } catch (Throwable) {
            $charset = '';
        }
        $csOk = str_starts_with($charset, 'utf8mb4') || $charset === '';
        $out[] = self::r('Charset utf8mb4', $csOk, 'warn',
            $charset !== '' ? $charset : 'no verificable');

        // 5) ¿Corrieron las migraciones? (tabla settings presente).
        $hasSettings = false;
        try {
            $pdo->query('SELECT 1 FROM settings LIMIT 1');
            $hasSettings = true;
        } catch (Throwable) {
            $hasSettings = false;
        }
        $out[] = self::r('Migraciones aplicadas (tabla settings)', $hasSettings, 'warn',
            $hasSettings ? 'ok' : 'corré: php system/database/migrate.php');

        return $out;
    }

    public static function ok(?array $checks = null): bool
    {
        $checks ??= self::checks();
        foreach ($checks as $c) {
            if (($c['severity'] ?? 'error') === 'error' && !($c['ok'] ?? false)) {
                return false;
            }
        }
        return true;
    }

    private static function friendlyError(string $msg): string
    {
        if (str_contains($msg, 'caching_sha2') || str_contains($msg, 'Authentication method')) {
            return 'Fallo de auth: parece MySQL 8 con caching_sha2_password y un cliente incompatible. Usá PDO/mysqlnd (no el cliente MariaDB de XAMPP).';
        }
        return $msg;
    }

    /** @return array{name:string, ok:bool, severity:string, detail:string} */
    private static function r(string $name, bool $ok, string $severity, string $detail): array
    {
        return ['name' => $name, 'ok' => $ok, 'severity' => $severity, 'detail' => $detail];
    }
}
