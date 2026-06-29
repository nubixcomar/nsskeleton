<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 mínimo (sin Composer).
 *   Core\*            → system/app/Core/*
 *   App\Controllers\* → system/app/Controllers/*
 *   App\Models\*      → system/app/Models/*
 *   App\Services\*    → system/app/Services/*
 */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Core\\' => BASE_PATH . '/app/Core/',
        'App\\'  => BASE_PATH . '/app/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
