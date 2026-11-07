<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Orquestador del update de core (v1.20). Lo usan el CLI (`system/console/core-update.php`)
 * y la pantalla de admin (`/admin/core-update`), así ambos hacen exactamente lo mismo.
 *
 * El motor por archivo es CoreUpdater (plan/apply/rollback/merge). Esta clase agrega:
 *  - preparar el paquete (carpeta o zip → árbol con core-lock.json),
 *  - la BASE del core (`storage/core-base/`): copia de cada archivo tal como lo envió el
 *    core, que habilita el merge 3-way. Se completa tras cada update y, en forma
 *    oportunista, desde el árbol instalado (archivos aún sin editar),
 *  - chequeo de permisos de escritura antes de aplicar,
 *  - un lock para que no corran dos updates a la vez,
 *  - backup de metadatos + base, actualización de VERSION/lock/manifest, migraciones,
 *  - informe `report.json` en la carpeta de backup y rollback completo.
 */
final class CoreUpdate
{
    public const META = ['core-lock.json', 'core-manifest.json', 'VERSION'];

    private string $installRoot;
    private string $storage;

    public function __construct(string $installRoot, string $storageDir)
    {
        $this->installRoot = rtrim(str_replace('\\', '/', $installRoot), '/');
        $this->storage = rtrim(str_replace('\\', '/', $storageDir), '/');
    }

    /** Instancia para la instalación actual. */
    public static function forInstall(): self
    {
        $project = \defined('PROJECT_PATH') ? (string) PROJECT_PATH : \dirname((string) BASE_PATH);
        return new self($project, (string) BASE_PATH . '/storage');
    }

    public function installRoot(): string
    {
        return $this->installRoot;
    }

    public function baseDir(): string
    {
        return $this->storage . '/core-base';
    }

    public function backupsDir(): string
    {
        return $this->storage . '/backups';
    }

    public function cacheDir(): string
    {
        return $this->storage . '/cache';
    }

    public function installedVersion(): string
    {
        return trim((string) @file_get_contents($this->installRoot . '/VERSION'));
    }

    /** @return array<string,string> */
    public function installedLock(): array
    {
        return CoreUpdater::loadLockFiles($this->installRoot . '/core-lock.json');
    }

    // ── Base del core ────────────────────────────────────────────────────────

    /**
     * Captura oportunista: guarda en la base los archivos del core instalado que todavía
     * coinciden con el lock (no editados). Barato e idempotente.
     * @return array{stored:int,present:int,missing:int}
     */
    public function captureBase(): array
    {
        return CoreUpdater::storeBase($this->installedLock(), $this->installRoot, $this->baseDir());
    }

    /**
     * Completa la base desde un paquete de la MISMA versión instalada (ej. el zip de esa
     * versión). Solo guarda archivos cuyo sha coincide con el lock instalado: si el
     * paquete es de otra versión, simplemente no aporta nada.
     * @return array{stored:int,present:int,missing:int}
     */
    public function seedBaseFrom(string $sourceRoot): array
    {
        return CoreUpdater::storeBase($this->installedLock(), $sourceRoot, $this->baseDir());
    }

    /** @return array{total:int,valid:int,missing:array<int,string>} */
    public function baseStatus(): array
    {
        return CoreUpdater::baseStatus($this->installedLock(), $this->baseDir());
    }

    /**
     * Archivos del core que la app editó y que NO tienen original en la base
     * (son los que, ante un conflicto, no se podrían mergear).
     * @return array<int,string>
     */
    public function editedWithoutBase(): array
    {
        $out = [];
        $base = $this->baseDir();
        foreach ($this->installedLock() as $path => $hash) {
            $local = $this->installRoot . '/' . $path;
            if (!is_file($local) || hash_file('sha256', $local) === $hash) {
                continue;
            }
            $b = $base . '/' . $path;
            if (!is_file($b) || hash_file('sha256', $b) !== $hash) {
                $out[] = $path;
            }
        }
        return $out;
    }

    // ── Paquete ──────────────────────────────────────────────────────────────

