<?php

declare(strict_types=1);

/**
 * Helper de la metodología Roadmap → Brief → Plan (planning/{roadmap,briefs,plans}).
 *
 *   php system/console/planning.php init                 # crea planning/ desde el scaffold (no pisa)
 *   php system/console/planning.php lint [--strict]      # valida códigos, estados, vínculos, índices (exit 1 si hay errores)
 *   php system/console/planning.php reindex              # regenera los INDEX.md desde el front-matter
 *   php system/console/planning.php sync                 # reindex + lint
 *   php system/console/planning.php next <rm|br|pl>      # próximo código libre
 *   php system/console/planning.php list [rm|br|pl]      # resumen por tipo
 *   php system/console/planning.php review               # solo sugerencias de próxima fase
 *
 * Opciones: --json (salida para agentes) · --root=<ruta> (raíz del proyecto) · --today=YYYY-MM-DD
 * --strict: los warnings también hacen exit 1.
 *
 * Lo usan /roadmap, /brief y /plan para que los índices no se desalineen (ver
 * agentic/methodology/roadmap-brief-plan.md). Sin DB.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/autoload.php';

use App\Services\Planning;

$args = array_slice($argv, 1);
$opt = static function (string $name) use ($args): ?string {
    foreach ($args as $a) {
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return null;
};
$flag = static fn (string $name): bool => in_array("--{$name}", $args, true);
$pos = array_values(array_filter($args, static fn ($a) => !str_starts_with($a, '--')));
$cmd = $pos[0] ?? 'help';
$json = $flag('json');
$root = $opt('root') ?? dirname(BASE_PATH);
$p = new Planning($root);

$out = static function (mixed $data) use ($json): void {
    if ($json) {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
};
$printLint = static function (array $r) use ($json, $out): void {
    if ($json) {
        $out($r);
        return;
    }
    echo "planning: {$r['counts']['rm']} RM · {$r['counts']['br']} BR · {$r['counts']['pl']} PL\n";
    foreach (['errors' => 'ERROR', 'warnings' => 'WARN ', 'suggestions' => 'SUGER'] as $k => $label) {
        foreach ($r[$k] as $line) {
            echo "  [{$label}] {$line}\n";
        }
    }
    echo sprintf("  → %d error(es), %d warning(s), %d sugerencia(s)\n", count($r['errors']), count($r['warnings']), count($r['suggestions']));
};
$requireDir = static function () use ($p): void {
    if (!$p->exists()) {
        fwrite(STDERR, "No existe planning/. Crealo con: php system/console/planning.php init  (o /roadmap init)\n");
        exit(2);
    }
};

switch ($cmd) {
    case 'init':
        $r = $p->init();
        $json ? $out($r) : print(
            ($r['created'] ? "Creado:\n  planning/" . implode("\n  planning/", $r['created']) . "\n" : '')
            . ($r['skipped'] ? "Ya existía (no se tocó):\n  planning/" . implode("\n  planning/", $r['skipped']) . "\n" : '')
        );
        exit(0);

    case 'lint':
        $requireDir();
        $r = $p->lint($opt('today'));
        $printLint($r);
        exit($r['errors'] || ($flag('strict') && $r['warnings']) ? 1 : 0);

    case 'reindex':
        $requireDir();
        $w = $p->reindex();
        $json ? $out(['written' => $w]) : print($w ? "Índices regenerados:\n  planning/" . implode("\n  planning/", $w) . "\n" : "Índices al día.\n");
        exit(0);

    case 'sync':
        $requireDir();
        $w = $p->reindex();
        $r = $p->lint($opt('today'));
        if ($json) {
            $out(['written' => $w] + $r);
        } else {
            echo $w ? "Índices regenerados: " . implode(', ', $w) . "\n" : "Índices al día.\n";
            $printLint($r);
        }
        exit($r['errors'] ? 1 : 0);

    case 'review':
        $requireDir();
        $r = $p->lint($opt('today'));
        if ($json) {
            $out(['suggestions' => $r['suggestions']]);
        } else {
            echo $r['suggestions'] ? "Sugerencias:\n  - " . implode("\n  - ", $r['suggestions']) . "\n" : "Sin sugerencias: cada iniciativa tiene su próxima fase en marcha.\n";
        }
        exit(0);

    case 'next':
        $type = strtolower($pos[1] ?? '');
        if (!isset(Planning::TYPES[$type])) {
            fwrite(STDERR, "Uso: planning.php next <rm|br|pl>\n");
            exit(2);
        }
        $code = $p->nextCode($type);
        $json ? $out(['type' => $type, 'next' => $code]) : print($code . "\n");
        exit(0);

    case 'list':
        $requireDir();
        $items = $p->load()['items'];
        $only = strtolower($pos[1] ?? '');
        $types = isset(Planning::TYPES[$only]) ? [$only] : array_keys(Planning::TYPES);
        if ($json) {
            $res = [];
            foreach ($types as $t) {
                foreach ($items[$t] as $it) {
                    $res[$t][] = ['code' => $it['code'], 'title' => $it['title'], 'status' => $it['status'] ?? $it['rawStatus'], 'file' => 'planning/' . $it['file']]
                        + ($t === 'pl' ? ['phases' => $it['phases']['done'] . '/' . $it['phases']['total']] : []);
                }
            }
            $out($res);
            exit(0);
        }
        foreach ($types as $t) {
            echo Planning::TYPES[$t]['label'] . ' (' . count($items[$t]) . ")\n";
            foreach ($items[$t] as $it) {
                $extra = $t === 'pl' ? "  [{$it['phases']['done']}/{$it['phases']['total']}]" : '';
                echo "  {$it['code']}  " . ($it['status'] ?? $it['rawStatus'] ?? '?') . "  {$it['title']}{$extra}\n";
            }
        }
        exit(0);

    default:
        echo "Uso: php system/console/planning.php <init|lint|reindex|sync|review|next|list> [rm|br|pl] [--json] [--strict] [--root=..] [--today=YYYY-MM-DD]\n";
        exit($cmd === 'help' ? 0 : 2);
}
