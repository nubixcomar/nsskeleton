<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;

/**
 * Motor de migraciones con soporte de rollback.
 * Cada archivo .sql puede tener una sección de reversa tras una línea `-- @DOWN`:
 *
 *   CREATE TABLE ...;
 *   -- @DOWN
 *   DROP TABLE IF EXISTS ...;
 *
 * El estado se guarda en `schema_migrations`.
 *
 * Separación core/app: las migraciones del **core** viven en
 * `database/migrations/` (las pisa el actualizador) y las de la **app** en
 * `database/migrations/app/` (no las toca el update). `migrate()` (sin dir)
 * aplica primero las del core y luego las de la app; el actualizador de core usa
 * `migrateCore()`. Así no colisionan y un update solo corre lo nuevo del core.
 */
final class Migrator
{
    /** Directorio de migraciones del CORE. */
    public static function dir(): string
    {
        return BASE_PATH . '/database/migrations';
    }

    /** Directorio de migraciones de la APP (no lo toca el actualizador de core). */
    public static function appDir(): string
    {
        return BASE_PATH . '/database/migrations/app';
    }

    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration  VARCHAR(255) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Rutas de archivos .sql ordenadas. Con `$dir` explícito, solo ese directorio.
     * Sin `$dir` (null): primero las del core, luego las de la app (orden de aplicación).
     * @return array<int,string>
     */
    public static function files(?string $dir = null): array
    {
        if ($dir !== null) {
            $files = glob($dir . '/*.sql') ?: [];
            sort($files);
            return $files;
        }
        $core = glob(self::dir() . '/*.sql') ?: [];
        sort($core);
        $app = glob(self::appDir() . '/*.sql') ?: [];
        sort($app);
        return array_merge($core, $app);
    }

    /** Separa el SQL en secciones up/down según el marcador `-- @DOWN`. @return array{up:string,down:string} */
    public static function parse(string $path): array
    {
        $sql = is_file($path) ? (file_get_contents($path) ?: '') : '';
        $up = [];
        $down = [];
        $inDown = false;
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            if (preg_match('/^\s*--\s*@DOWN\s*$/i', $line)) {
                $inDown = true;
                continue;
            }
            $inDown ? $down[] = $line : $up[] = $line;
        }
        return ['up' => trim(implode("\n", $up)), 'down' => trim(implode("\n", $down))];
    }

    /** @return array<int,array{migration:string,applied_at:string}> */
    public static function appliedRows(): array
    {
        self::ensureTable();
        return Database::select('SELECT migration, applied_at FROM schema_migrations');
    }

    /** @return array<int,string> */
    public static function appliedNames(): array
    {
        return array_column(self::appliedRows(), 'migration');
    }

    /** Aplica las migraciones pendientes. @return array<int,string> nombres aplicados */
    public static function migrate(?string $dir = null): array
    {
        self::ensureTable();
        $applied = self::appliedNames();
        $done = [];

        foreach (self::files($dir) as $path) {
            $name = basename($path);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $parsed = self::parse($path);
            if ($parsed['up'] !== '') {
                Database::connection()->exec($parsed['up']);
            }
            Database::run('INSERT INTO schema_migrations (migration) VALUES (?)', [$name]);
            $done[] = $name;
        }
        return $done;
    }

    /** Aplica solo las migraciones del CORE (lo que corre el actualizador de core). @return array<int,string> */
    public static function migrateCore(): array
    {
        return self::migrate(self::dir());
    }

    /** Aplica solo las migraciones de la APP. @return array<int,string> */
    public static function migrateApp(): array
    {
        return self::migrate(self::appDir());
    }

    /**
     * Revierte las últimas N migraciones aplicadas.
     *  - Sin $dir (default): **global** sobre core + app, en orden inverso de aplicación
     *    (desde 1.20.3; antes revertía solo las del core aunque la última fuera de la app).
     *  - Con $dir: solo las de ese directorio (comportamiento previo).
     * @return array<int,string>
     */
    public static function rollback(int $steps = 1, ?string $dir = null): array
    {
        return self::rollbackAcross($steps, $dir !== null ? [$dir] : [self::dir(), self::appDir()]);
    }

    /**
     * Revierte las últimas N migraciones aplicadas que pertenezcan a alguno de $dirs.
     * Orden: `applied_at` DESC y, en empate (mismo segundo), la que `migrate()` aplicó después
     * (posición en la lista combinada de los $dirs, en ese orden: core antes que app).
     * @param array<int,string> $dirs
     * @return array<int,string>
     */
    public static function rollbackAcross(int $steps, array $dirs): array
    {
        self::ensureTable();
        $pos = [];
        $paths = [];
        foreach ($dirs as $d) {
            foreach (self::files($d) as $path) {
                $name = basename($path);
                if (!isset($pos[$name])) {
                    $pos[$name] = count($pos);
                    $paths[$name] = $path;
                }
            }
        }

        $rows = array_values(array_filter(
            self::appliedRows(),
            static fn (array $r): bool => isset($pos[$r['migration']])
        ));
        usort($rows, static function (array $a, array $b) use ($pos): int {
            $byTime = strcmp((string) $b['applied_at'], (string) $a['applied_at']);
            return $byTime !== 0 ? $byTime : $pos[$b['migration']] <=> $pos[$a['migration']];
        });

        $done = [];
        foreach (array_slice($rows, 0, max(0, $steps)) as $row) {
            $path = $paths[$row['migration']];
            $parsed = self::parse($path);
            if ($parsed['down'] !== '') {
                Database::connection()->exec($parsed['down']);
            }
            Database::run('DELETE FROM schema_migrations WHERE migration = ?', [$row['migration']]);
            $done[] = $row['migration'];
        }
        return $done;
    }

    /** @return array<int,array{migration:string,applied:bool,reversible:bool}> */
    public static function status(?string $dir = null): array
    {
        $applied = self::appliedNames();
        $out = [];
        foreach (self::files($dir) as $path) {
            $name = basename($path);
            $out[] = [
                'migration'  => $name,
                'applied'    => in_array($name, $applied, true),
                'reversible' => self::parse($path)['down'] !== '',
            ];
        }
        return $out;
    }
}
