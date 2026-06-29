<?php

declare(strict_types=1);

/**
 * Presets de dashboard: combinaciones de bloques reutilizables.
 * El activo se resuelve desde settings `dashboard.preset` (editable en Configuración),
 * con fallback a `default`. Bloques disponibles: alerts, kpis, charts, feed, recent, modules.
 */
return [
    'default' => 'completo',
    'presets' => [
        'completo'  => ['label' => 'Completo', 'blocks' => ['alerts', 'kpis', 'charts', 'feed', 'recent', 'modules']],
        'operativo' => ['label' => 'Operativo / Monitoreo', 'blocks' => ['alerts', 'kpis', 'recent', 'feed', 'charts']],
        'showcase'  => ['label' => 'Showcase / Demo', 'blocks' => ['feed', 'kpis', 'charts', 'modules']],
        'minimo'    => ['label' => 'Mínimo', 'blocks' => ['kpis', 'charts', 'modules']],
    ],
];
