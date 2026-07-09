<?php

declare(strict_types=1);

/**
 * Configuración de empaquetado para deploy. Overrideable en `config/overrides/deploy.php`.
 *
 * Deriva del hallazgo 1.5 de la auditoría de nsCheckout: el deploy subía TODO el árbol
 * (docs, tests, installer, agentic, código fuente) al server público → superficie de
 * exposición innecesaria. Con `package = 'production'` solo se sube el runtime necesario.
 */
return [
    // 'full'       → todo el árbol menos secretos/runtime/deps (default histórico, retrocompat).
    // 'production' → SOLO runtime (allowlist): lo mínimo para correr en el server público.
    'package' => 'full',

    // Allowlist de producción: prefijos que SÍ se suben en modo 'production'.
    // system/ (app + public + config + consola) y app/ (tu código). Nada de docs/tests/etc.
    'production_allow' => [
        'system/',
        'app/',
    ],

    // Denylist (siempre se excluye, en cualquier modo): deps, control de versiones, binarios.
    // A los secretos (.env), runtime de storage/ y .gitkeep runtime los maneja el Deployer.
    'exclude' => [
        '/.git/',
        '/vendor/',
        '/node_modules/',
        '/tools/bin/',
        '/landing/downloads/',
    ],
];
