<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Webhooks salientes: suscripciones evento → URL. La entrega se hace por la cola de jobs
 * (handler `webhook:deliver`) con firma HMAC-SHA256.
 */
final class Webhook
{
    /** Eventos disponibles para suscribir (clave => etiqueta). @return array<string,string> */
    public static function events(): array
    {
        return [
            'ping'          => 'Prueba (ping)',
            'admin.created' => 'Administrador creado',
        ];
    }

    public static function sign(string $secret, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    public static function subscribe(string $event, string $url): int
    {
        return Database::insert(
            'INSERT INTO webhooks (event, url, secret) VALUES (?, ?, ?)',
            [$event, $url, bin2hex(random_bytes(16))]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::select('SELECT * FROM webhooks ORDER BY id DESC');
    }

    public static function toggle(int $id): void
    {
        Database::run('UPDATE webhooks SET active = 1 - active WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM webhooks WHERE id = ?', [$id]);
    }

    /**
     * Encola la entrega del evento a cada webhook activo suscripto. Devuelve cuántos.
     * @param array<string,mixed> $payload
     */
    public static function dispatch(string $event, array $payload = []): int
    {
        $hooks = Database::select('SELECT id, url FROM webhooks WHERE event = ? AND active = 1', [$event]);
        foreach ($hooks as $h) {
            JobQueue::push('webhook:deliver', [
                'webhook_id' => (int) $h['id'],
                'url'        => (string) $h['url'],
                'event'      => $event,
                'data'       => $payload,
            ]);
        }
        return count($hooks);
    }
}
