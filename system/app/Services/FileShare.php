<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * Links públicos de archivos por token: descarga sin login en /a/{token}.
 */
final class FileShare
{
    /** Crea (o devuelve) el token público de un archivo. */
    public static function share(string $relPath): string
    {
        $existing = self::byPath($relPath);
        if ($existing !== null) {
            return (string) $existing['token'];
        }
        $token = bin2hex(random_bytes(16));
        Database::insert('INSERT INTO file_shares (token, rel_path) VALUES (?, ?)', [$token, $relPath]);
        return $token;
    }

    public static function unshare(string $relPath): void
    {
        Database::run('DELETE FROM file_shares WHERE rel_path = ?', [$relPath]);
    }

    /** @return array<string,mixed>|null */
    public static function byPath(string $relPath): ?array
    {
        try {
            return Database::selectOne('SELECT * FROM file_shares WHERE rel_path = ? LIMIT 1', [$relPath]);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public static function byToken(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        try {
            return Database::selectOne('SELECT * FROM file_shares WHERE token = ? LIMIT 1', [$token]);
        } catch (Throwable) {
            return null;
        }
    }

    public static function countDownload(int $id): void
    {
        try {
            Database::run('UPDATE file_shares SET downloads = downloads + 1 WHERE id = ?', [$id]);
        } catch (Throwable) {
        }
    }

    /** Mapa rel_path => token de todos los archivos compartidos (para el listado). @return array<string,string> */
    public static function map(): array
    {
        try {
            $rows = Database::select('SELECT rel_path, token FROM file_shares');
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['rel_path']] = (string) $r['token'];
        }
        return $out;
    }
}
