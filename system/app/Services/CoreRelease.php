<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Releases publicadas del core: lectura de `latest.json` (índice que genera
 * `landing/build-core-package.php` junto a los zips) y descarga verificada.
 *
 * Formato de latest.json (schema 1):
 *   {
 *     "schema": 1,
 *     "name": "nsSkeleton-core",
 *     "latest": "1.20.0",
 *     "generated_at": "2026-11-07T12:00:00-03:00",
 *     "releases": {
 *       "1.20.0": {"file": "nsSkeleton-core-1.20.0.zip", "sha256": "…", "size": 123456, "released_at": "…"},
 *       "1.19.0": {…}
 *     }
 *   }
 *
 * `file` es relativo a la URL de latest.json (también se acepta una URL absoluta).
 * El sha256 protege contra descargas corruptas o truncadas y contra un zip
 * reemplazado en tránsito; NO reemplaza confiar en el servidor que publica
 * (usá https y un dominio propio).
 */
final class CoreRelease
{
    /** URL de latest.json configurada (config/core_update.php → .env CORE_UPDATE_URL). */
    public static function configuredUrl(): string
    {
        $url = '';
        try {
            $url = (string) \Core\Config::get('core_update', 'latest_url', '');
        } catch (\Throwable) {
            $url = '';
        }
        if ($url === '') {
            $url = (string) \Core\Env::get('CORE_UPDATE_URL', '');
        }
        return trim($url);
    }

