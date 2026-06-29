<?php

declare(strict_types=1);

namespace App\Services;

use App\Alerts\AlertProvider;
use Throwable;

/**
 * Recolecta alertas de todos los providers registrados y las ordena por severidad.
 */
final class AlertService
{
    private const RANK = ['danger' => 0, 'warning' => 1, 'info' => 2];

    /** @return array<int,class-string> */
    public static function providers(): array
    {
        $file = BASE_PATH . '/config/alert_providers.php';
        return is_file($file) ? (require $file) : [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        $items = [];
        foreach (self::providers() as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }
            $p = new $class();
            if (!$p instanceof AlertProvider) {
                continue;
            }
            try {
                foreach ($p->collect() as $alert) {
                    $items[] = $alert;
                }
            } catch (Throwable) {
                // un provider que falla no rompe el panel
            }
        }
        return self::sort($items);
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    public static function sort(array $items): array
    {
        usort($items, static fn (array $a, array $b): int =>
            (self::RANK[$a['severity'] ?? 'info'] ?? 3) <=> (self::RANK[$b['severity'] ?? 'info'] ?? 3));
        return $items;
    }

    public static function count(): int
    {
        return count(self::all());
    }
}
