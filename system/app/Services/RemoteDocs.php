<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config;

/**
 * Sincronizador de manuales remotos (config `remote_docs`).
 *
 * Baja cada manual por HTTP GET, lo valida (200, Markdown, tamaño mínimo), y si cambió su
 * sha256 lo escribe de forma atómica en `target_dir`, guardando el anterior como `*.prev.md`.
 * El contenido se guarda **verbatim**: la procedencia (url, versión, sha256, fechas) va al
 * lock `remote-docs.lock.json`, no adentro del archivo. Nunca lanza.
 *
 * Estados de un sync: `new` | `updated` | `unchanged` | `error` | `disabled`.
 */
final class RemoteDocs
{
    public const LOCK_FILE = 'remote-docs.lock.json';
    private const USER_AGENT = 'nsSkeleton-remote-docs/1.0';

    /** @var array<string,mixed>|null Config inyectada (tests). */
    private static ?array $config = null;

    /** @var string|null Raíz del proyecto inyectada (tests). */
    private static ?string $root = null;

    /** @param array<string,mixed>|null $config */
    public static function useConfig(?array $config, ?string $root = null): void
    {
        self::$config = $config;
        self::$root = $root;
    }

    /** @return array<string,mixed> */
    public static function config(): array
    {
        return self::$config ?? Config::load('remote_docs');
    }

    /** @return array<string,array<string,mixed>> Todos los manuales declarados (habilitados o no). */
    public static function docs(): array
    {
        $docs = self::config()['docs'] ?? [];
        return is_array($docs) ? $docs : [];
    }

    public static function targetDir(): string
    {
        $root = self::$root ?? (defined('PROJECT_PATH') ? PROJECT_PATH : dirname(BASE_PATH));
        $dir = (string) (self::config()['target_dir'] ?? 'app-agentic/knowledge/remote');
        return rtrim($root, '/\\') . '/' . trim($dir, '/\\');
    }

    /** Ruta absoluta del archivo local de un manual. */
    public static function pathFor(string $key): ?string
    {
        $doc = self::docs()[$key] ?? null;
        if ($doc === null || empty($doc['file'])) {
            return null;
        }
        $file = str_replace('\\', '/', (string) $doc['file']);
        if (str_contains($file, '..')) {
            return null; // nunca escribir fuera de target_dir
        }
        return self::targetDir() . '/' . ltrim($file, '/');
    }

    /**
     * Sincroniza un manual o todos los habilitados.
     * @return array<string,array{status:string,version:?string,prev_version:?string,bytes:int,sha256:?string,error:?string,path:?string}>
     */
    public static function sync(?string $key = null, bool $dryRun = false): array
    {
        $docs = self::docs();
        if ($key !== null) {
            if (!isset($docs[$key])) {
                return [$key => self::result('error', error: "Manual '{$key}' no está declarado en config/remote_docs.php.")];
            }
            $docs = [$key => $docs[$key]];
        }

        $lock = self::readLock();
        $out = [];
        foreach ($docs as $k => $doc) {
            $k = (string) $k;
            if (empty($doc['enabled'])) {
                $out[$k] = self::result('disabled');
                continue;
            }
            $out[$k] = self::syncOne($k, $doc, $lock, $dryRun);
        }
        if (!$dryRun) {
            self::writeLock($lock);
        }
        return $out;
    }

