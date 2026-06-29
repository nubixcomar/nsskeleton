<?php

declare(strict_types=1);

/**
 * Menú del backend, agrupado. Cada ítem: [path, label, permiso, icono].
 * Cada grupo: title + icon (se muestra en el rail colapsado) + items.
 * Iconos disponibles: ver Core\Icons. Los módulos generados que no estén acá se
 * agregan automáticamente bajo un grupo "Módulos".
 */
return [
    'top' => [
        ['/admin', 'Dashboard', 'dashboard.view', 'home'],
    ],

    'groups' => [
        [
            'title' => 'Utilitarios',
            'icon'  => 'folder',
            'items' => [
                ['/admin/files', 'Archivos', 'files.manage', 'folder'],
            ],
        ],
        [
            'title' => 'Usuarios',
            'icon'  => 'users',
            'items' => [
                ['/admin/users',    'Administradores', 'admins.manage', 'user-circle'],
                ['/admin/roles',    'Roles',           'admins.manage', 'shield'],
                ['/admin/audit',    'Auditoría',       'audit.view',    'list'],
            ],
        ],
        [
            'title' => 'Configuración',
            'icon'  => 'adjustments',
            'items' => [
                ['/admin/settings',   'General',     'settings.manage', 'adjustments'],
                ['/admin/mail',       'Emails',      'mail.manage',     'envelope'],
                ['/admin/ai',         'Conector IA', 'ai.manage',       'cpu'],
                ['/admin/api-tokens', 'API',         'api.manage',      'key'],
                ['/admin/webhooks',   'Webhooks',    'api.manage',      'bolt'],
            ],
        ],
        [
            'title' => 'Sistema',
            'icon'  => 'server',
            'items' => [
                ['/admin/backup', 'Backups',       'backup.manage', 'archive'],
                ['/admin/health', 'Estado',        'dashboard.view', 'heart'],
                ['/admin/cron',   'Tareas / Cron', 'cron.manage',   'clock'],
                ['/admin/jobs',   'Colas / Jobs',  'cron.manage',   'queue'],
            ],
        ],
    ],
];
