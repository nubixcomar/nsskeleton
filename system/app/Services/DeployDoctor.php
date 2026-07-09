<?php

declare(strict_types=1);

namespace App\Services;

use Core\Env;
use Core\Session;

/**
 * Diagnóstico de "listo para producción" (hallazgo 1.3 de la auditoría de nsCheckout).
 *
 * Valida las cosas que el desarrollo local NO ejercita y que rompieron en el deploy real:
 * docroot, versión de PHP, extensiones, permisos de `storage/`, save_path de sesión,
 * APP_KEY y `.env`. No hace cambios: solo reporta.
 *
 *   php system/console/deploy.php doctor
 */
final class DeployDoctor
{
    /** Versión mínima de PHP soportada por el core (enums, readonly, etc.). */
    public const MIN_PHP = '8.1.0';

    /** Extensiones requeridas por el core. */
    public const REQUIRED_EXT = ['pdo_mysql', 'mbstring', 'openssl', 'curl', 'zip', 'fileinfo'];

    /**
     * Ejecuta todos los chequeos y devuelve una lista de resultados.
     *
     * @return list<array{name:string, ok:bool, severity:string, detail:string}>
     */
    public static function checks(): array
    {
        $out = [];
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2) . '/system';
        $project = defined('PROJECT_PATH') ? PROJECT_PATH : dirname($base);

        // 1) Versión de PHP.
        $phpOk = version_compare(PHP_VERSION, self::MIN_PHP, '>=');
        $out[] = self::r('PHP >= ' . self::MIN_PHP, $phpOk, $phpOk ? 'error' : 'error',
            'Actual: ' . PHP_VERSION);

        // 2) Extensiones.
        foreach (self::REQUIRED_EXT as $ext) {
            $loaded = extension_loaded($ext);
            $out[] = self::r("Extensión {$ext}", $loaded, 'error',
                $loaded ? 'cargada' : 'FALTA — instalá/activá php-' . $ext);
        }

        // 3) Docroot: index.php debe estar en system/public.
        $indexOk = is_file($base . '/public/index.php');
        $out[] = self::r('Docroot = system/public', $indexOk, 'error',
            $indexOk
                ? 'front-controller en system/public/index.php'
                : 'no se encontró system/public/index.php');

        // 4) storage/ escribible (subdirs runtime).
        foreach (['backups', 'cache', 'uploads', 'logs', 'sessions'] as $d) {
            $dir = $base . '/storage/' . $d;
            $ok = is_dir($dir) && is_writable($dir);
            $out[] = self::r("storage/{$d} escribible", $ok, 'error',
                $ok ? 'ok' : 'no existe o no es escribible: ' . $dir);
        }

        // 5) save_path de sesión escribible (hallazgo 1.4).
        $sp = Session::resolveSavePath();
        $spOk = is_string($sp) && (is_writable($sp) || (!is_dir($sp) && @mkdir($sp, 0770, true)));
        $out[] = self::r('session.save_path escribible', (bool) $spOk, 'error',
            $sp === null ? 'no resuelto' : (string) $sp);

        // 6) APP_KEY presente (cifrado en reposo).
        $key = Env::get('APP_KEY', '');
        $keyOk = is_string($key) && trim($key) !== '';
        $out[] = self::r('APP_KEY seteada', $keyOk, 'error',
            $keyOk ? 'ok' : 'generá una con: php system/console/key.php');

        // 7) .env presente.
        $envOk = is_file($project . '/.env');
        $out[] = self::r('.env presente', $envOk, 'error',
            $envOk ? 'ok' : 'copiá .env.example a .env');

        // 8) APP_DEBUG=false en producción (recordatorio).
        $env = Env::get('APP_ENV', 'local');
        $debug = (bool) Env::get('APP_DEBUG', false);
        $debugOk = !($env === 'production' && $debug);
        $out[] = self::r('APP_DEBUG apagado en producción', $debugOk, 'warn',
            $debugOk ? 'ok' : 'APP_ENV=production con APP_DEBUG=true → apagá debug');

        // 9) Rewrite/try_files (no detectable server-side): recordatorio.
        $out[] = self::r('Rewrite del server configurado', true, 'info',
            'nginx: usá try_files (ver docs/deploy/nginx.conf.example). Apache: .htaccess ya incluido.');

        return $out;
    }

    /**
     * ¿Pasan todos los chequeos de severidad 'error'?
     */
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

    /** @return array{name:string, ok:bool, severity:string, detail:string} */
    private static function r(string $name, bool $ok, string $severity, string $detail): array
    {
        return ['name' => $name, 'ok' => $ok, 'severity' => $severity, 'detail' => $detail];
    }
}
