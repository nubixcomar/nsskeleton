<?php

declare(strict_types=1);

namespace App\Services;

use Core\Auth;
use Core\Database;
use Throwable;

/**
 * Control de acceso basado en roles (RBAC).
 * Defaults en config/permissions.php; overrides de rol en settings (grupo `rbac_roles`)
 * y overrides por usuario en la tabla `user_permissions`.
 */
final class Rbac
{
    /** @return array<string,mixed> */
    private static function config(): array
    {
        return require BASE_PATH . '/config/permissions.php';
    }

    /** @return array<int,string> */
    public static function roles(): array
    {
        return array_keys(self::config()['roles'] ?? []);
    }

    /** Catálogo de permisos disponibles. @return array<string,string> clave => etiqueta */
    public static function catalog(): array
    {
        return self::config()['catalog'] ?? [];
    }

    /** @return array<int,string> permisos efectivos del rol (override de settings o config) */
    public static function permissionsFor(string $role): array
    {
        if ($role === 'superadmin') {
            return ['*'];
        }
        try {
            $override = Settings::group('rbac_roles')[$role] ?? null;
        } catch (Throwable) {
            $override = null;
        }
        if (is_string($override) && $override !== '') {
            $decoded = json_decode($override, true);
            if (is_array($decoded)) {
                return array_values(array_map('strval', $decoded));
            }
        }
        return self::config()['roles'][$role] ?? [];
    }

    /** Persiste los permisos de un rol (override editable). @param array<int,string> $perms */
    public static function setRolePermissions(string $role, array $perms): void
    {
        if ($role === 'superadmin') {
            return; // superadmin siempre tiene todo
        }
        $perms = array_values(array_intersect(array_keys(self::catalog()), $perms));
        Settings::set('rbac_roles.' . $role, json_encode($perms), 'rbac_roles');
    }

    /** Overrides por usuario. @return array<string,bool> permiso => true(allow)/false(deny) */
    public static function userOverrides(int $userId): array
    {
        try {
            $rows = Database::select('SELECT permission, effect FROM user_permissions WHERE user_id = ?', [$userId]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['permission']] = (int) $r['effect'] === 1;
        }
        return $out;
    }

    /** Define/quita un override de usuario. $effect: true=permitir, false=denegar, null=heredar. */
    public static function setUserPermission(int $userId, string $permission, ?bool $effect): void
    {
        if (!array_key_exists($permission, self::catalog())) {
            return;
        }
        if ($effect === null) {
            Database::run('DELETE FROM user_permissions WHERE user_id = ? AND permission = ?', [$userId, $permission]);
            return;
        }
        Database::run(
            'INSERT INTO user_permissions (user_id, permission, effect) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE effect = VALUES(effect)',
            [$userId, $permission, $effect ? 1 : 0]
        );
    }

    /**
     * ¿El usuario (o el admin logueado) tiene el permiso?
     * Precedencia: override del usuario (deny/allow) > permisos del rol.
     * @param array<string,mixed>|null $user
     */
    public static function can(string $permission, ?array $user = null): bool
    {
        if ($user === null) {
            try {
                $user = Auth::user();
            } catch (Throwable) {
                $user = null;
            }
        }

        $role = (string) ($user['role'] ?? '');
        if ($role === '') {
            return false;
        }

        // Override por usuario tiene prioridad (deny y allow).
        $userId = (int) ($user['id'] ?? 0);
        if ($userId > 0) {
            $overrides = self::userOverrides($userId);
            if (array_key_exists($permission, $overrides)) {
                return $overrides[$permission];
            }
        }

        $perms = self::permissionsFor($role);
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }
}
