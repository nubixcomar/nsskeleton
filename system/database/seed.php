<?php

declare(strict_types=1);

/**
 * Seed (CLI): crea un administrador por defecto si no existe ninguno.
 *   Uso:  php system/database/seed.php
 *
 * Credenciales por defecto (CAMBIAR tras el primer login):
 *   email:    admin@nsskeleton.local
 *   password: admin1234
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));

require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use Core\Database;
use Core\Auth;

Env::load(PROJECT_PATH . '/.env');

$existing = Database::selectOne('SELECT COUNT(*) AS n FROM admin_users');
if ($existing !== null && (int) $existing['n'] > 0) {
    echo "Ya existe al menos un administrador. No se crea el seed.\n";
    exit(0);
}

Database::insert(
    'INSERT INTO admin_users (name, email, password, role, active) VALUES (?, ?, ?, ?, 1)',
    ['Administrador', 'admin@nsskeleton.local', Auth::hash('admin1234'), 'superadmin']
);

echo "Administrador por defecto creado:\n";
echo "  email:    admin@nsskeleton.local\n";
echo "  password: admin1234   (cambialo tras el primer login)\n";
