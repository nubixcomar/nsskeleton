<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Motor de actualización del core (Fase 4 del mecanismo de update).
 *
 * Compara tres estados — el lock INSTALADO (lo que el core envió la última vez),
 * el árbol LOCAL real (para detectar si la app editó archivos del core) y el lock
 * NUEVO (del paquete a instalar) — y produce un PLAN por archivo. Solo toca lo que
 * es del core (lo listado en los locks); lo de la app (untracked) nunca se toca.
 *
 * Acciones del plan:
 *   add            archivo nuevo del core → se agrega
 *   update         archivo del core sin tocar por la app → se pisa (limpio)
 *   skip           ya está idéntico al nuevo → nada
 *   conflict       la app editó un archivo del core → se deja el nuevo como `.new`
 *   conflict_add   la app creó un archivo donde ahora el core trae uno → `.new`
 *   delete         el core lo eliminó y la app no lo tocó → se borra
 *   delete_modified el core lo eliminó pero la app lo había editado → se conserva + aviso
 *
 * `apply()` respalda todo lo que va a tocar en un backupDir y escribe `applied.json`
 * para poder hacer `rollback()`. Nunca lanza por archivo: acumula errores.
 */
final class CoreUpdater
{
    public const ADD = 'add';
    public const UPDATE = 'update';
    public const SKIP = 'skip';
    public const CONFLICT = 'conflict';
    public const CONFLICT_ADD = 'conflict_add';
    public const DELETE = 'delete';
    public const DELETE_MODIFIED = 'delete_modified';

    /** Acciones que efectivamente modifican el árbol. */
    public const MUTATING = [self::ADD, self::UPDATE, self::CONFLICT, self::CONFLICT_ADD, self::DELETE];

    /**
     * Lee un archivo core-lock.json y devuelve el mapa { path => sha256 }.
     * @return array<string,string>
     */
    public static function loadLockFiles(string $lockFile): array
    {
        if (!is_file($lockFile)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($lockFile), true);
        $files = is_array($data) && isset($data['files']) && is_array($data['files']) ? $data['files'] : [];
        /** @var array<string,string> $files */
        return $files;
    }

