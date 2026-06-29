<?php

declare(strict_types=1);

/**
 * Providers de alertas computadas (implementan App\Alerts\AlertProvider).
 * El dashboard las recolecta y ordena por severidad (danger > warning > info).
 * Agregá los tuyos creando una clase + sumándola acá.
 */
return [
    \App\Alerts\Providers\FailedJobsAlertProvider::class,
    \App\Alerts\Providers\FailedCronAlertProvider::class,
    \App\Alerts\Providers\PendingQueueAlertProvider::class,
    \App\Alerts\Providers\OldBackupAlertProvider::class,
];
