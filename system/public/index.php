<?php

declare(strict_types=1);

/**
 * Front controller — único punto de entrada de la aplicación.
 * Todas las peticiones pasan por acá (ver public/.htaccess).
 */

define('BASE_PATH', dirname(__DIR__));            // .../system
define('PROJECT_PATH', dirname(BASE_PATH));       // .../skeleton (raíz del proyecto)

// Servidor PHP integrado (desarrollo): servir archivos estáticos existentes tal cual.
// En Apache esto lo resuelve public/.htaccess (RewriteCond !-f).
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (is_file($file)) {
        return false;
    }
}

require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use Core\App;

// Carga variables de entorno desde la raíz del proyecto (.env).
Env::load(PROJECT_PATH . '/.env');

(new App())->run();
