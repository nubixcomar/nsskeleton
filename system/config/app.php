<?php

declare(strict_types=1);

use Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'nsSkeleton'),
    'env'      => Env::get('APP_ENV', 'local'),
    'debug'    => (bool) Env::get('APP_DEBUG', true),
    'url'      => Env::get('APP_URL', 'http://localhost'),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
];
