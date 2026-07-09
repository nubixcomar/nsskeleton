<?php

declare(strict_types=1);

namespace Core;

/**
 * Cabeceras de seguridad HTTP aplicadas a todas las respuestas.
 *
 * La CSP se arma con orígenes BASE ('self' + CDN según assets) y se puede EXTENDER
 * sin editar este archivo, vía `config/security.php` (overrideable), variables de
 * entorno `CSP_*_EXTRA`, o presets de integración (mercadopago/stripe/recaptcha…).
 * Ver `system/config/security.php`.
 *
 * `Core\App` aplica estas cabeceras haciendo MERGE: si un controller ya seteó una
 * cabecera (p. ej. `Response::withCsp()`), esa gana → override por-respuesta real.
 */
final class Security
{
    /** Directivas de CSP extensibles y su etiqueta CSP. */
    private const DIRECTIVES = [
        'script_src'  => 'script-src',
        'style_src'   => 'style-src',
        'connect_src' => 'connect-src',
        'frame_src'   => 'frame-src',
        'img_src'     => 'img-src',
        'font_src'    => 'font-src',
        'worker_src'  => 'worker-src',
    ];

    /** @return array<string,string> */
    public static function headers(bool $https = false): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => self::csp(),
        ];

        if ($https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    /** Arma la cabecera Content-Security-Policy completa. */
    public static function csp(): string
    {
        // Orígenes base por directiva.
        if (Assets::useLocal()) {
            $style  = ["'self'", "'unsafe-inline'"];
            $script = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];
        } else {
            $style  = ["'self'", "'unsafe-inline'", 'https://cdn.tailwindcss.com'];
            $script = ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://cdn.tailwindcss.com', 'https://cdn.jsdelivr.net'];
        }

        $base = [
            'script_src'  => $script,
            'style_src'   => $style,
            'img_src'     => ["'self'", 'data:'],
            'font_src'    => ["'self'", 'data:'],
            'connect_src' => ["'self'"],
            'frame_src'   => [],
            'worker_src'  => [],
        ];

        // Suma extras: config + env + presets.
        $extra = self::extraOrigins();
        foreach (self::DIRECTIVES as $key => $_label) {
            if (!empty($extra[$key])) {
                $base[$key] = array_merge($base[$key] ?? [], $extra[$key]);
            }
        }

        $parts = ["default-src 'self'"];
        foreach (self::DIRECTIVES as $key => $label) {
            $origins = self::dedupe($base[$key] ?? []);
            if ($origins !== []) {
                $parts[] = $label . ' ' . implode(' ', $origins);
            }
        }

        $parts[] = "frame-ancestors 'self'";
        $parts[] = "base-uri 'self'";
        $parts[] = "form-action 'self'";

        return implode('; ', $parts);
    }

    /**
     * Orígenes extra por directiva, combinando config (`security.csp` + presets)
     * y variables de entorno `CSP_*_EXTRA`.
     *
     * @return array<string,list<string>>
     */
    private static function extraOrigins(): array
    {
        $out = [];
        foreach (array_keys(self::DIRECTIVES) as $key) {
            $out[$key] = [];
        }

        $cfg = Config::load('security');

        // 1) Orígenes explícitos en config/security.php (csp.<directiva>).
        $csp = is_array($cfg['csp'] ?? null) ? $cfg['csp'] : [];
        foreach ($out as $key => $_) {
            if (!empty($csp[$key]) && is_array($csp[$key])) {
                $out[$key] = array_merge($out[$key], array_map('strval', $csp[$key]));
            }
        }

        // 2) Presets activados (config + env CSP_PRESETS, separados por coma/espacio).
        $presets = is_array($cfg['presets'] ?? null) ? $cfg['presets'] : [];
        $envPresets = Env::get('CSP_PRESETS', '');
        if (is_string($envPresets) && trim($envPresets) !== '') {
            $presets = array_merge($presets, preg_split('/[\s,]+/', trim($envPresets)) ?: []);
        }
        $defs    = is_array($cfg['preset_definitions'] ?? null) ? $cfg['preset_definitions'] : [];
        foreach ($presets as $name) {
            $def = $defs[$name] ?? null;
            if (!is_array($def)) {
                continue;
            }
            foreach ($out as $key => $_) {
                if (!empty($def[$key]) && is_array($def[$key])) {
                    $out[$key] = array_merge($out[$key], array_map('strval', $def[$key]));
                }
            }
        }

        // 3) Variables de entorno CSP_<DIRECTIVA>_EXTRA (espacios o comas).
        foreach (array_keys(self::DIRECTIVES) as $key) {
            $envKey = 'CSP_' . strtoupper($key) . '_EXTRA';
            $val = Env::get($envKey, '');
            if (is_string($val) && trim($val) !== '') {
                $tokens = preg_split('/[\s,]+/', trim($val)) ?: [];
                $out[$key] = array_merge($out[$key], array_filter($tokens));
            }
        }

        return $out;
    }

    /**
     * Quita duplicados preservando orden.
     * @param list<string> $origins
     * @return list<string>
     */
    private static function dedupe(array $origins): array
    {
        return array_values(array_unique($origins));
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