    private static function hashFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $h = hash_file('sha256', $path);
        return $h === false ? null : $h;
    }

    /**
     * Construye el plan de actualización.
     *
     * @param array<string,string> $oldLock  lock instalado (core anterior)
     * @param array<string,string> $newLock  lock del paquete nuevo
     * @param string $installRoot  raíz del proyecto instalado
     * @param string $sourceRoot   raíz con los archivos del core nuevo
     * @return array<int,array{path:string,action:string,reason:string}>
     */
    public static function plan(array $oldLock, array $newLock, string $installRoot, string $sourceRoot): array
    {
        $installRoot = rtrim(str_replace('\\', '/', $installRoot), '/');
        $sourceRoot  = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $plan = [];

        // 1) Archivos presentes en el core nuevo.
        foreach ($newLock as $path => $newHash) {
            $local = self::hashFile($installRoot . '/' . $path);
            $old   = $oldLock[$path] ?? null;

            if ($local === null) {
                $plan[] = self::entry($path, self::ADD, 'nuevo archivo del core');
            } elseif ($local === $newHash) {
                $plan[] = self::entry($path, self::SKIP, 'ya actualizado');
            } elseif ($old !== null && $local === $old) {
                $plan[] = self::entry($path, self::UPDATE, 'archivo del core sin modificar localmente');
            } elseif ($old === null) {
                $plan[] = self::entry($path, self::CONFLICT_ADD, 'la app creó un archivo que ahora trae el core');
            } else {
                $plan[] = self::entry($path, self::CONFLICT, 'la app modificó un archivo del core');
            }
        }

        // 2) Archivos que el core viejo tenía y el nuevo ya no.
        foreach ($oldLock as $path => $oldHash) {
            if (array_key_exists($path, $newLock)) {
                continue;
            }
            $local = self::hashFile($installRoot . '/' . $path);
            if ($local === null) {
                continue; // ya no está
            }
            if ($local === $oldHash) {
                $plan[] = self::entry($path, self::DELETE, 'el core eliminó este archivo');
            } else {
                $plan[] = self::entry($path, self::DELETE_MODIFIED, 'el core lo eliminó pero la app lo editó (se conserva)');
            }
        }

        return $plan;
    }

    /** @return array{path:string,action:string,reason:string} */
    private static function entry(string $path, string $action, string $reason): array
    {
        return ['path' => $path, 'action' => $action, 'reason' => $reason];
    }

    /**
     * Resumen { action => cantidad } de un plan.
     * @param array<int,array{path:string,action:string,reason:string}> $plan
     * @return array<string,int>
     */
    public static function summarize(array $plan): array
    {
        $out = [];
        foreach ($plan as $e) {
            $out[$e['action']] = ($out[$e['action']] ?? 0) + 1;
        }
        return $out;
    }

    /**
     * Aplica el plan. Respalda lo que toca en $backupDir y escribe applied.json.
     *
     * @param array<int,array{path:string,action:string,reason:string}> $plan
     * @return array{applied:array<int,array{path:string,action:string}>,errors:array<int,string>,conflicts:array<int,string>}
     */
    public static function apply(array $plan, string $installRoot, string $sourceRoot, string $backupDir): array
    {
        $installRoot = rtrim(str_replace('\\', '/', $installRoot), '/');
        $sourceRoot  = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $backupDir   = rtrim(str_replace('\\', '/', $backupDir), '/');

        $applied = [];
        $errors = [];
        $conflicts = [];

        foreach ($plan as $e) {
            $path = $e['path'];
            $action = $e['action'];
            $dst = $installRoot . '/' . $path;
            $src = $sourceRoot . '/' . $path;

            try {
                switch ($action) {
                    case self::ADD:
                        if (!is_file($src)) {
                            $errors[] = "fuente faltante para add: {$path}";
                            break;
                        }
                        self::copy($src, $dst);
                        $applied[] = ['path' => $path, 'action' => $action];
                        break;

                    case self::UPDATE:
                        if (!is_file($src)) {
                            $errors[] = "fuente faltante para update: {$path}";
                            break;
                        }
                        self::backup($dst, $backupDir . '/files/' . $path);
                        self::copy($src, $dst);
                        $applied[] = ['path' => $path, 'action' => $action];
                        break;

                    case self::CONFLICT:
                    case self::CONFLICT_ADD:
                        if (!is_file($src)) {
                            $errors[] = "fuente faltante para conflict: {$path}";
                            break;
                        }
                        self::copy($src, $dst . '.new');
                        $conflicts[] = $path;
                        $applied[] = ['path' => $path, 'action' => $action];
                        break;

                    case self::DELETE:
                        self::backup($dst, $backupDir . '/files/' . $path);
                        @unlink($dst);
                        $applied[] = ['path' => $path, 'action' => $action];
                        break;

                    case self::DELETE_MODIFIED:
                    case self::SKIP:
                    default:
                        break;
                }
            } catch (\Throwable $ex) {
                $errors[] = "{$path}: " . $ex->getMessage();
            }
        }

        // applied.json para rollback.
        self::ensureDir($backupDir);
        file_put_contents(
            $backupDir . '/applied.json',
            json_encode(['applied' => $applied], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return ['applied' => $applied, 'errors' => $errors, 'conflicts' => $conflicts];
    }

    /**
     * Revierte un update aplicado, usando el backupDir generado por apply().
     * @return array{restored:int,errors:array<int,string>}
     */
    public static function rollback(string $backupDir, string $installRoot): array
    {
        $backupDir   = rtrim(str_replace('\\', '/', $backupDir), '/');
        $installRoot = rtrim(str_replace('\\', '/', $installRoot), '/');

        $errors = [];
        $restored = 0;

        $appliedFile = $backupDir . '/applied.json';
        if (!is_file($appliedFile)) {
            return ['restored' => 0, 'errors' => ["No hay applied.json en {$backupDir}"]];
        }
        $data = json_decode((string) file_get_contents($appliedFile), true);
        $applied = is_array($data) && isset($data['applied']) ? $data['applied'] : [];

        foreach ($applied as $a) {
            $path = (string) ($a['path'] ?? '');
            $action = (string) ($a['action'] ?? '');
            $dst = $installRoot . '/' . $path;
            $bak = $backupDir . '/files/' . $path;

            try {
                switch ($action) {
                    case self::UPDATE:
                    case self::DELETE:
                        if (is_file($bak)) {
                            self::copy($bak, $dst);
                            $restored++;
                        } else {
                            $errors[] = "sin backup para restaurar: {$path}";
                        }
                        break;
                    case self::ADD:
                        @unlink($dst); // lo agregó el update → se quita
                        $restored++;
                        break;
                    case self::CONFLICT:
                    case self::CONFLICT_ADD:
                        @unlink($dst . '.new'); // solo se había dejado el .new
                        $restored++;
                        break;
                }
            } catch (\Throwable $ex) {
                $errors[] = "{$path}: " . $ex->getMessage();
            }
        }

        return ['restored' => $restored, 'errors' => $errors];
    }

    // ── helpers de archivos ──────────────────────────────────────────────────

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private static function copy(string $src, string $dst): void
    {
        self::ensureDir(\dirname($dst));
        if (!@copy($src, $dst)) {
            throw new \RuntimeException("no se pudo copiar a {$dst}");
        }
    }

    /** Respalda un archivo existente (si existe) preservando la ruta relativa. */
    private static function backup(string $file, string $backupPath): void
    {
        if (is_file($file)) {
            self::copy($file, $backupPath);
        }
    }
}