    /**
     * Estado local de cada manual (sin red).
     * @return array<string,array{title:string,enabled:bool,url:string,version:?string,checked_at:?string,changed_at:?string,age_hours:?float,stale:bool,exists:bool,path:?string}>
     */
    public static function status(?int $nowTs = null): array
    {
        $nowTs ??= time();
        $maxAge = (float) (self::config()['max_age_hours'] ?? 24);
        $lock = self::readLock();
        $out = [];
        foreach (self::docs() as $k => $doc) {
            $k = (string) $k;
            $entry = $lock[$k] ?? [];
            $path = self::pathFor($k);
            $checked = isset($entry['checked_at']) ? strtotime((string) $entry['checked_at']) : false;
            $age = $checked !== false ? round(($nowTs - $checked) / 3600, 1) : null;
            $exists = $path !== null && is_file($path);
            $out[$k] = [
                'title'      => (string) ($doc['title'] ?? $k),
                'enabled'    => !empty($doc['enabled']),
                'url'        => (string) ($doc['url'] ?? ''),
                'version'    => $entry['version'] ?? null,
                'checked_at' => $entry['checked_at'] ?? null,
                'changed_at' => $entry['changed_at'] ?? null,
                'age_hours'  => $age,
                'stale'      => !$exists || $age === null || $age > $maxAge,
                'exists'     => $exists,
                'path'       => $path,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $doc
     * @param array<string,array<string,mixed>> $lock (por referencia: se actualiza)
     */
    private static function syncOne(string $key, array $doc, array &$lock, bool $dryRun): array
    {
        $path = self::pathFor($key);
        $url = (string) ($doc['url'] ?? '');
        if ($path === null || !preg_match('#^https://#i', $url)) {
            return self::result('error', error: 'Falta `file` válido o la `url` no es https://.');
        }

        $res = Http::request('GET', $url, [
            'headers' => ['Accept: text/markdown, text/plain;q=0.9', 'User-Agent: ' . self::USER_AGENT],
            'timeout' => (int) (self::config()['timeout'] ?? 30),
            'retries' => 1,
        ]);
        $now = date('c');

        $body = (string) $res['body'];
        $error = self::validate($res['status'], $body, (int) ($doc['min_bytes'] ?? 1));
        if ($error !== null) {
            if (!$dryRun) {
                $lock[$key]['last_error'] = $error;
                $lock[$key]['last_error_at'] = $now;
            }
            return self::result('error', version: $lock[$key]['version'] ?? null, error: $error, path: $path);
        }

        $body = self::stripBom($body);
        $sha = hash('sha256', $body);
        $version = self::extractVersion($body, (string) ($doc['version_pattern'] ?? ''));
        $prevSha = $lock[$key]['sha256'] ?? null;
        $prevVersion = $lock[$key]['version'] ?? null;
        $exists = is_file($path);

        $status = !$exists || $prevSha === null ? 'new' : ($prevSha === $sha ? 'unchanged' : 'updated');
        // El archivo existe pero no coincide con el lock (lo editaron a mano): se repone.
        if ($status === 'unchanged' && hash_file('sha256', $path) !== $sha) {
            $status = 'updated';
        }

        if (!$dryRun) {
            if ($status !== 'unchanged') {
                $write = self::writeAtomic($path, $body, $exists && $status === 'updated');
                if ($write !== null) {
                    return self::result('error', version: $prevVersion, error: $write, path: $path);
                }
                $lock[$key]['changed_at'] = $now;
                $lock[$key]['prev_version'] = $prevVersion;
            }
            $lock[$key] = array_merge($lock[$key] ?? [], [
                'url'        => $url,
                'file'       => (string) $doc['file'],
                'version'    => $version,
                'sha256'     => $sha,
                'bytes'      => strlen($body),
                'checked_at' => $now,
            ]);
            unset($lock[$key]['last_error'], $lock[$key]['last_error_at']);
        }

        return self::result($status, $version, $prevVersion, strlen($body), $sha, null, $path);
    }

    /** Devuelve el motivo de rechazo, o null si el cuerpo es un manual válido. */
    public static function validate(int $status, string $body, int $minBytes = 1): ?string
    {
        if ($status !== 200) {
            return $status === 0 ? 'Sin conexión con el servidor del manual.' : "HTTP {$status}.";
        }
        $trim = ltrim(self::stripBom($body));
        if (strlen($trim) < max(1, $minBytes)) {
            return 'Respuesta demasiado chica (' . strlen($trim) . ' bytes): no parece el manual.';
        }
        if (preg_match('/^<(!doctype|html|\?xml)/i', $trim)) {
            return 'Llegó HTML en vez de Markdown (¿redirección o página de error?).';
        }
        if (!str_starts_with($trim, '#')) {
            return 'El contenido no empieza con un encabezado Markdown (#).';
        }
        return null;
    }

    public static function extractVersion(string $body, string $pattern): ?string
    {
        if ($pattern === '' || @preg_match($pattern, substr($body, 0, 4000), $m) !== 1) {
            return null;
        }
        return isset($m[1]) ? trim($m[1]) : null;
    }

    private static function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }

    /** Escribe vía archivo temporal + rename. Si $keepPrev, el actual pasa a `*.prev.md`. */
    private static function writeAtomic(string $path, string $body, bool $keepPrev): ?string
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return "No se pudo crear el directorio {$dir}.";
        }
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $body) !== strlen($body)) {
            @unlink($tmp);
            return "No se pudo escribir {$tmp}.";
        }
        if ($keepPrev && is_file($path)) {
            $prev = preg_replace('/\.md$/i', '', $path) . '.prev.md';
            @copy($path, $prev);
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return "No se pudo reemplazar {$path}.";
        }
        return null;
    }

    /** @return array<string,array<string,mixed>> */
    private static function readLock(): array
    {
        $f = self::targetDir() . '/' . self::LOCK_FILE;
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($f), true);
        return is_array($data['docs'] ?? null) ? $data['docs'] : [];
    }

    /** @param array<string,array<string,mixed>> $docs */
    private static function writeLock(array $docs): void
    {
        $dir = self::targetDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        ksort($docs);
        $json = json_encode(
            ['_comment' => 'Generado por App\\Services\\RemoteDocs. No editar a mano.', 'docs' => $docs],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        @file_put_contents($dir . '/' . self::LOCK_FILE, $json . "\n");
    }

    /** @return array{status:string,version:?string,prev_version:?string,bytes:int,sha256:?string,error:?string,path:?string} */
    private static function result(
        string $status,
        ?string $version = null,
        ?string $prevVersion = null,
        int $bytes = 0,
        ?string $sha = null,
        ?string $error = null,
        ?string $path = null
    ): array {
        return [
            'status' => $status, 'version' => $version, 'prev_version' => $prevVersion,
            'bytes' => $bytes, 'sha256' => $sha, 'error' => $error, 'path' => $path,
        ];
    }
}
