<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Gestor de archivos del servidor, acotado a system/storage/uploads.
 * Toda operación valida que la ruta resultante quede DENTRO de la raíz
 * (anti path-traversal). Soporta carpetas/subcarpetas y subida de archivos.
 */
final class FileManager
{
    /** @return array{max_upload_bytes:int,allowed_ext:array<int,string>} */
    private static function config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = require BASE_PATH . '/config/files.php';
        }
        return $cfg;
    }

    public static function isImage(string $name): bool
    {
        return in_array(
            strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'],
            true
        );
    }

    public static function root(): string
    {
        $dir = BASE_PATH . '/storage/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return realpath($dir) ?: $dir;
    }

    /** Normaliza una ruta relativa; null si intenta salir de la raíz (..). */
    public static function normalizeRel(string $rel): ?string
    {
        $rel = str_replace('\\', '/', $rel);
        $parts = [];
        foreach (explode('/', $rel) as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                return null;
            }
            $parts[] = $p;
        }
        return implode('/', $parts);
    }

    /** Resuelve un directorio EXISTENTE dentro de la raíz (o null). */
    public static function safeDir(string $rel): ?string
    {
        $norm = self::normalizeRel($rel);
        if ($norm === null) {
            return null;
        }
        $path = realpath(self::root() . ($norm === '' ? '' : '/' . $norm));
        return ($path !== false && is_dir($path) && self::within($path)) ? $path : null;
    }

    /** Resuelve un archivo o carpeta EXISTENTE dentro de la raíz (o null). */
    public static function resolve(string $rel): ?string
    {
        $norm = self::normalizeRel($rel);
        if ($norm === null || $norm === '') {
            return null;
        }
        $path = realpath(self::root() . '/' . $norm);
        return ($path !== false && self::within($path)) ? $path : null;
    }

    private static function within(string $path): bool
    {
        $root = self::root();
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /** Sanitiza un nombre de archivo/carpeta (o null si es inválido). */
    public static function cleanName(string $name): ?string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\w.\- ]/u', '_', $name) ?? '';
        $name = trim($name);
        if ($name === '' || $name === '.' || str_contains($name, '..')) {
            return null;
        }
        return $name;
    }

    /**
     * Lista el contenido de un directorio relativo.
     * @return array{dirs:array<int,array<string,mixed>>,files:array<int,array<string,mixed>>}
     */
    public static function list(string $rel): array
    {
        $dir = self::safeDir($rel) ?? self::root();
        $base = self::normalizeRel($rel) ?? '';

        $dirs = [];
        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $abs = $dir . '/' . $entry;
            $childRel = ($base === '' ? '' : $base . '/') . $entry;
            $item = [
                'name'  => $entry,
                'rel'   => $childRel,
                'size'  => is_file($abs) ? (int) filesize($abs) : 0,
                'mtime' => (int) filemtime($abs),
            ];
            if (is_dir($abs)) {
                $dirs[] = $item;
            } else {
                $files[] = $item;
            }
        }

        usort($dirs, static fn ($a, $b): int => strcasecmp($a['name'], $b['name']));
        usort($files, static fn ($a, $b): int => strcasecmp($a['name'], $b['name']));

        return ['dirs' => $dirs, 'files' => $files];
    }

    /** @return array<int,array{label:string,rel:string}> */
    public static function breadcrumb(string $rel): array
    {
        $norm = self::normalizeRel($rel) ?? '';
        $crumbs = [['label' => 'Inicio', 'rel' => '']];
        $acc = '';
        foreach (array_filter(explode('/', $norm)) as $part) {
            $acc = ($acc === '' ? '' : $acc . '/') . $part;
            $crumbs[] = ['label' => $part, 'rel' => $acc];
        }
        return $crumbs;
    }

    // ----------------------------------------------------------- Operaciones

    /** @return array{ok:bool,error?:string,name?:string} */
    public static function upload(string $rel, array $file): array
    {
        $dir = self::safeDir($rel) ?? self::root();

        if (!isset($file['tmp_name'], $file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'No se recibió un archivo válido.'];
        }
        $name = self::cleanName((string) ($file['name'] ?? ''));
        if ($name === null) {
            return ['ok' => false, 'error' => 'Nombre de archivo inválido.'];
        }

        $cfg = self::config();
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $cfg['allowed_ext'], true)) {
            return ['ok' => false, 'error' => "Extensión .{$ext} no permitida."];
        }
        if ((int) ($file['size'] ?? 0) > $cfg['max_upload_bytes']) {
            $mb = round($cfg['max_upload_bytes'] / 1048576, 1);
            return ['ok' => false, 'error' => "El archivo supera el máximo de {$mb} MB."];
        }

        $dest = self::uniquePath($dir . '/' . $name);
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@rename($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo.'];
        }
        return ['ok' => true, 'name' => basename($dest)];
    }

    /** @return array{ok:bool,error?:string} */
    public static function makeDir(string $rel, string $name): array
    {
        $dir = self::safeDir($rel) ?? self::root();
        $clean = self::cleanName($name);
        if ($clean === null) {
            return ['ok' => false, 'error' => 'Nombre de carpeta inválido.'];
        }
        $target = $dir . '/' . $clean;
        if (is_dir($target)) {
            return ['ok' => false, 'error' => 'Ya existe una carpeta con ese nombre.'];
        }
        if (!@mkdir($target, 0775)) {
            return ['ok' => false, 'error' => 'No se pudo crear la carpeta.'];
        }
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function rename(string $rel, string $newName): array
    {
        $path = self::resolve($rel);
        if ($path === null) {
            return ['ok' => false, 'error' => 'Ruta inválida.'];
        }
        $clean = self::cleanName($newName);
        if ($clean === null) {
            return ['ok' => false, 'error' => 'Nombre inválido.'];
        }
        $dest = dirname($path) . '/' . $clean;
        if (file_exists($dest)) {
            return ['ok' => false, 'error' => 'Ya existe un archivo o carpeta con ese nombre.'];
        }
        if (!@rename($path, $dest)) {
            return ['ok' => false, 'error' => 'No se pudo renombrar.'];
        }
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function move(string $rel, string $destRel): array
    {
        $path = self::resolve($rel);
        if ($path === null) {
            return ['ok' => false, 'error' => 'Ruta inválida.'];
        }
        $destDir = ($destRel === '' || self::normalizeRel($destRel) === '') ? self::root() : self::safeDir($destRel);
        if ($destDir === null) {
            return ['ok' => false, 'error' => 'Carpeta destino inválida.'];
        }
        // No mover una carpeta dentro de sí misma.
        if (is_dir($path) && str_starts_with($destDir . '/', rtrim($path, '/') . '/')) {
            return ['ok' => false, 'error' => 'No se puede mover una carpeta dentro de sí misma.'];
        }
        $dest = $destDir . '/' . basename($path);
        if (file_exists($dest)) {
            return ['ok' => false, 'error' => 'Ya existe un elemento con ese nombre en el destino.'];
        }
        if (!@rename($path, $dest)) {
            return ['ok' => false, 'error' => 'No se pudo mover.'];
        }
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(string $rel): array
    {
        $path = self::resolve($rel);
        if ($path === null) {
            return ['ok' => false, 'error' => 'Ruta inválida.'];
        }
        if (is_dir($path)) {
            self::rrmdir($path);
        } else {
            @unlink($path);
        }
        return ['ok' => true];
    }

    private static function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $abs = $dir . '/' . $entry;
            is_dir($abs) ? self::rrmdir($abs) : @unlink($abs);
        }
        @rmdir($dir);
    }

    private static function uniquePath(string $path): string
    {
        if (!file_exists($path)) {
            return $path;
        }
        $dir = dirname($path);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $i = 1;
        do {
            $candidate = $dir . '/' . $base . '_' . $i . ($ext !== '' ? '.' . $ext : '');
            $i++;
        } while (file_exists($candidate));
        return $candidate;
    }
}
