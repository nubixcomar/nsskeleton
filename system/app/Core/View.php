<?php

declare(strict_types=1);

namespace Core;

/**
 * Renderizador de vistas PHP planas con soporte de layout.
 *
 * Las vistas del core viven en `system/app/Views/{nombre}.php`. La app puede
 * **sobreescribir** cualquiera (layout, parcial, vista) colocando un archivo con
 * el mismo nombre en `system/app/Views/overrides/{nombre}.php` (patrón child-theme):
 * se resuelve **primero el override**, luego el del core. Así personalizás vistas
 * sin editar las del core → el actualizador no pisa tu personalización.
 */
final class View
{
    /** Resuelve la ruta de una vista: override de la app primero, luego core. */
    private static function resolve(string $view): ?string
    {
        foreach (['/app/Views/overrides/', '/app/Views/'] as $dir) {
            $file = BASE_PATH . $dir . $view . '.php';
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Renderiza una vista (envuelta en un layout) y devuelve una Response HTML.
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app', int $status = 200): Response
    {
        $content = self::partial($view, $data);

        if ($layout !== null && self::exists($layout)) {
            $content = self::partial($layout, array_merge($data, ['content' => $content]));
        }

        return Response::html($content, $status);
    }

    /**
     * Renderiza una vista parcial y devuelve el string (sin layout).
     */
    public static function partial(string $view, array $data = []): string
    {
        $file = self::resolve($view);
        if ($file === null) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function exists(string $view): bool
    {
        return self::resolve($view) !== null;
    }

    /** Escape HTML para usar en vistas: <?= View::e($x) ?> */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
