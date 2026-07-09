<?php

declare(strict_types=1);

/**
 * Configuración de seguridad HTTP: Content-Security-Policy y cabeceras.
 *
 * Overrideable por la app en `config/overrides/security.php` (gana la app),
 * SIN editar este archivo del core → el actualizador de core no pisa tu config.
 *
 * Tres formas de agregar orígenes externos (para embeber SDKs/iframes de terceros
 * como Mercado Pago, Stripe, mapas, reCAPTCHA…) sin tocar `Core\Security`:
 *
 *  1) Config override: agregá orígenes en `csp.<directiva>` de este archivo (vía overrides/).
 *  2) Env: `CSP_SCRIPT_SRC_EXTRA`, `CSP_STYLE_SRC_EXTRA`, `CSP_CONNECT_SRC_EXTRA`,
 *     `CSP_FRAME_SRC_EXTRA`, `CSP_IMG_SRC_EXTRA`, `CSP_FONT_SRC_EXTRA`,
 *     `CSP_WORKER_SRC_EXTRA` (lista separada por espacios o comas).
 *  3) Presets: activá integraciones conocidas por nombre en `presets`
 *     (ver `preset_definitions`) y sus orígenes se agregan automáticamente.
 *
 * Además, un controller puede overridear la CSP de UNA respuesta con
 * `$response->withCsp("…")`: `Core\App` respeta las cabeceras que el controller
 * ya seteó (merge, no overwrite).
 */
return [
    // Orígenes EXTRA por directiva (se suman a los base 'self' del core).
    'csp' => [
        'script_src'  => [],
        'style_src'   => [],
        'connect_src' => [],
        'frame_src'   => [],
        'img_src'     => [],
        'font_src'    => [],
        'worker_src'  => [],
    ],

    // Integraciones conocidas activadas (nombres de `preset_definitions`).
    // Ej: ['mercadopago', 'recaptcha']
    'presets' => [],

    // Definiciones de presets: cada uno aporta orígenes por directiva.
    // Nacen del caso real de nsCheckout (Card Payment Brick de Mercado Pago).
    'preset_definitions' => [
        'mercadopago' => [
            'script_src'  => ['https://sdk.mercadopago.com'],
            'connect_src' => ['https://api.mercadopago.com', 'https://api.mercadolibre.com'],
            'frame_src'   => [
                'https://*.mercadopago.com', 'https://*.mercadopago.com.ar',
                'https://*.mercadolibre.com', 'https://*.mercadolibre.com.ar',
            ],
            'img_src'     => ['https://*.mlstatic.com', 'https://*.mercadopago.com'],
            'worker_src'  => ['blob:'],
        ],
        'stripe' => [
            'script_src'  => ['https://js.stripe.com'],
            'connect_src' => ['https://api.stripe.com'],
            'frame_src'   => ['https://js.stripe.com', 'https://hooks.stripe.com'],
        ],
        'recaptcha' => [
            'script_src'  => ['https://www.google.com', 'https://www.gstatic.com'],
            'frame_src'   => ['https://www.google.com'],
        ],
    ],
];