    /**
     * Prepara un paquete (carpeta o .zip) y ubica su core-lock.json.
     * @return array{ok:bool,error:string,root:string,lock_file:string,version:string,cleanup:?string}
     */
    public function prepareSource(string $source): array
    {
        $fail = static fn (string $e): array => ['ok' => false, 'error' => $e, 'root' => '', 'lock_file' => '', 'version' => '', 'cleanup' => null];
        $cleanup = null;

        if (is_file($source) && preg_match('/\.zip$/i', $source)) {
            if (!class_exists(\ZipArchive::class)) {
                return $fail('ZipArchive no disponible; extraé el zip y pasá la carpeta.');
            }
            $tmp = $this->cacheDir() . '/core-update-src-' . date('Ymd_His') . '-' . bin2hex(random_bytes(3));
            @mkdir($tmp, 0775, true);
            $zip = new \ZipArchive();
            if ($zip->open($source) !== true) {
                return $fail("No se pudo abrir el zip: {$source}");
            }
            // Defensa: no extraer entradas con rutas absolutas o que escapen (zip slip).
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $norm = str_replace('\\', '/', $name);
                if (preg_match('#(^|/)\.\.(/|$)#', $norm) || str_starts_with($norm, '/') || preg_match('#^[a-z]:#i', $norm)) {
                    $zip->close();
                    self::rrmdir($tmp);
                    return $fail("Entrada sospechosa en el zip: {$name}");
                }
            }
            $zip->extractTo($tmp);
            $zip->close();
            $root = $tmp;
            $cleanup = $tmp;
        } elseif (is_dir($source)) {
            $root = rtrim(str_replace('\\', '/', $source), '/');
        } else {
            return $fail("El paquete no es ni carpeta ni zip: {$source}");
        }

        $lockFile = $root . '/core-lock.json';
        if (!is_file($lockFile)) {
            $candidates = glob($root . '/*/core-lock.json') ?: [];
            if (count($candidates) === 1) {
                $lockFile = str_replace('\\', '/', $candidates[0]);
                $root = \dirname($lockFile);
            }
        }
        if (!is_file($lockFile)) {
            if ($cleanup) {
                self::rrmdir($cleanup);
            }
            return $fail("El paquete no tiene core-lock.json en la raíz: {$root}");
        }

