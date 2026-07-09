<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 mínimo (sin Composer).
 *
 *   Core\*            → system/app/Core/*                    (núcleo)
 *   App\*             → system/app/*        (core, primero)  (código core del skeleton)
 *                       app/*               (proyecto, luego) (TU código, fuera de core-lock)
 *
 * Frontera core/app (hallazgo 1.6 de la auditoría de nsCheckout): el código PROPIO del
 * proyecto va en `app/` (raíz), NO en `system/app/` (que es core-lock-tracked y lo pisa el
 * actualizador de core). Ambos comparten el namespace `App\` y la misma convención de
 * handlers de rutas; el autoloader busca primero en el core y luego en tu `app/`, así podés
 * agregar controllers/models/services propios sin generar "drift" del core.
 */
spl_autoload_register(static function (string $class): void {
    $root = dirname(BASE_PATH); // raíz del proyecto (.../skeleton)

    /** @var array<string,list<string>> cada prefijo → carpetas en orden de precedencia */
    $prefixes = [
        'Core\\' => [BASE_PATH . '/app/Core/'],
        'App\\'  => [BASE_PATH . '/app/', $root . '/app/'],
    ];

    foreach ($prefixes as $prefix => $dirs) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        foreach ($dirs as $baseDir) {
            $file = $baseDir . $relative . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});
