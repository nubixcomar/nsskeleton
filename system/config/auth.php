<?php

declare(strict_types=1);

/**
 * Guards de autenticación (v1.17 #7, hallazgo 1.9). Overrideable en `config/overrides/auth.php`.
 *
 * Cada guard es un "login independiente": su propia tabla de usuarios, su propia clave de
 * sesión (sesiones aisladas entre guards), su prefijo de rutas, path de login y redirect
 * post-login. Así una app multi-rol/multi-tenant (ej. panel de marca + panel admin + cuenta
 * de cliente) no reimplementa auth ni toca el core — el patrón que nsCheckout tuvo que armar
 * a mano (`BrandAuth`) nace acá.
 *
 * `Core\Auth` es la fachada del guard `default` (retrocompat: misma tabla y clave de sesión).
 * Para otros guards: `Core\Guard::for('brand')->attempt($login, $pass)`.
 */
return [
    'default' => 'admin',

    'guards' => [
        // Guard del backend admin. NO cambiar session_key ni table: mantiene compatibilidad
        // con el Core\Auth histórico.
        'admin' => [
            'table'         => 'admin_users',
            'session_key'   => '_auth_user_id',
            'login_columns' => ['email', 'username'],
            'active_column' => 'active',
            'login_path'    => '/admin/login',
            'redirect'      => '/admin',
        ],

        // Guards de EJEMPLO para apps multi-tenant. El core define el mecanismo; el proyecto
        // crea la tabla correspondiente (ej. una migración en database/migrations/app/).
        'brand' => [
            'table'         => 'brand_users',
            'session_key'   => '_auth_brand_id',
            'login_columns' => ['email'],
            'active_column' => 'active',
            'login_path'    => '/panel/login',
            'redirect'      => '/panel',
        ],
        'customer' => [
            'table'         => 'customers',
            'session_key'   => '_auth_customer_id',
            'login_columns' => ['email'],
            'active_column' => null, // sin columna 'active'
            'login_path'    => '/cuenta/login',
            'redirect'      => '/cuenta',
        ],
    ],
];
