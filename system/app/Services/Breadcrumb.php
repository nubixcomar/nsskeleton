<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Breadcrumb del backend, derivado automáticamente de la ruta actual + el menú
 * (config/menu.php + módulos generados). Estructura:
 *   Inicio > [Grupo] > [Módulo] > [Acción] > [Registro]
 *
 * Para mostrar el nombre del registro en una acción (ej. "Editar > Juan Pérez"), el
 * controlador pasa `$breadcrumbExtra` a la vista:
 *   return $this->view('admin/users/form', ['editing' => $u, 'breadcrumbExtra' => $u['name']]);
 *
 * Para un trail totalmente custom, pasar `$breadcrumb` (array de ['label','url']).
 */
final class Breadcrumb
{
    /** Rutas del backend que no están en el menú (clave => etiqueta). */
    private const EXTRA = [
        '/admin/profile'      => 'Mi perfil',
        '/admin/notifications' => 'Notificaciones',
        '/admin/security/2fa' => 'Seguridad (2FA)',
        '/admin/2fa'          => 'Verificación 2FA',
        '/admin/search'       => 'Búsqueda',
    ];

    /** Último segmento de la URL → etiqueta de acción. */
    private const ACTIONS = [
        'create'      => 'Nuevo',
        'edit'        => 'Editar',
        'permissions' => 'Permisos',
        'trash'       => 'Papelera',
        'export'      => 'Exportar',
        'docs'        => 'Documentación',
        'log'         => 'Historial',
        'queue'       => 'Cola',
    ];

    /**
     * Devuelve el camino de migas para una ruta. $extra (opcional) se agrega como
     * última miga (ej. el nombre del registro en editar/ver).
     * @return array<int,array{label:string,url:?string}>
     */
    public static function trail(string $reqPath, ?string $extra = null): array
    {
        $reqPath = '/' . trim($reqPath, '/');
        $crumbs = [['label' => 'Inicio', 'url' => '/admin']];

        if ($reqPath === '/admin' || $reqPath === '/') {
            return self::appendExtra($crumbs, $extra); // dashboard (extra raro, pero soportado)
        }

        [$group, $label, $path] = self::matchMenu($reqPath);

        if ($group !== null) {
            $crumbs[] = ['label' => $group, 'url' => null]; // los grupos no navegan
        }

        if ($label !== null) {
            $crumbs[] = ['label' => $label, 'url' => $path];
        } else {
            // No está en el menú: buscar en EXTRA (exacto o por prefijo).
            $extraLabel = self::EXTRA[$reqPath] ?? null;
            if ($extraLabel === null) {
                foreach (self::EXTRA as $p => $l) {
                    if (str_starts_with($reqPath, $p . '/')) {
                        $extraLabel = $l;
                        $path = $p;
                        break;
                    }
                }
            } else {
                $path = $reqPath;
            }
            if ($extraLabel !== null) {
                $crumbs[] = ['label' => $extraLabel, 'url' => $path];
            }
        }

        // Acción: lo que queda después del path del módulo.
        $base = $path ?? '/admin';
        $rest = trim(substr($reqPath, strlen($base)), '/');
        if ($rest !== '') {
            $segs = explode('/', $rest);
            $last = (string) end($segs);
            $action = self::ACTIONS[$last] ?? (is_numeric($last) ? 'Detalle' : ucfirst($last));
            $crumbs[] = ['label' => $action, 'url' => null];
        }

        // El último siempre es la página actual (sin link).
        $crumbs[count($crumbs) - 1]['url'] = null;

        return self::appendExtra($crumbs, $extra);
    }

    /**
     * Agrega el nombre del registro como última miga (si lo hay).
     * @param array<int,array{label:string,url:?string}> $crumbs
     * @return array<int,array{label:string,url:?string}>
     */
    private static function appendExtra(array $crumbs, ?string $extra): array
    {
        $extra = $extra !== null ? trim($extra) : '';
        if ($extra === '') {
            return $crumbs;
        }
        // El crumb anterior deja de ser "actual" (recupera link si tenía path candidato).
        $crumbs[] = ['label' => mb_strimwidth($extra, 0, 60, '…'), 'url' => null];
        return $crumbs;
    }

    /**
     * Busca el ítem de menú cuyo path sea el prefijo más largo de $reqPath.
     * @return array{0:?string,1:?string,2:?string} [grupo, etiqueta, path]
     */
    private static function matchMenu(string $reqPath): array
    {
        $best = null;
        $consider = static function (?string $group, string $path, string $label) use (&$best, $reqPath): void {
            if ($path === '' || $path === '/admin') {
                return; // /admin = Inicio, no es módulo
            }
            if ($reqPath === $path || str_starts_with($reqPath, $path . '/')) {
                $len = strlen($path);
                if ($best === null || $len > $best['len']) {
                    $best = ['group' => $group, 'label' => $label, 'path' => $path, 'len' => $len];
                }
            }
        };

        $menuFile = BASE_PATH . '/config/menu.php';
        $menu = is_file($menuFile) ? (require $menuFile) : ['top' => [], 'groups' => []];
        foreach ($menu['top'] ?? [] as $it) {
            $consider(null, (string) ($it[0] ?? ''), (string) ($it[1] ?? ''));
        }
        foreach ($menu['groups'] ?? [] as $g) {
            foreach ($g['items'] ?? [] as $it) {
                $consider((string) ($g['title'] ?? ''), (string) ($it[0] ?? ''), (string) ($it[1] ?? ''));
            }
        }

        $modFile = BASE_PATH . '/config/modules_menu.php';
        if (is_file($modFile)) {
            foreach ((array) require $modFile as $m) {
                $consider('Módulos', (string) ($m['path'] ?? ''), (string) ($m['label'] ?? ''));
            }
        }

        return $best === null ? [null, null, null] : [$best['group'], $best['label'], $best['path']];
    }
}