        return [
            'ok' => true, 'error' => '', 'root' => $root, 'lock_file' => $lockFile,
            'version' => trim((string) @file_get_contents($root . '/VERSION')), 'cleanup' => $cleanup,
        ];
    }

    // ── Plan ─────────────────────────────────────────────────────────────────

    /**
     * Arma el plan completo + vista previa del merge + chequeo de escritura. No toca nada.
     * @return array{installed:string,new:string,old_count:int,new_count:int,plan:array<int,array<string,mixed>>,summary:array<string,int>,merge:array<string,array{status:string,conflicts:int}>,unwritable:array<int,string>,changes:int}
     */
    public function inspect(string $sourceRoot, string $newLockFile, bool $merge = true): array
    {
        $this->captureBase();
        $oldLock = $this->installedLock();
        $newLock = CoreUpdater::loadLockFiles($newLockFile);
        $plan = CoreUpdater::plan($oldLock, $newLock, $this->installRoot, $sourceRoot);
        $summary = CoreUpdater::summarize($plan);
        $changes = array_sum(array_map(static fn ($a) => $summary[$a] ?? 0, CoreUpdater::MUTATING));

        return [
            'installed'  => $this->installedVersion(),
            'new'        => trim((string) @file_get_contents($sourceRoot . '/VERSION')),
            'old_count'  => count($oldLock),
            'new_count'  => count($newLock),
            'plan'       => $plan,
            'summary'    => $summary,
            'merge'      => $merge ? CoreUpdater::mergePreview($plan, $this->installRoot, $sourceRoot, $this->baseDir()) : [],
            'unwritable' => $this->unwritable($plan),
            'changes'    => $changes,
        ];
    }

    /**
     * Rutas que el plan necesita escribir y el proceso no puede (permisos del hosting).
     * @param array<int,array<string,mixed>> $plan
     * @return array<int,string>
     */
    public function unwritable(array $plan): array
    {
        $out = [];
        $checked = [];
        foreach ($plan as $e) {
            if (!in_array($e['action'], CoreUpdater::MUTATING, true)) {
                continue;
            }
            $dst = $this->installRoot . '/' . $e['path'];
            if (is_file($dst)) {
                if (!is_writable($dst)) {
                    $out[] = (string) $e['path'];
                }
                // Conflictos escriben .new/.merge al lado: también hace falta el directorio.
                if (!in_array($e['action'], [CoreUpdater::CONFLICT, CoreUpdater::CONFLICT_ADD, CoreUpdater::DELETE], true)) {
                    continue;
                }
            }
            // Primer directorio existente hacia arriba (se crearán los que falten).
            $dir = \dirname($dst);
            while (!is_dir($dir) && $dir !== \dirname($dir)) {
                $dir = \dirname($dir);
            }
            if (!isset($checked[$dir])) {
                $checked[$dir] = is_writable($dir);
            }
            if (!$checked[$dir]) {
                $out[] = (string) $e['path'];
            }
        }
        foreach (self::META as $meta) {
            $f = $this->installRoot . '/' . $meta;
            if (is_file($f) && !is_writable($f)) {
                $out[] = $meta;
            }
        }
        return array_values(array_unique($out));
    }

    // ── Aplicar ──────────────────────────────────────────────────────────────

    /**
     * Aplica el update completo. Opciones: merge (bool, default true), migrate (bool,
     * default true), actor (string, para el informe).
     *
     * @param array{merge?:bool,migrate?:bool,actor?:string} $opts
     * @return array{ok:bool,error:string,backup_dir:string,from:string,to:string,applied:int,conflicts:array<int,string>,merged:array<int,string>,merge_conflicts:array<int,string>,errors:array<int,string>,migrations:string}
     */
    public function run(string $sourceRoot, string $newLockFile, array $opts = []): array
    {
        $merge = $opts['merge'] ?? true;
        $result = [
            'ok' => false, 'error' => '', 'backup_dir' => '', 'from' => $this->installedVersion(), 'to' => '',
            'applied' => 0, 'conflicts' => [], 'merged' => [], 'merge_conflicts' => [], 'errors' => [], 'migrations' => '',
        ];

        $lock = $this->acquireLock();
        if ($lock === null) {
            return ['error' => 'Ya hay un update de core en curso.'] + $result;
        }

        try {
            $this->captureBase();
            $oldLock = $this->installedLock();
            $newLock = CoreUpdater::loadLockFiles($newLockFile);
            if ($newLock === []) {
                return ['error' => 'El core-lock.json del paquete está vacío o es inválido.'] + $result;
            }
            $plan = CoreUpdater::plan($oldLock, $newLock, $this->installRoot, $sourceRoot);

            $bad = $this->unwritable($plan);
            if ($bad !== []) {
                return ['error' => 'Sin permiso de escritura en ' . count($bad) . ' ruta(s): ' . implode(', ', array_slice($bad, 0, 5))] + $result;
            }

            $backupDir = $this->backupsDir() . '/core-update-' . date('Ymd_His');
            if (is_dir($backupDir)) {
                $backupDir .= '-' . bin2hex(random_bytes(2));
            }
            @mkdir($backupDir . '/meta', 0775, true);
            foreach (self::META as $meta) {
                if (is_file($this->installRoot . '/' . $meta)) {
                    @copy($this->installRoot . '/' . $meta, $backupDir . '/meta/' . $meta);
                }
            }

            $res = CoreUpdater::apply($plan, $this->installRoot, $sourceRoot, $backupDir, [
                'base_dir' => $merge ? $this->baseDir() : null,
                'merge'    => $merge,
            ]);

            // Metadatos del core.
            @copy($newLockFile, $this->installRoot . '/core-lock.json');
            $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
            if (is_file($sourceRoot . '/core-manifest.json')) {
                @copy($sourceRoot . '/core-manifest.json', $this->installRoot . '/core-manifest.json');
            }
            $newVer = trim((string) @file_get_contents($sourceRoot . '/VERSION'));
            if ($newVer !== '') {
                file_put_contents($this->installRoot . '/VERSION', $newVer);
            }

            // La base pasa a ser el core nuevo (la anterior queda en el backup para rollback).
            $this->rotateBase($backupDir, $newLock, $sourceRoot);

            $migrations = 'omitidas';
            if ($opts['migrate'] ?? true) {
                try {
                    $done = Migrator::migrateCore();
                    $migrations = $done === [] ? 'ninguna pendiente' : (count($done) . ' aplicada(s)');
                } catch (\Throwable $ex) {
                    $migrations = 'omitidas (' . $ex->getMessage() . ')';
                }
            }

            $result = [
                'ok'              => $res['errors'] === [],
                'error'           => $res['errors'] === [] ? '' : count($res['errors']) . ' error(es) al aplicar.',
                'backup_dir'      => $backupDir,
                'from'            => $result['from'],
                'to'              => $newVer,
                'applied'         => count($res['applied']),
                'conflicts'       => $res['conflicts'],
                'merged'          => $res['merged'],
                'merge_conflicts' => $res['merge_conflicts'],
                'errors'          => $res['errors'],
                'migrations'      => $migrations,
            ];

            file_put_contents(
                $backupDir . '/report.json',
                json_encode($result + [
                    'at' => date('c'),
                    'actor' => (string) ($opts['actor'] ?? 'cli'),
                    'summary' => CoreUpdater::summarize($plan),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            return $result;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Revierte un update: archivos del core, metadatos y base.
     * @return array{ok:bool,restored:int,errors:array<int,string>}
     */
    public function rollback(string $backupDir): array
    {
        $backupDir = rtrim(str_replace('\\', '/', $backupDir), '/');
        if (!is_dir($backupDir)) {
            return ['ok' => false, 'restored' => 0, 'errors' => ["No existe el backup: {$backupDir}"]];
        }
        $lock = $this->acquireLock();
        if ($lock === null) {
            return ['ok' => false, 'restored' => 0, 'errors' => ['Ya hay un update de core en curso.']];
        }
        try {
            $r = CoreUpdater::rollback($backupDir, $this->installRoot);
            foreach (self::META as $meta) {
                $bak = $backupDir . '/meta/' . $meta;
                if (is_file($bak)) {
                    @copy($bak, $this->installRoot . '/' . $meta);
                }
            }
            // Base: volver a la del core anterior.
            if (is_dir($backupDir . '/core-base')) {
                self::rrmdir($this->baseDir());
                self::copyTree($backupDir . '/core-base', $this->baseDir());
            }
            @file_put_contents($backupDir . '/rolled-back.json', json_encode(['at' => date('c')]));
            return ['ok' => $r['errors'] === [], 'restored' => $r['restored'], 'errors' => $r['errors']];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Updates aplicados (carpetas core-update-*), más nuevo primero, con su informe.
     * @return array<int,array{name:string,dir:string,at:string,from:string,to:string,ok:bool,rolled_back:bool,conflicts:int,merged:int}>
     */
    public function history(int $limit = 20): array
    {
        $dirs = glob($this->backupsDir() . '/core-update-*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);
        $out = [];
        foreach (array_slice($dirs, 0, $limit) as $d) {
            $rep = json_decode((string) @file_get_contents($d . '/report.json'), true);
            $rep = is_array($rep) ? $rep : [];
            $out[] = [
                'name'        => basename($d),
                'dir'         => str_replace('\\', '/', $d),
                'at'          => (string) ($rep['at'] ?? date('c', (int) filemtime($d))),
                'from'        => (string) ($rep['from'] ?? '?'),
                'to'          => (string) ($rep['to'] ?? '?'),
                'ok'          => (bool) ($rep['ok'] ?? false),
                'rolled_back' => is_file($d . '/rolled-back.json'),
                'conflicts'   => count((array) ($rep['conflicts'] ?? [])),
                'merged'      => count((array) ($rep['merged'] ?? [])),
            ];
        }
        return $out;
    }

    /** Resuelve el nombre de un backup a su carpeta (sin permitir salir de backups/). */
    public function backupPath(string $name): ?string
    {
        if (!preg_match('/^core-update-[0-9_\-a-f]+$/', $name)) {
            return null;
        }
        $dir = $this->backupsDir() . '/' . $name;
        return is_dir($dir) ? $dir : null;
    }

    // ── internos ─────────────────────────────────────────────────────────────

    /** @param array<string,string> $newLock */
    private function rotateBase(string $backupDir, array $newLock, string $sourceRoot): void
    {
        $base = $this->baseDir();
        if (is_dir($base)) {
            // Copia (no rename) para no depender de que backups/ y core-base/ estén en el mismo FS.
            self::copyTree($base, $backupDir . '/core-base');
        }
        CoreUpdater::storeBase($newLock, $sourceRoot, $base, true);
    }

    /** @return resource|null */
    private function acquireLock()
    {
        @mkdir($this->cacheDir(), 0775, true);
        $h = @fopen($this->cacheDir() . '/core-update.lock', 'c');
        if ($h === false) {
            return null;
        }
        if (!flock($h, LOCK_EX | LOCK_NB)) {
            fclose($h);
            return null;
        }
        return $h;
    }

    /** @param resource $h */
    private function releaseLock($h): void
    {
        flock($h, LOCK_UN);
        fclose($h);
    }

    public static function copyTree(string $src, string $dst): void
    {
        $src = rtrim(str_replace('\\', '/', $src), '/');
        $dst = rtrim(str_replace('\\', '/', $dst), '/');
        if (!is_dir($src)) {
            return;
        }
        @mkdir($dst, 0775, true);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($src)), '/');
            if ($f->isDir()) {
                @mkdir($dst . '/' . $rel, 0775, true);
            } else {
                @copy($f->getPathname(), $dst . '/' . $rel);
            }
        }
    }

    public static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? self::rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
