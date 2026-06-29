<?php

declare(strict_types=1);

/**
 * Registro de handlers de la cola de jobs: nombre => clase (implements App\Jobs\Job).
 * Encolá con JobQueue::push('<nombre>', [...payload...]).
 */
return [
    'demo:log'        => \App\Jobs\LogJob::class,
    'webhook:deliver' => \App\Jobs\WebhookDeliverJob::class,
];
