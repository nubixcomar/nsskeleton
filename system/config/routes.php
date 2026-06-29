<?php

declare(strict_types=1);

use Core\Router;
use Core\Response;

/**
 * Definición de rutas. Recibe el Router y registra las rutas de la aplicación.
 * Handler puede ser:
 *   - 'NombreController@metodo'         (resuelve a App\Controllers\NombreController)
 *   - [App\Controllers\Foo::class, 'm'] (array clase/método)
 *   - una closure: fn(Request $r) => Response
 */
return static function (Router $router): void {

    $router->get('/', 'HomeController@index');

    // Health-check (útil para deploy/monitoreo). Resumen público.
    $router->get('/health', static fn (): Response => Response::json(\App\Services\Health::summary()));

    // Descarga pública de archivos compartidos por token (sin login).
    $router->get('/a/{token}', 'PublicFileController@download');

    // --- Backend admin ---
    $router->get('/admin/login', 'Admin\AuthController@showLogin');
    $router->post('/admin/login', 'Admin\AuthController@login');
    $router->post('/admin/logout', 'Admin\AuthController@logout');

    // 2FA: desafío en el login (sin sesión todavía).
    $router->get('/admin/2fa', 'Admin\AuthController@show2fa');
    $router->post('/admin/2fa', 'Admin\AuthController@verify2fa');

    // 2FA: configuración (requiere sesión).
    $router->get('/admin/security/2fa', 'Admin\TwoFactorController@setup');
    $router->post('/admin/security/2fa/enable', 'Admin\TwoFactorController@enable');
    $router->post('/admin/security/2fa/disable', 'Admin\TwoFactorController@disable');

    // Recuperación de contraseña.
    $router->get('/admin/forgot', 'Admin\ForgotPasswordController@showForgot');
    $router->post('/admin/forgot', 'Admin\ForgotPasswordController@sendReset');
    $router->get('/admin/reset', 'Admin\ForgotPasswordController@showReset');
    $router->post('/admin/reset', 'Admin\ForgotPasswordController@doReset');

    $router->get('/admin', 'Admin\DashboardController@index');

    // Mi perfil (datos + cambio de contraseña).
    $router->get('/admin/profile', 'Admin\ProfileController@show');
    $router->post('/admin/profile', 'Admin\ProfileController@update');
    $router->post('/admin/profile/password', 'Admin\ProfileController@password');

    // Configuración general.
    $router->get('/admin/settings', 'Admin\SettingsController@show');
    $router->post('/admin/settings', 'Admin\SettingsController@save');

    // Roles y permisos (visor).
    $router->get('/admin/roles', 'Admin\RoleController@index');

    // Estado del sistema (healthcheck + métricas + flags).
    $router->get('/admin/health', 'Admin\HealthController@index');

    // Búsqueda global a través de los módulos.
    $router->get('/admin/search', 'Admin\SearchController@index');

    // Webhooks salientes.
    $router->get('/admin/webhooks', 'Admin\WebhookController@index');
    $router->post('/admin/webhooks', 'Admin\WebhookController@create');
    $router->post('/admin/webhooks/test', 'Admin\WebhookController@test');
    $router->post('/admin/webhooks/{id}/toggle', 'Admin\WebhookController@toggle');
    $router->post('/admin/webhooks/{id}/delete', 'Admin\WebhookController@destroy');

    // Cola de jobs (monitor).
    $router->get('/admin/jobs', 'Admin\JobQueueController@index');
    $router->post('/admin/jobs/run', 'Admin\JobQueueController@runNow');
    $router->post('/admin/jobs/{id}/retry', 'Admin\JobQueueController@retry');
    $router->post('/admin/jobs/{id}/forget', 'Admin\JobQueueController@forget');

    // Notificaciones in-app.
    $router->get('/admin/notifications', 'Admin\NotificationController@index');
    $router->post('/admin/notifications/read-all', 'Admin\NotificationController@readAll');
    $router->post('/admin/notifications/{id}/read', 'Admin\NotificationController@read');

    // Auditoría.
    $router->get('/admin/audit', 'Admin\AuditController@index');

    // Tokens de API.
    $router->get('/admin/api-tokens', 'Admin\ApiTokenController@index');
    $router->post('/admin/api-tokens', 'Admin\ApiTokenController@create');
    $router->post('/admin/api-tokens/{id}/revoke', 'Admin\ApiTokenController@revoke');

    // ===== API REST (Bearer token) =====
    // Documentación (antes del wildcard): spec OpenAPI + visor.
    // Dos rutas: sin extensión (anda también en el server embebido de PHP) y .json (Apache/herramientas).
    $router->get('/api/openapi', 'Api\DocsController@openapi');
    $router->get('/api/openapi.json', 'Api\DocsController@openapi');
    $router->get('/api/docs', 'Api\DocsController@ui');

    $router->get('/api/{resource}', 'Api\ResourceController@index');
    $router->post('/api/{resource}', 'Api\ResourceController@store');
    $router->get('/api/{resource}/{id}', 'Api\ResourceController@show');
    $router->put('/api/{resource}/{id}', 'Api\ResourceController@update');
    $router->patch('/api/{resource}/{id}', 'Api\ResourceController@update');
    $router->delete('/api/{resource}/{id}', 'Api\ResourceController@destroy');

    // Gestión de perfiles de administrador (CRUD).
    $router->get('/admin/users', 'Admin\AdminUserController@index');
    $router->get('/admin/users/create', 'Admin\AdminUserController@create');
    $router->post('/admin/users', 'Admin\AdminUserController@store');
    $router->get('/admin/users/{id}/edit', 'Admin\AdminUserController@edit');
    $router->get('/admin/users/{id}/permissions', 'Admin\UserPermissionController@edit');
    $router->post('/admin/users/{id}/permissions', 'Admin\UserPermissionController@update');
    $router->post('/admin/users/{id}', 'Admin\AdminUserController@update');
    $router->post('/admin/users/{id}/delete', 'Admin\AdminUserController@destroy');

    // Guardado de la matriz de roles (editable).
    $router->post('/admin/roles', 'Admin\RoleController@update');

    // Tareas programadas (cronmaster).
    $router->get('/admin/cron', 'Admin\CronController@index');
    $router->get('/admin/cron/create', 'Admin\CronController@create');
    $router->post('/admin/cron', 'Admin\CronController@store');
    $router->get('/admin/cron/{id}/edit', 'Admin\CronController@edit');
    $router->post('/admin/cron/{id}', 'Admin\CronController@update');
    $router->post('/admin/cron/{id}/toggle', 'Admin\CronController@toggle');
    $router->post('/admin/cron/{id}/run', 'Admin\CronController@runNow');
    $router->post('/admin/cron/{id}/delete', 'Admin\CronController@destroy');

    // Emails (configuración SMTP + prueba + historial).
    $router->get('/admin/mail', 'Admin\MailController@settings');
    $router->post('/admin/mail', 'Admin\MailController@saveSettings');
    $router->post('/admin/mail/test', 'Admin\MailController@test');
    $router->get('/admin/mail/log', 'Admin\MailController@log');
    $router->get('/admin/mail/queue', 'Admin\MailController@queue');
    $router->post('/admin/mail/queue/process', 'Admin\MailController@processQueue');

    // File manager (carpetas/subcarpetas + uploads).
    $router->get('/admin/files', 'Admin\FilesController@index');
    $router->get('/admin/files/download', 'Admin\FilesController@download');
    $router->get('/admin/files/raw', 'Admin\FilesController@raw');
    $router->post('/admin/files/upload', 'Admin\FilesController@upload');
    $router->post('/admin/files/share', 'Admin\FilesController@share');
    $router->post('/admin/files/unshare', 'Admin\FilesController@unshare');
    $router->post('/admin/files/mkdir', 'Admin\FilesController@mkdir');
    $router->post('/admin/files/rename', 'Admin\FilesController@rename');
    $router->post('/admin/files/move', 'Admin\FilesController@move');
    $router->post('/admin/files/delete', 'Admin\FilesController@delete');

    // Conector de IA (OpenAI / Deepseek).
    $router->get('/admin/ai', 'Admin\AiController@settings');
    $router->post('/admin/ai', 'Admin\AiController@saveSettings');
    $router->post('/admin/ai/test', 'Admin\AiController@test');
    $router->get('/admin/ai/stream', 'Admin\AiController@stream');

    // Rutas de módulos generados (ver system/console/make-module.php).
    foreach (glob(BASE_PATH . '/config/routes/*.php') ?: [] as $moduleRoutes) {
        $register = require $moduleRoutes;
        if (is_callable($register)) {
            $register($router);
        }
    }

    // Rutas de la APP (punto de extensión, no se pisa al actualizar el core).
    // Definí rutas propias en config/routes.app.php devolviendo `fn(Router $router) => ...`.
    // Se cargan después de las del core: si repetís una ruta, gana la de la app.
    $appRoutes = BASE_PATH . '/config/routes.app.php';
    if (is_file($appRoutes)) {
        $register = require $appRoutes;
        if (is_callable($register)) {
            $register($router);
        }
    }

    // Backups (base de datos + archivos).
    $router->get('/admin/backup', 'Admin\BackupController@index');
    $router->post('/admin/backup/db', 'Admin\BackupController@createDb');
    $router->post('/admin/backup/files', 'Admin\BackupController@createFiles');
    $router->post('/admin/backup/full', 'Admin\BackupController@createFull');
    $router->get('/admin/backup/download/{name}', 'Admin\BackupController@download');
    $router->post('/admin/backup/restore', 'Admin\BackupController@restore');
    $router->post('/admin/backup/delete', 'Admin\BackupController@destroy');
};
