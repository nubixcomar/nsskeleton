<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * Backup y restauración del sistema (archivos) y la base de datos (SQL), en PHP puro.
 * Sin dependencias de mysqldump. Los backups viven en system/storage/backups.
 */
final class Backup
{
    /** Carpetas/segmentos excluidos del backup de archivos. */
    private const EXCLUDE = [
        '/.git/',
        '/vendor/',
        '/node_modules/',
        '/storage/backups/',
        '/storage/cache/',
    ];

    public static function dir(): string
    {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function root(): string
    {
        return defined('PROJECT_PATH') ? PROJECT_PATH : dirname(BASE_PATH);
    }

    // ---------------------------------------------------------------- Crear

    /** @return array{ok:bool,file?:string,size?:int,error?:string} */
    public static function createDatabaseBackup(): array
    {
        try {
            $pdo = Database::connection();
            $cfg = require BASE_PATH . '/config/database.php';

            $sql = "-- Backup de `{$cfg['name']}` — " . date('Y-m-d H:i:s') . "\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC) ?: [];
                $ddl = $create['Create Table'] ?? $create['Create View'] ?? '';

                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n\n";

                if (!isset($create['Create Table'])) {
                    continue; // es una vista; no tiene filas
                }

                $rows = $pdo->query("SELECT * FROM `{$table}`");
                while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $cols = implode(', ', array_map(static fn ($c): string => "`{$c}`", array_keys($row)));
                    $vals = implode(', ', array_map(
                        static fn ($v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    ));
                    $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
                }
                $sql .= "\n";
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

            $file = 'db_' . date('Ymd_His') . '.sql';
            file_put_contents(self::dir() . '/' . $file, $sql);
            $size = (int) filesize(self::dir() . '/' . $file);

            self::log('db', $file, $size, 'ok', null);
            return ['ok' => true, 'file' => $file, 'size' => $size];
        } catch (Throwable $e) {
            self::log('db', null, null, 'failed', $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok:bool,file?:string,size?:int,error?:string} */
    public static function createFilesBackup(): array
    {
        try {
            if (!class_exists(ZipArchive::class)) {
                throw new \RuntimeException('La extensión ZipArchive no está disponible.');
            }

            $file = 'files_' . date('Ymd_His') . '.zip';
            $path = self::dir() . '/' . $file;

            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el archivo ZIP.');
            }

            $root = self::root();
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $entry) {
                if (!$entry->isFile()) {
                    continue;
                }
                $abs = str_replace('\\', '/', $entry->getPathname());
                foreach (self::EXCLUDE as $skip) {
                    if (str_contains($abs, $skip)) {
                        continue 2;
                    }
                }
                $rel = ltrim(str_replace(str_replace('\\', '/', $root), '', $abs), '/');
                $zip->addFile($entry->getPathname(), $rel);
            }

            $zip->close();
            $size = (int) filesize($path);

            self::log('files', $file, $size, 'ok', null);
            return ['ok' => true, 'file' => $file, 'size' => $size];
        } catch (Throwable $e) {
            self::log('files', null, null, 'failed', $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------- Restaurar

    /** @return array{ok:bool,error?:string} */
    public static function restoreDatabase(string $name): array
    {
        $path = self::safePath($name);
        if ($path === null || !str_ends_with($path, '.sql')) {
            return ['ok' => false, 'error' => 'Archivo de backup inválido.'];
        }
        try {
            $sql = file_get_contents($path) ?: '';
            Database::connection()->exec($sql);
            self::log('restore', $name, null, 'ok', null);
            return ['ok' => true];
        } catch (Throwable $e) {
            self::log('restore', $name, null, 'failed', $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ----------------------------------------------------------------- Listar

    /** @return array<int,array{name:string,type:string,size:int,mtime:int}> */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.{sql,zip}', GLOB_BRACE) ?: [] as $path) {
            $out[] = [
                'name'  => basename($path),
                'type'  => str_ends_with($path, '.sql') ? 'db' : 'files',
                'size'  => (int) filesize($path),
                'mtime' => (int) filemtime($path),
            ];
        }
        usort($out, static fn ($a, $b): int => $b['mtime'] <=> $a['mtime']);
        return $out;
    }

    public static function delete(string $name): bool
    {
        $path = self::safePath($name);
        return $path !== null && @unlink($path);
    }

    /** Borra backups con más de $days días. Devuelve cuántos eliminó. */
    public static function cleanup(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        $limit = time() - ($days * 86400);
        $deleted = 0;
        foreach (self::list() as $b) {
            if ($b['mtime'] < $limit && self::delete($b['name'])) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /** Resuelve un nombre a una ruta segura dentro de la carpeta de backups (o null). */
    public static function safePath(string $name): ?string
    {
        $name = basename($name);
        $path = self::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    private static function log(string $type, ?string $file, ?int $size, string $status, ?string $message): void
    {
        try {
            Database::insert(
                'INSERT INTO backup_log (type, file, size, status, message) VALUES (?, ?, ?, ?, ?)',
                [$type, $file, $size, $status, $message === null ? null : substr($message, 0, 500)]
            );
        } catch (Throwable) {
            // Log de auditoría best-effort: no romper el backup si la DB no está.
        }
    }
}
