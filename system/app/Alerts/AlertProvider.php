<?php

declare(strict_types=1);

namespace App\Alerts;

/**
 * Proveedor de alertas computadas. Cada implementación inspecciona el sistema y
 * devuelve 0+ alertas. Registrá las clases en config/alert_providers.php.
 */
interface AlertProvider
{
    public function key(): string;

    /**
     * @return array<int,array{severity:string,title:string,detail:string,url:string,icon:string}>
     *         severity: danger | warning | info
     */
    public function collect(): array;
}
