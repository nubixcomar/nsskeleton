<?php

declare(strict_types=1);

/**
 * PL-002/F5 — Barrido de permisos del admin (BR-001 CS1, CS2, CS3; caso borde 6).
 *
 * Recorre TODAS las rutas registradas (core + módulos + routes.app.php si existen) y, para cada
 * controlador admin, verifica el permiso que exige. Un controlador nuevo sin permiso, o con uno que
 * tiene el rol `viewer`, rompe el test.
 *
 * ⚠ Nunca construir controladores admin en tests: el constructor hace redirect()->send(); exit;
 * y corta la suite con un falso verde. Se usa newInstanceWithoutConstructor().
 */

use App\Controllers\Admin\AdminController;
use App\Services\Rbac;
use Core\Config;
use Core\Router;

/** Controlador de prueba SIN declaración propia: debe caer en el default. Nunca se instancia con new. */
final class __DefaultPermProbe extends AdminController
{
}

group('Permisos del admin · barrido de rutas (PL-002/F5)');

/** Permiso que exige un controlador, sin ejecutar su constructor. */
$permOf = static function (string $class): string {
    $obj = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    return (string) (new ReflectionMethod($obj, 'requiredPermission'))->invoke($obj);
};

it('toda ruta de un controlador admin exige un permiso que viewer no tiene (o es AUTH_ONLY a propósito)', function () use ($permOf) {
    $router = new Router();
    (require BASE_PATH . '/config/routes.php')($router);
    $ref = new ReflectionObject($router);
    $index = $ref->getProperty('index')->getValue($router);
    $routes = $ref->getProperty('routes')->getValue($router);

    $lockData = json_decode((string) file_get_contents(PROJECT_PATH . '/core-lock.json'), true);
    $lock = is_array($lockData) ? ($lockData['files'] ?? []) : [];
    $catalog = array_keys(Rbac::catalog());
    $viewer = (require BASE_PATH . '/config/permissions.php')['roles']['viewer'] ?? [];
    $authOnlyOk = [
        \App\Controllers\Admin\DashboardController::class,     // abierto; cada widget exige su permiso
        \App\Controllers\Admin\ProfileController::class,       // cada usuario, su perfil
        \App\Controllers\Admin\NotificationController::class,  // cada usuario, sus notificaciones
        \App\Controllers\Admin\TwoFactorController::class,     // cada usuario, su 2FA
    ];
    $root = str_replace('\\', '/', (string) PROJECT_PATH);

    $checked = 0;
    $problems = [];
    foreach ($index as $key => $pos) {
        $handler = $routes[$pos]['handler'] ?? null;
        $class = null;
        if (is_string($handler) && str_contains($handler, '@')) {
            $class = 'App\\Controllers\\' . explode('@', $handler, 2)[0];
        } elseif (is_array($handler) && isset($handler[0]) && is_string($handler[0])) {
            $class = $handler[0];
        }
        if ($class === null || !class_exists($class) || !is_subclass_of($class, AdminController::class)) {
            continue; // closures, login, forgot/reset, API, públicos
        }
        $checked++;
        $perm = $permOf($class);
        if ($perm === AdminController::AUTH_ONLY) {
            if (!in_array($class, $authOnlyOk, true)) {
                $problems[] = "{$key} → {$class} no exige permiso";
            }
            continue;
        }
        if (in_array($perm, $viewer, true)) {
            $problems[] = "{$key} → viewer tiene '{$perm}'";
        }
        $file = str_replace('\\', '/', (string) (new ReflectionClass($class))->getFileName());
        $rel = ltrim(substr($file, strlen($root)), '/');
        if (isset($lock[$rel]) && !in_array($perm, $catalog, true)) {
            $problems[] = "{$key} → '{$perm}' no está en el catálogo de permisos";
        }
    }

    assertTrue($checked > 50, "el barrido debería cubrir las rutas del admin (cubrió {$checked})");
    assertEquals([], $problems, implode(' | ', array_slice($problems, 0, 8)));
});

it('un controlador que no declara permiso cae en el default seguro (modules.manage)', function () use ($permOf) {
    Config::flush();
    assertEquals('modules.manage', $permOf(__DefaultPermProbe::class));
    $viewer = (require BASE_PATH . '/config/permissions.php')['roles']['viewer'] ?? [];
    assertFalse(in_array('modules.manage', $viewer, true), 'el default no debe estar al alcance de viewer');
});

it('admin_default_permission = null restaura el comportamiento previo (solo sesión)', function () use ($permOf) {
    $cache = new ReflectionProperty(Config::class, 'cache');
    try {
        $cache->setValue(null, ['security' => ['admin_default_permission' => null]]);
        assertEquals(AdminController::AUTH_ONLY, $permOf(__DefaultPermProbe::class));
    } finally {
        Config::flush();
    }
});
