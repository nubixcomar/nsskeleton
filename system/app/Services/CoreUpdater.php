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
 *
 * Merge 3-way (v1.20): si se le pasa un `base_dir` (copia del core tal como se instaló,
 * ver storeBase()), en cada `conflict` intenta combinar base/local/nuevo con
 * ThreeWayMerge. Si no hay choques, el archivo local queda mergeado (acción `merged`,
 * con backup). Si hay choques, el local no se toca: se deja `.new` (core nuevo) y
 * `.merge` (resultado con marcadores de conflicto) para resolver a mano.
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
    /** Solo en applied.json: conflicto resuelto por merge 3-way sin choques. */
    public const MERGED = 'merged';

    /** Razón del `skip` para un archivo del core editado por la app que el core no cambió. */
    public const REASON_APP_EDIT = 'editado por la app; el core no lo cambió';

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
            } elseif ($old === $newHash) {
                // La app lo editó pero el core no lo cambió en esta versión: no hay nada que traer
                // ni que mergear. Se conserva la versión local (antes salía como conflicto falso).
                $plan[] = self::entry($path, self::SKIP, self::REASON_APP_EDIT);
            } else {
                $plan[] = self::entry($path, self::CONFLICT, 'la app modificó un archivo del core') + ['old' => $old];
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
     * Opciones:
     *   base_dir  ?string  copia del core instalado (habilita el merge 3-way de conflictos)
     *   merge     bool     false = comportamiento clásico (solo `.new`), default true
     *
     * @param array<int,array{path:string,action:string,reason:string,old?:?string}> $plan
     * @param array{base_dir?:?string,merge?:bool} $opts
     * @return array{applied:array<int,array{path:string,action:string}>,errors:array<int,string>,conflicts:array<int,string>,merged:array<int,string>,merge_conflicts:array<int,string>}
     */
    public static function apply(array $plan, string $installRoot, string $sourceRoot, string $backupDir, array $opts = []): array
    {
        $installRoot = rtrim(str_replace('\\', '/', $installRoot), '/');
        $sourceRoot  = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $backupDir   = rtrim(str_replace('\\', '/', $backupDir), '/');
        $baseDir     = isset($opts['base_dir']) && $opts['base_dir'] !== null
            ? rtrim(str_replace('\\', '/', (string) $opts['base_dir']), '/') : null;
        $doMerge     = ($opts['merge'] ?? true) && $baseDir !== null;

        $applied = [];
        $errors = [];
        $conflicts = [];
        $merged = [];
        $mergeConflicts = [];

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
                        if ($action === self::CONFLICT && $doMerge) {
                            $m = self::tryMerge($e, $dst, $src, (string) $baseDir);
                            if ($m !== null && $m['clean']) {
                                // Merge limpio: el local queda combinado (con backup para rollback).
                                self::backup($dst, $backupDir . '/files/' . $path);
                                self::write($dst, $m['content']);
                                $merged[] = $path;
                                $applied[] = ['path' => $path, 'action' => self::MERGED];
                                break;
                            }
                            if ($m !== null) {
                                // Choques: se deja la propuesta con marcadores junto al .new.
                                self::write($dst . '.merge', $m['content']);
                                $mergeConflicts[] = $path;
                            }
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

        return [
            'applied' => $applied,
            'errors' => $errors,
            'conflicts' => $conflicts,
            'merged' => $merged,
            'merge_conflicts' => $mergeConflicts,
        ];
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
                    case self::MERGED:
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
                        @unlink($dst . '.merge'); // y, si hubo merge con choques, el .merge
                        $restored++;
                        break;
                }
            } catch (\Throwable $ex) {
                $errors[] = "{$path}: " . $ex->getMessage();
            }
        }

        return ['restored' => $restored, 'errors' => $errors];
    }

    // ── base del core (para merge 3-way) ─────────────────────────────────────

    /**
     * Guarda en $baseDir la copia "tal cual la envió el core" de cada archivo del lock,
     * tomándola de $fromRoot SOLO si su sha256 coincide con el del lock (así nunca se
     * guarda como base una versión editada por la app). Los que ya están válidos en la
     * base se dejan. Con $prune, borra de la base lo que no está en el lock.
     *
     * Usos: tras un update (fromRoot = paquete nuevo → base completa), y en forma
     * oportunista desde el árbol instalado (captura los archivos aún sin editar).
     *
     * @param array<string,string> $lock
     * @return array{stored:int,present:int,missing:int}
     */
    public static function storeBase(array $lock, string $fromRoot, string $baseDir, bool $prune = false): array
    {
        $fromRoot = rtrim(str_replace('\\', '/', $fromRoot), '/');
        $baseDir  = rtrim(str_replace('\\', '/', $baseDir), '/');
        $stored = $present = $missing = 0;

        foreach ($lock as $path => $hash) {
            $dst = $baseDir . '/' . $path;
            if (self::hashFile($dst) === $hash) {
                $present++;
                continue;
            }
            $src = $fromRoot . '/' . $path;
            if (self::hashFile($src) === $hash) {
                self::copy($src, $dst);
                $stored++;
            } else {
                $missing++;
            }
        }

        if ($prune && is_dir($baseDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($baseDir)), '/');
                if ($f->isDir()) {
                    @rmdir($f->getPathname()); // solo si quedó vacío
                } elseif (!array_key_exists($rel, $lock)) {
                    @unlink($f->getPathname());
                }
            }
        }

        return ['stored' => $stored, 'present' => $present, 'missing' => $missing];
    }

    /**
     * Estado de la base respecto de un lock: cuántos archivos tienen su original guardado.
     * @param array<string,string> $lock
     * @return array{total:int,valid:int,missing:array<int,string>}
     */
    public static function baseStatus(array $lock, string $baseDir): array
    {
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');
        $valid = 0;
        $missing = [];
        foreach ($lock as $path => $hash) {
            if (self::hashFile($baseDir . '/' . $path) === $hash) {
                $valid++;
            } else {
                $missing[] = $path;
            }
        }
        return ['total' => count($lock), 'valid' => $valid, 'missing' => $missing];
    }

    /**
     * Vista previa del merge para los conflictos del plan (no escribe nada).
     *   clean     se combina solo
     *   conflict  hay choques (quedará .merge con marcadores + .new)
     *   no_base   falta el original en la base → solo .new
     *   binary    archivo binario → solo .new
     *
     * @param array<int,array{path:string,action:string,reason:string,old?:?string}> $plan
     * @return array<string,array{status:string,conflicts:int}>
     */
    public static function mergePreview(array $plan, string $installRoot, string $sourceRoot, string $baseDir): array
    {
        $installRoot = rtrim(str_replace('\\', '/', $installRoot), '/');
        $sourceRoot  = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $out = [];
        foreach ($plan as $e) {
            if ($e['action'] !== self::CONFLICT) {
                continue;
            }
            $dst = $installRoot . '/' . $e['path'];
            $src = $sourceRoot . '/' . $e['path'];
            $m = self::tryMerge($e, $dst, $src, $baseDir);
            if ($m === null) {
                $out[$e['path']] = ['status' => self::mergeSkipReason($e, $dst, $src, $baseDir), 'conflicts' => 0];
            } else {
                $out[$e['path']] = ['status' => $m['clean'] ? 'clean' : 'conflict', 'conflicts' => $m['conflicts']];
            }
        }
        return $out;
    }

    /**
     * Intenta el merge de un conflicto. null si no se puede (sin base válida o binario).
     * @param array{path:string,action:string,reason:string,old?:?string} $e
     * @return array{clean:bool,content:string,conflicts:int}|null
     */
    private static function tryMerge(array $e, string $dst, string $src, string $baseDir): ?array
    {
        if (self::mergeSkipReason($e, $dst, $src, $baseDir) !== '') {
            return null;
        }
        $base   = (string) file_get_contents(rtrim($baseDir, '/') . '/' . $e['path']);
        $ours   = (string) file_get_contents($dst);
        $theirs = (string) file_get_contents($src);
        return ThreeWayMerge::merge($base, $ours, $theirs);
    }

    /** '' si se puede mergear; si no, el motivo (no_base | binary). */
    private static function mergeSkipReason(array $e, string $dst, string $src, string $baseDir): string
    {
        $old = $e['old'] ?? null;
        $baseFile = rtrim($baseDir, '/') . '/' . $e['path'];
        if ($old === null || $old === '' || self::hashFile($baseFile) !== $old || !is_file($dst) || !is_file($src)) {
            return 'no_base';
        }
        foreach ([$baseFile, $dst, $src] as $f) {
            $h = @fopen($f, 'rb');
            $head = $h ? (string) fread($h, 8192) : '';
            if ($h) {
                fclose($h);
            }
            if (ThreeWayMerge::isBinary($head)) {
                return 'binary';
            }
        }
        return '';
    }

    // ── helpers de archivos ──────────────────────────────────────────────────

    private static function write(string $dst, string $content): void
    {
        self::ensureDir(\dirname($dst));
        if (@file_put_contents($dst, $content) === false) {
            throw new \RuntimeException("no se pudo escribir {$dst}");
        }
    }

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
