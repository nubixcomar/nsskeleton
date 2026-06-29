<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Contrato de un job de la cola. Debe lanzar excepción si falla (para reintentar).
 */
interface Job
{
    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void;
}
