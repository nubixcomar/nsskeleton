<?php

declare(strict_types=1);

/**
 * Recursos expuestos por la API REST (/api/{resource}).
 * Cada recurso: 'model' (clase de modelo) y 'fields' (whitelist con su tipo).
 *
 * El core no trae recursos por defecto: registrá los tuyos acá (o el generador de
 * módulos los agrega). Ejemplo:
 *   if (class_exists(\App\Models\MiModelo::class)) {
 *       $resources['mi_recurso'] = ['model' => \App\Models\MiModelo::class, 'fields' => ['nombre' => 'string']];
 *   }
 */

$resources = [];

return [
    'resources'    => $resources,
    'rate_limit'   => 60,  // peticiones por minuto y por token
    'per_page'     => 20,  // tamaño de página por defecto
    'max_per_page' => 100, // tope de page size
];
