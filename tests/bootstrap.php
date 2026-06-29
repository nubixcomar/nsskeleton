<?php

declare(strict_types=1);

/**
 * Bootstrap del runner de tests propio (sin dependencias).
 * Define helpers de aserción y un registro global de resultados.
 */

define('PROJECT_PATH', dirname(__DIR__));
define('BASE_PATH', PROJECT_PATH . '/system');

require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;

Env::load(PROJECT_PATH . '/.env');

$GLOBALS['__test_stats'] = ['pass' => 0, 'fail' => 0, 'skip' => 0, 'failures' => []];

/** Imprime el nombre de un grupo de tests. */
function group(string $name): void
{
    echo "\n# {$name}\n";
}

/**
 * Ejecuta un test. Si la closure devuelve 'skip', se cuenta como omitido.
 */
function it(string $desc, callable $fn): void
{
    try {
        $r = $fn();
        if ($r === 'skip') {
            $GLOBALS['__test_stats']['skip']++;
            echo "  SKIP  {$desc}\n";
            return;
        }
        $GLOBALS['__test_stats']['pass']++;
        echo "  PASS  {$desc}\n";
    } catch (\Throwable $e) {
        $GLOBALS['__test_stats']['fail']++;
        $GLOBALS['__test_stats']['failures'][] = $desc . ' — ' . $e->getMessage();
        echo "  FAIL  {$desc} — {$e->getMessage()}\n";
    }
}

function assertTrue(mixed $c, string $m = 'esperaba true'): void
{
    if ($c !== true) {
        throw new \RuntimeException($m);
    }
}

function assertFalse(mixed $c, string $m = 'esperaba false'): void
{
    if ($c !== false) {
        throw new \RuntimeException($m);
    }
}

function assertEquals(mixed $expected, mixed $actual, string $m = ''): void
{
    if ($expected !== $actual) {
        throw new \RuntimeException($m !== '' ? $m
            : 'esperaba ' . var_export($expected, true) . ' y obtuvo ' . var_export($actual, true));
    }
}

function assertNull(mixed $v, string $m = 'esperaba null'): void
{
    if ($v !== null) {
        throw new \RuntimeException($m);
    }
}

function assertNotNull(mixed $v, string $m = 'esperaba no-null'): void
{
    if ($v === null) {
        throw new \RuntimeException($m);
    }
}

function assertContains(string $needle, string $haystack, string $m = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new \RuntimeException($m !== '' ? $m : "esperaba contener '{$needle}'");
    }
}

function assertCount(int $n, array $arr, string $m = ''): void
{
    if (count($arr) !== $n) {
        throw new \RuntimeException($m !== '' ? $m : "esperaba {$n} elementos, hay " . count($arr));
    }
}
