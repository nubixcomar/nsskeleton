<?php

declare(strict_types=1);

/**
 * Jobs internos (callables PHP) ejecutables por el cronmaster.
 * En una tarea, usá el comando `job:<nombre>` (ej. `job:demo:ping`).
 * Cada job devuelve un string (o imprime) que se guarda como salida.
 */
return [
    'demo:ping' => static function (): string {
        return 'pong @ ' . date('Y-m-d H:i:s');
    },

    'cache:clear' => static function (): string {
        $dir = BASE_PATH . '/storage/cache';
        $n = 0;
        foreach (glob($dir . '/*') ?: [] as $f) {
            $base = basename($f);
            if (is_file($f) && $base !== '.gitkeep' && $base !== 'cron.lock') {
                @unlink($f);
                $n++;
            }
        }
        return "cache limpiada: {$n} archivo(s)";
    },

    'email:queue' => static function (): string {
        $r = \App\Services\EmailQueue::process(20);
        return "cola emails: {$r['sent']} enviados, {$r['failed']} con error de {$r['processed']}.";
    },

    'queue:work' => static function (): string {
        $r = \App\Services\JobQueue::work(25);
        return "cola jobs: {$r['done']} ok, {$r['retried']} reintentar, {$r['failed']} fallidos de {$r['processed']}.";
    },

    'backup:run' => static function (): string {
        $db = \App\Services\Backup::createDatabaseBackup();
        $files = \App\Services\Backup::createFilesBackup();
        return 'backup db:' . ($db['ok'] ? 'ok' : 'fail') . ' files:' . ($files['ok'] ? 'ok' : 'fail');
    },
];