    /**
     * Descarga y valida latest.json.
     * @return array{ok:bool,error:string,index:array<string,mixed>}
     */
    public static function fetchIndex(string $latestUrl): array
    {
        if (!self::isHttpUrl($latestUrl)) {
            return ['ok' => false, 'error' => 'URL de latest.json inválida (debe ser http/https).', 'index' => []];
        }
        $r = Http::request('GET', $latestUrl, ['timeout' => 20, 'retries' => 1, 'headers' => ['Accept: application/json']]);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => 'No se pudo leer latest.json: ' . ($r['error'] ?: 'HTTP ' . $r['status']), 'index' => []];
        }
        $index = is_array($r['data']) ? $r['data'] : null;
        $err = self::validateIndex($index);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err, 'index' => []];
        }
        /** @var array<string,mixed> $index */
        return ['ok' => true, 'error' => '', 'index' => $index];
    }

    /** Devuelve null si el índice es válido, o el motivo si no. */
    public static function validateIndex(mixed $index): ?string
    {
        if (!is_array($index)) {
            return 'latest.json no es un JSON válido.';
        }
        $latest = (string) ($index['latest'] ?? '');
        if (!self::isVersion($latest)) {
            return 'latest.json no declara una versión "latest" válida.';
        }
        $releases = $index['releases'] ?? null;
        if (!is_array($releases) || !isset($releases[$latest]) || !is_array($releases[$latest])) {
            return "latest.json no trae la release {$latest} en \"releases\".";
        }
        foreach ($releases as $ver => $rel) {
            if (!self::isVersion((string) $ver) || !is_array($rel)) {
                return "Release inválida en latest.json: {$ver}.";
            }
            if (!isset($rel['file']) || !is_string($rel['file']) || $rel['file'] === '') {
                return "La release {$ver} no tiene \"file\".";
            }
            if (!isset($rel['sha256']) || !preg_match('/^[a-f0-9]{64}$/', (string) $rel['sha256'])) {
                return "La release {$ver} no tiene un sha256 válido.";
            }
        }
        return null;
    }

    /**
     * Release concreta del índice, con su URL absoluta resuelta.
     * @param array<string,mixed> $index
     * @return array{version:string,url:string,sha256:string,size:int,released_at:string}|null
     */
    public static function release(array $index, string $latestUrl, ?string $version = null): ?array
    {
        $version ??= (string) ($index['latest'] ?? '');
        $rel = $index['releases'][$version] ?? null;
        if (!is_array($rel)) {
            return null;
        }
        return [
            'version'     => $version,
            'url'         => self::resolveUrl($latestUrl, (string) $rel['file']),
            'sha256'      => strtolower((string) $rel['sha256']),
            'size'        => (int) ($rel['size'] ?? 0),
            'released_at' => (string) ($rel['released_at'] ?? ''),
        ];
    }

    /**
     * Compara la versión instalada contra latest.json.
     * @return array{ok:bool,error:string,installed:string,latest:string,update_available:bool,release:?array{version:string,url:string,sha256:string,size:int,released_at:string},insecure:bool}
     */
    public static function check(string $latestUrl, string $installed): array
    {
        $base = [
            'ok' => false, 'error' => '', 'installed' => $installed, 'latest' => '',
            'update_available' => false, 'release' => null,
            'insecure' => self::isHttpUrl($latestUrl) && !str_starts_with(strtolower($latestUrl), 'https://'),
        ];
        $idx = self::fetchIndex($latestUrl);
        if (!$idx['ok']) {
            return ['error' => $idx['error']] + $base;
        }
        $rel = self::release($idx['index'], $latestUrl);
        $latest = (string) $idx['index']['latest'];
        return [
            'ok' => true,
            'latest' => $latest,
            'update_available' => self::isVersion($installed) ? version_compare($latest, $installed, '>') : true,
            'release' => $rel,
        ] + $base;
    }

    /**
     * Descarga un paquete a $dest y verifica su sha256 (si se pasa uno).
     * Si no coincide, borra el archivo y devuelve error.
     * @return array{ok:bool,error:string,path:string,sha256:string}
     */
    public static function download(string $url, string $dest, ?string $expectedSha = null): array
    {
        if (!self::isHttpUrl($url)) {
            return ['ok' => false, 'error' => "URL inválida: {$url}", 'path' => '', 'sha256' => ''];
        }
        $r = Http::request('GET', $url, ['timeout' => 180, 'retries' => 2]);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => 'Falló la descarga: ' . ($r['error'] ?: 'HTTP ' . $r['status']), 'path' => '', 'sha256' => ''];
        }
        if ($r['body'] === '') {
            return ['ok' => false, 'error' => 'La descarga vino vacía.', 'path' => '', 'sha256' => ''];
        }
        $dir = \dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($dest, $r['body']) === false) {
            return ['ok' => false, 'error' => "No se pudo escribir {$dest}", 'path' => '', 'sha256' => ''];
        }
        $sha = (string) hash_file('sha256', $dest);
        if ($expectedSha !== null && $expectedSha !== '' && !hash_equals(strtolower($expectedSha), $sha)) {
            @unlink($dest);
            return [
                'ok' => false,
                'error' => "sha256 no coincide (esperado {$expectedSha}, obtenido {$sha}). Paquete descartado.",
                'path' => '',
                'sha256' => $sha,
            ];
        }
        return ['ok' => true, 'error' => '', 'path' => $dest, 'sha256' => $sha];
    }

    /** Resuelve $file relativo a la URL de latest.json (o lo deja si ya es absoluta). */
    public static function resolveUrl(string $latestUrl, string $file): string
    {
        if (self::isHttpUrl($file)) {
            return $file;
        }
        $base = preg_replace('/[?#].*$/', '', $latestUrl) ?? $latestUrl;
        $dir = substr($base, 0, (int) strrpos($base, '/') + 1);
        return $dir . rawurlencode(ltrim(basename($file), '/'));
    }

    /**
     * Construye el índice latest.json a partir de los zips de core presentes en $dir
     * (lo usa build-core-package). Idempotente: se recalcula siempre desde disco.
     * @return array<string,mixed>
     */
    public static function buildIndex(string $dir): array
    {
        $releases = [];
        foreach (glob(rtrim($dir, '/\\') . '/nsSkeleton-core-*.zip') ?: [] as $zip) {
            if (!preg_match('/nsSkeleton-core-(\d+\.\d+\.\d+)\.zip$/', $zip, $m)) {
                continue;
            }
            $releases[$m[1]] = [
                'file'        => basename($zip),
                'sha256'      => (string) hash_file('sha256', $zip),
                'size'        => (int) filesize($zip),
                'released_at' => date('c', (int) filemtime($zip)),
            ];
        }
        uksort($releases, static fn (string $x, string $y): int => version_compare($y, $x)); // más nueva primero
        $latest = (string) (array_key_first($releases) ?? '');

        return [
            'schema'       => 1,
            'name'         => 'nsSkeleton-core',
            'latest'       => $latest,
            'generated_at' => date('c'),
            'releases'     => $releases,
        ];
    }

    public static function isVersion(string $v): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $v);
    }

    private static function isHttpUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://[^\s/]+#i', $url);
    }
}
