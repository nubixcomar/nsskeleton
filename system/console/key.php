<?php

declare(strict_types=1);

/**
 * Genera una APP_KEY para cifrado de secretos.
 *   php system/console/key.php
 * Copiá la línea resultante a tu archivo .env.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/autoload.php';

use Core\Crypto;

echo 'APP_KEY=' . Crypto::generateKey() . "\n";
