<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Tokens de API (Bearer). Se persiste solo el hash; el token en claro se muestra
 * una sola vez al crearlo.
 */
final class ApiToken
{
    /** Genera un token para un admin y devuelve el valor en claro (no se vuelve a ver). */
    public static function generate(int $adminId, string $name, string $scopes = 'read,write'): string
    {
        $token = 'nsk_' . bin2hex(random_bytes(24));
        Database::insert(
            'INSERT INTO api_tokens (admin_id, name, scopes, token_hash) VALUES (?, ?, ?, ?)',
            [$adminId, $name !== '' ? $name : 'token', self::normalizeScopes($scopes), hash('sha256', $token)]
        );
        return $token;
    }

    public static function normalizeScopes(string $scopes): string
    {
        $allowed = ['read', 'write'];
        $parts = array_values(array_intersect($allowed, array_map('trim', explode(',', strtolower($scopes)))));
        return $parts === [] ? 'read' : implode(',', $parts);
    }

    /**
     * Resuelve un token: admin + scopes + id (para autenticar la API).
     * @return array{admin:array<string,mixed>,scopes:array<int,string>,token_id:int}|null
     */
    public static function resolve(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $row = Database::selectOne('SELECT * FROM api_tokens WHERE token_hash = ? LIMIT 1', [hash('sha256', $token)]);
        if ($row === null) {
            return null;
        }
        $admin = Database::selectOne(
            'SELECT id, name, email, role, active FROM admin_users WHERE id = ? LIMIT 1',
            [$row['admin_id']]
        );
        if ($admin === null || (int) $admin['active'] !== 1) {
            return null;
        }
        Database::run('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?', [$row['id']]);

        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) ($row['scopes'] ?? 'read,write')))));
        return ['admin' => $admin, 'scopes' => $scopes, 'token_id' => (int) $row['id']];
    }

    /**
     * Valida un token y devuelve el admin (activo) asociado, o null.
     * @return array<string,mixed>|null
     */
    public static function validate(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $row = Database::selectOne('SELECT * FROM api_tokens WHERE token_hash = ? LIMIT 1', [hash('sha256', $token)]);
        if ($row === null) {
            return null;
        }

        $admin = Database::selectOne(
            'SELECT id, name, email, role, active FROM admin_users WHERE id = ? LIMIT 1',
            [$row['admin_id']]
        );
        if ($admin === null || (int) $admin['active'] !== 1) {
            return null;
        }

        Database::run('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?', [$row['id']]);
        return $admin;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::select(
            'SELECT t.*, a.name AS admin_name FROM api_tokens t
             LEFT JOIN admin_users a ON a.id = t.admin_id
             ORDER BY t.id DESC'
        );
    }

    public static function revoke(int $id): void
    {
        Database::run('DELETE FROM api_tokens WHERE id = ?', [$id]);
    }
}
