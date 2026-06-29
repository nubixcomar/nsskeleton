<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Http;
use App\Services\Webhook;
use Core\Database;

/**
 * Entrega un webhook: POST firmado (HMAC) a la URL. Lanza excepción si no es 2xx
 * (para que la cola lo reintente).
 */
final class WebhookDeliverJob implements Job
{
    public function handle(array $payload): void
    {
        $url = (string) ($payload['url'] ?? '');
        $event = (string) ($payload['event'] ?? '');
        if ($url === '') {
            throw new \RuntimeException('Webhook sin URL.');
        }

        $hook = Database::selectOne('SELECT secret FROM webhooks WHERE id = ? LIMIT 1', [(int) ($payload['webhook_id'] ?? 0)]);
        $secret = (string) ($hook['secret'] ?? '');

        $body = json_encode([
            'event'   => $event,
            'data'    => $payload['data'] ?? [],
            'sent_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE) ?: '{}';

        $res = Http::postRaw($url, $body, [
            'Content-Type: application/json',
            'X-NS-Event: ' . $event,
            'X-NS-Signature: ' . Webhook::sign($secret, $body),
        ]);

        if (!$res['ok']) {
            throw new \RuntimeException("Webhook {$url} respondió " . ($res['status'] ?: 'sin conexión') . '.');
        }
    }
}
