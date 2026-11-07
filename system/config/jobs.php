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

    // Manuales remotos (config/remote_docs.php): baja los que cambiaron. Opt-in: programalo
    // como tarea `job:docs:sync` (sugerido: diario) solo si el proyecto los usa.
    'docs:sync' => static function (): string {
        $parts = [];
        $errors = 0;
        foreach (\App\Services\RemoteDocs::sync() as $key => $r) {
            if ($r['status'] === 'disabled') {
                continue;
            }
            $errors += $r['status'] === 'error' ? 1 : 0;
            $parts[] = $key . ':' . $r['status'] . ($r['version'] !== null ? '@' . $r['version'] : '')
                . ($r['error'] !== null ? ' (' . $r['error'] . ')' : '');
        }
        if ($errors > 0) {
            throw new \RuntimeException('docs:sync con errores — ' . implode(', ', $parts));
        }
        return 'manuales: ' . ($parts === [] ? 'ninguno habilitado' : implode(', ', $parts));
    },

    'backup:run' => static function (): string {
        $db = \App\Services\Backup::createDatabaseBackup();
        $files = \App\Services\Backup::createFilesBackup();
        return 'backup db:' . ($db['ok'] ? 'ok' : 'fail') . ' files:' . ($files['ok'] ? 'ok' : 'fail');
    },
];
