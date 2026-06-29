<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Job de ejemplo: escribe una línea en logs/jobs-demo.log.
 */
final class LogJob implements Job
{
    public function handle(array $payload): void
    {
        $message = (string) ($payload['message'] ?? 'sin mensaje');
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents(BASE_PATH . '/../logs/jobs-demo.log', $line, FILE_APPEND);
    }
}
