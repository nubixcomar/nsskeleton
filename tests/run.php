<?php

declare(strict_types=1);

/**
 * Runner de tests de nsSkeleton.
 *   php tests/run.php
 * Descubre y ejecuta tests/unit/*.php y tests/feature/*.php
 * Código de salida 1 si algún test falla (útil para CI/cron).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

require __DIR__ . '/bootstrap.php';

foreach (['unit', 'feature'] as $suite) {
    foreach (glob(__DIR__ . '/' . $suite . '/*.php') ?: [] as $file) {
        require $file;
    }
}

$s = $GLOBALS['__test_stats'];

echo "\n" . str_repeat('-', 52) . "\n";
echo "PASS: {$s['pass']}   FAIL: {$s['fail']}   SKIP: {$s['skip']}\n";

if ($s['fail'] > 0) {
    echo "\nFallos:\n";
    foreach ($s['failures'] as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "OK — todos los tests pasaron.\n";
