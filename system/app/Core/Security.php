<?php

declare(strict_types=1);

namespace Core;

/**
 * Cabeceras de seguridad HTTP aplicadas a todas las respuestas.
 * La CSP permite los CDN actuales (Tailwind/Alpine/Chart.js); se endurece en A5
 * cuando los assets pasen a ser locales.
 */
final class Security
{
    /** @return array<string,string> */
    public static function headers(bool $https = false): array
    {
        // En modo local no se permiten orígenes externos; en CDN se habilitan.
        if (Assets::useLocal()) {
            $style = "style-src 'self' 'unsafe-inline'";
            $script = "script-src 'self' 'unsafe-inline' 'unsafe-eval'";
        } else {
            $style = "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com";
            $script = "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net";
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "img-src 'self' data:",
            $style,
            $script,
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => $csp,
        ];

        if ($https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }
        return ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}
