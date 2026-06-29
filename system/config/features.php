<?php

declare(strict_types=1);

/**
 * Feature flags: interruptores de funcionalidades. Valor = default.
 * Se pueden sobreescribir por panel (se guardan en settings, grupo `flags`).
 */
return [
    'maintenance_banner' => false, // muestra un aviso de mantenimiento en el backend
    'registro_publico'   => false, // ejemplo: habilitar auto-registro (no implementado)
    'exportar_listados'  => true,  // ejemplo: mostrar botones de exportación
];
