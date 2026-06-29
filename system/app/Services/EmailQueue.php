<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Cola de envío de emails. `push()` encola; `process()` drena (lo llama el job
 * `email:queue` desde el cron). Reintenta hasta MAX_ATTEMPTS antes de marcar failed.
 */
final class EmailQueue
{
    private const MAX_ATTEMPTS = 3;

    public static function push(string $to, string $subject, string $body): int
    {
        return Database::insert(
            'INSERT INTO email_queue (to_address, subject, body, status) VALUES (?, ?, ?, ?)',
            [$to, $subject, $body, 'pending']
        );
    }

    /**
     * Procesa hasta $limit pendientes. Devuelve el resumen.
     * @return array{processed:int,sent:int,failed:int}
     */
    public static function process(int $limit = 20): array
    {
        $rows = Database::select(
            'SELECT * FROM email_queue WHERE status = ? ORDER BY id ASC LIMIT ' . max(1, $limit),
            ['pending']
        );

        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $result = Mailer::send((string) $row['to_address'], (string) $row['subject'], (string) $row['body']);
            $attempts = (int) $row['attempts'] + 1;

            if ($result['ok']) {
                Database::run(
                    'UPDATE email_queue SET status = ?, attempts = ?, sent_at = NOW(), error = NULL WHERE id = ?',
                    ['sent', $attempts, $row['id']]
                );
                $sent++;
            } else {
                $status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                Database::run(
                    'UPDATE email_queue SET status = ?, attempts = ?, error = ? WHERE id = ?',
                    [$status, $attempts, substr((string) $result['error'], 0, 500), $row['id']]
                );
                $failed++;
            }
        }

        return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed];
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 100): array
    {
        return Database::select('SELECT * FROM email_queue ORDER BY id DESC LIMIT ' . max(1, $limit));
    }

    /** @return array<string,int> conteo por estado */
    public static function counts(): array
    {
        $out = ['pending' => 0, 'sent' => 0, 'failed' => 0];
        foreach (Database::select('SELECT status, COUNT(*) AS c FROM email_queue GROUP BY status') as $r) {
            $out[(string) $r['status']] = (int) $r['c'];
        }
        return $out;
    }
}
