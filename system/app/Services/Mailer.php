<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Core\Env;

/**
 * Facade de envío de emails: arma el SmtpMailer desde la configuración persistida
 * (con fallback a .env), envía y registra el intento en `email_log`.
 */
final class Mailer
{
    public static function fromSettings(): SmtpMailer
    {
        $g = Settings::group('mail');
        $get = static fn (string $k, string $envKey, string $def = ''): string
            => (string) ($g[$k] ?? Env::get($envKey, $def));

        return new SmtpMailer(
            host: $get('host', 'MAIL_HOST', ''),
            port: (int) $get('port', 'MAIL_PORT', '587'),
            user: $get('user', 'MAIL_USER', ''),
            pass: $get('pass', 'MAIL_PASS', ''),
            encryption: $get('encryption', 'MAIL_ENCRYPTION', 'tls'),
            fromAddress: $get('from_address', 'MAIL_FROM_ADDRESS', ''),
            fromName: $get('from_name', 'MAIL_FROM_NAME', (string) Env::get('APP_NAME', 'nsSkeleton')),
        );
    }

    /**
     * Renderiza una plantilla de email (app/Views/emails/) dentro del layout de email.
     */
    public static function render(string $view, array $data = [], string $layout = 'emails/layout'): string
    {
        $content = \Core\View::partial($view, $data);
        return \Core\View::partial($layout, array_merge($data, ['content' => $content]));
    }

    /** Encola un email para envío diferido (lo drena el job `email:queue`). */
    public static function queue(string $to, string $subject, string $htmlBody): int
    {
        return EmailQueue::push($to, $subject, $htmlBody);
    }

    /** @return array{ok:bool,error:string,log:array<int,string>} */
    public static function send(string $to, string $subject, string $htmlBody): array
    {
        $result = self::fromSettings()->send($to, $subject, $htmlBody);

        Database::insert(
            'INSERT INTO email_log (to_address, subject, status, error) VALUES (?, ?, ?, ?)',
            [$to, $subject, $result['ok'] ? 'sent' : 'failed', $result['ok'] ? null : substr($result['error'], 0, 500)]
        );

        return $result;
    }
}
