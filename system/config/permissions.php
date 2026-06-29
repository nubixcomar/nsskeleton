<?php

declare(strict_types=1);

/**
 * Matriz de roles → permisos. `*` = todos los permisos.
 * Los permisos siguen la convención `recurso.accion`.
 */
return [
    // Catálogo de permisos (clave => etiqueta) para la matriz editable.
    'catalog' => [
        'dashboard.view'  => 'Ver dashboard',
        'admins.manage'   => 'Gestionar administradores',
        'cron.manage'     => 'Gestionar tareas (cron)',
        'mail.manage'     => 'Gestionar emails',
        'backup.manage'   => 'Gestionar backups',
        'files.manage'    => 'Gestionar archivos',
        'ai.manage'       => 'Gestionar IA',
        'settings.manage' => 'Gestionar configuración',
        'audit.view'      => 'Ver auditoría',
        'api.manage'      => 'Gestionar API',
        'modules.manage'  => 'Gestionar módulos',
    ],
    'roles' => [
        'superadmin' => ['*'],
        'admin' => [
            'dashboard.view',
            'admins.manage',
            'cron.manage',
            'mail.manage',
            'backup.manage',
            'files.manage',
            'ai.manage',
            'settings.manage',
            'audit.view',
            'api.manage',
            'modules.manage',
        ],
        'editor' => [
            'dashboard.view',
            'files.manage',
            'modules.manage',
        ],
        'viewer' => [
            'dashboard.view',
        ],
    ],
];
