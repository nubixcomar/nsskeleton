<?php

declare(strict_types=1);

use Core\Env;

/**
 * Actualización de core (v1.20). Overrideable desde config/overrides/core_update.php.
 */
return [
    // URL del índice de releases (latest.json) que publica build-core-package.php en el
    // landing. Ej: https://misitio.com/downloads/latest.json. Vacío = sin chequeo remoto.
    'latest_url' => (string) Env::get('CORE_UPDATE_URL', ''),

    // Pantalla de admin /admin/core-update. false = la ruta responde 404 (solo CLI).
    'ui_enabled' => !in_array(strtolower((string) Env::get('CORE_UPDATE_UI', 'true')), ['0', 'false', 'off', 'no'], true),

    // Merge 3-way automático de conflictos (requiere la base en storage/core-base/).
    'merge' => true,
];
