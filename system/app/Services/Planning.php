<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Planning — helper determinístico de la metodología Roadmap → Brief → Plan.
 *
 * Lee los artefactos de `planning/{roadmap,briefs,plans}/` (front-matter de cada
 * `RM-NNN-*.md`, `BR-NNN-*.md`, `PL-NNN-*.md`), valida su coherencia (lint),
 * regenera los `INDEX.md` (reindex) y calcula el próximo código libre.
 *
 * Es el soporte mecánico de los comandos /roadmap, /brief y /plan: los agentes deciden,
 * esta clase evita que los índices se desalineen en loops largos.
 * Metodología: agentic/methodology/roadmap-brief-plan.md. Sin dependencias ni DB.
 */
final class Planning
{
    /** @var array<string,array{prefix:string,dir:string,label:string}> */
    public const TYPES = [
        'rm' => ['prefix' => 'RM', 'dir' => 'roadmap', 'label' => 'Roadmap'],
        'br' => ['prefix' => 'BR', 'dir' => 'briefs',  'label' => 'Briefs'],
        'pl' => ['prefix' => 'PL', 'dir' => 'plans',   'label' => 'Planes'],
    ];

    /** Estados canónicos por tipo (emoji + etiqueta). */
    public const STATUSES = [
        'rm' => ['💡 Idea', '📋 Planificado', '🟡 En curso', '✅ Entregado', '⏸️ Pausado', '⛔ Bloqueado', '❌ Descartado'],
        'br' => ['✏️ Borrador', '❓ En indagación', '✅ Listo', '🧭 Planificado', '⏸️ Pausado', '❌ Descartado'],
        'pl' => ['✏️ Borrador', '🔍 En revisión', '👍 Aprobado', '⚙️ En ejecución', '✅ Completado', '⛔ Bloqueado', '🔁 Reemplazado', '❌ Descartado'],
    ];

    public const ORIGINS = ['roadmap', 'brief', 'urgencia', 'imprevisto', 'mejora-técnica'];

    private const INDEX_HEADERS = [
        'rm' => ['Código', 'Nombre', 'Estado', 'Prior.', 'Inicio tent.', 'Entrega tent.', 'Briefs', 'Planes'],
        'br' => ['Código', 'Nombre', 'Estado', 'RM', 'Planes', 'Preguntas bloqueantes', 'Actualizado'],
        'pl' => ['Código', 'Nombre', 'Estado', 'BR', 'RM', 'Origen', 'Fases (hechas/total)', 'Actualizado'],
    ];

    private string $root;
    private string $dir;

    /** @param string $projectRoot raíz del proyecto (donde vive `planning/`) */
    public function __construct(string $projectRoot, string $planningDir = 'planning')
    {
        $this->root = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $this->dir  = $this->root . '/' . trim($planningDir, '/');
    }

    public function path(string $rel = ''): string
    {
        return $rel === '' ? $this->dir : $this->dir . '/' . ltrim($rel, '/');
    }

    public function exists(): bool
    {
        return is_dir($this->dir);
    }

    /** Carpeta del scaffold: override del proyecto primero, luego core. */
    public function templatesDir(): string
    {
        $app = $this->root . '/app-agentic/templates/planning';
        return is_dir($app) ? $app : $this->root . '/agentic/templates/planning';
    }

    /**
     * Crea `planning/` copiando README + los tres INDEX.md del scaffold. Nunca pisa.
     *
     * @return array{created:list<string>,skipped:list<string>}
     */
    public function init(): array
    {
        $src = $this->templatesDir();
        $out = ['created' => [], 'skipped' => []];
        $files = ['README.md'];
        foreach (self::TYPES as $t) {
            $files[] = $t['dir'] . '/INDEX.md';
        }
        foreach ($files as $rel) {
            $dst = $this->path($rel);
            if (is_file($dst)) {
                $out['skipped'][] = $rel;
                continue;
            }
            if (!is_file("{$src}/{$rel}")) {
                throw new \RuntimeException("Falta el scaffold {$src}/{$rel}");
            }
            if (!is_dir(dirname($dst))) {
                mkdir(dirname($dst), 0775, true);
            }
            copy("{$src}/{$rel}", $dst);
            $out['created'][] = $rel;
        }
        return $out;
    }

    // ------------------------------------------------------------------ parsing

    /**
     * Parser mínimo de front-matter YAML (`clave: valor`, listas `[a, b]`, comentarios `  # …`).
     * `—`, `-`, `~`, `null` y vacío se normalizan a null.
     *
     * @return array<string,mixed>|null null si no hay front-matter
     */
    public static function parseFrontMatter(string $content): ?array
    {
        $content = ltrim(str_replace("\r\n", "\n", $content), "\xEF\xBB\xBF");
        if (!preg_match('/^---\n(.*?)\n---(\n|$)/s', $content, $m)) {
            return null;
        }
        $data = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (!preg_match('/^([A-Za-z_][\w-]*)\s*:\s*(.*)$/u', $line, $kv)) {
                continue;
            }
            $val = preg_replace('/\s+#.*$/u', '', $kv[2]) ?? '';
            $data[$kv[1]] = self::scalarOrList(trim($val));
        }
        return $data;
    }

    /** @return string|list<string>|null */
    private static function scalarOrList(string $v): string|array|null
    {
        if (str_starts_with($v, '[') && str_ends_with($v, ']')) {
            $items = array_map(static fn ($s) => trim(trim($s), "\"'"), explode(',', substr($v, 1, -1)));
            return array_values(array_filter($items, static fn ($s) => self::scalarOrList($s) !== null));
        }
        $v = trim($v, "\"'");
        return in_array($v, ['', '—', '-', '~', 'null'], true) ? null : $v;
    }

    /** Devuelve el estado canónico (con emoji) o null si no es válido para el tipo. */
    public static function normalizeStatus(string $type, ?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $key = self::statusKey($raw);
        foreach (self::STATUSES[$type] ?? [] as $canon) {
            if (self::statusKey($canon) === $key) {
                return $canon;
            }
        }
        return null;
    }

    /** Etiqueta sin emoji, minúsculas: "🟡 En curso" → "en curso". */
    public static function statusKey(string $s): string
    {
        $label = preg_replace('/^[^\p{L}]+/u', '', trim($s)) ?? '';
        return mb_strtolower(trim($label));
    }

    /**
     * Fases de un plan: headings `### F<n> — … · estado: <estado>`.
     *
     * @return array{total:int,done:int,pending:int,blocked:int,items:list<array{n:int,state:string}>}
     */
    public static function parsePhases(string $content): array
    {
        $out = ['total' => 0, 'done' => 0, 'pending' => 0, 'blocked' => 0, 'items' => []];
        preg_match_all('/^###\s+F(\d+)\b[^\n]*?estado:\s*([^\n]+)$/mu', $content, $mm, PREG_SET_ORDER);
        foreach ($mm as $m) {
            $s = mb_strtolower($m[2]);
            $state = match (true) {
                str_contains($s, '✅') || str_contains($s, 'hecha') => 'done',
                str_contains($s, '⏭') || str_contains($s, 'omitida') => 'skipped',
                str_contains($s, '⛔') || str_contains($s, 'bloqueada') => 'blocked',
                str_contains($s, '⚙') || str_contains($s, 'en curso') => 'running',
                default => 'pending',
            };
            $out['total']++;
            $out['items'][] = ['n' => (int) $m[1], 'state' => $state];
            if ($state === 'done' || $state === 'skipped') {
                $out['done']++;
            } elseif ($state === 'blocked') {
                $out['blocked']++;
            } else {
                $out['pending']++;
            }
        }
        return $out;
    }

    /** Preguntas bloqueantes sin respuesta en la tabla "Preguntas abiertas" de un brief. */
    public static function countBlockingQuestions(string $content): int
    {
        $content = str_replace("\r\n", "\n", $content);
        if (!preg_match('/^##[^\n]*Preguntas abiertas[^\n]*\n(.*?)(?=^## |\z)/msu', $content, $sec)) {
            return 0;
        }
        $n = 0;
        foreach (explode("\n", $sec[1]) as $line) {
            if (!str_starts_with(trim($line), '|')) {
                continue;
            }
            $cells = array_map('trim', explode('|', trim(trim($line), '|')));
            if (count($cells) < 4 || preg_match('/^-+$|^#$/', $cells[0] ?? '') || ($cells[1] ?? '') === '') {
                continue;
            }
            $blocking = (bool) preg_match('/^s[ií]$/iu', $cells[2]);
            if ($blocking && $cells[3] === '') {
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ carga

    /**
     * Carga todos los artefactos.
     *
     * @return array{items:array<string,array<string,array<string,mixed>>>,problems:list<array{level:string,code:string,msg:string}>}
     */
    public function load(): array
    {
        $items = ['rm' => [], 'br' => [], 'pl' => []];
        $problems = [];
        foreach (self::TYPES as $type => $t) {
            $dir = $this->path($t['dir']);
            if (!is_dir($dir)) {
                continue;
            }
            $files = glob($dir . '/*.md') ?: [];
            sort($files);
            foreach ($files as $file) {
                $base = basename($file);
                if (in_array($base, ['INDEX.md', 'README.md'], true) || str_contains($base, '.template.')) {
                    continue;
                }
                $rel = $t['dir'] . '/' . $base;
                if (!preg_match('/^(' . $t['prefix'] . '-\d{3})(-[\p{L}\d-]+)?\.md$/u', $base, $fm)) {
                    $problems[] = ['level' => 'error', 'code' => $rel, 'msg' => "nombre de archivo inválido (esperado {$t['prefix']}-NNN-<slug>.md)"];
                    continue;
                }
                $content = (string) file_get_contents($file);
                $meta = self::parseFrontMatter($content);
                if ($meta === null) {
                    $problems[] = ['level' => 'error', 'code' => $fm[1], 'msg' => "{$rel}: sin front-matter"];
                    continue;
                }
                $code = (string) ($meta['code'] ?? '');
                if ($code !== $fm[1]) {
                    $problems[] = ['level' => 'error', 'code' => $fm[1], 'msg' => "{$rel}: code del front-matter ('{$code}') no coincide con el archivo"];
                    $code = $fm[1];
                }
                if (isset($items[$type][$code])) {
                    $problems[] = ['level' => 'error', 'code' => $code, 'msg' => "código duplicado: {$items[$type][$code]['file']} y {$rel}"];
                    continue;
                }
                $item = [
                    'type'   => $type,
                    'code'   => $code,
                    'file'   => $rel,
                    'title'  => (string) ($meta['title'] ?? ''),
                    'rawStatus' => is_string($meta['status'] ?? null) ? $meta['status'] : null,
                    'status' => self::normalizeStatus($type, is_string($meta['status'] ?? null) ? $meta['status'] : null),
                    'meta'   => $meta,
                ];
                if ($type === 'pl') {
                    $item['phases'] = self::parsePhases($content);
                }
                if ($type === 'br') {
                    $item['blocking'] = self::countBlockingQuestions($content);
                }
                $items[$type][$code] = $item;
            }
        }
        foreach ($items as &$byType) {
            ksort($byType);
        }
        unset($byType);
        return ['items' => $items, 'problems' => $problems];
    }

    /** @return list<string> */
    private static function listOf(mixed $v): array
    {
        if ($v === null) {
            return [];
        }
        return array_values(array_filter(is_array($v) ? array_map('strval', $v) : [(string) $v]));
    }

    private static function one(mixed $v): ?string
    {
        if (is_array($v)) {
            return isset($v[0]) ? (string) $v[0] : null;
        }
        return $v === null ? null : (string) $v;
    }

    /**
     * Vínculos derivados (unión de lo declarado en cada lado).
     *
     * @param array<string,array<string,array<string,mixed>>> $items
     * @return array{rmBriefs:array<string,list<string>>,rmPlans:array<string,list<string>>,brPlans:array<string,list<string>>}
     */
    private function links(array $items): array
    {
        $rmBriefs = $rmPlans = $brPlans = [];
        foreach ($items['rm'] as $code => $rm) {
            $rmBriefs[$code] = self::listOf($rm['meta']['briefs'] ?? null);
            $rmPlans[$code]  = self::listOf($rm['meta']['plans'] ?? null);
        }
        foreach ($items['br'] as $code => $br) {
            $brPlans[$code] = self::listOf($br['meta']['plans'] ?? null);
            if (($rm = self::one($br['meta']['rm'] ?? null)) !== null) {
                $rmBriefs[$rm][] = $code;
            }
        }
        foreach ($items['pl'] as $code => $pl) {
            if (($rm = self::one($pl['meta']['rm'] ?? null)) !== null) {
                $rmPlans[$rm][] = $code;
            }
            if (($br = self::one($pl['meta']['brief'] ?? null)) !== null) {
                $brPlans[$br][] = $code;
            }
        }
        $norm = static function (array $m): array {
            foreach ($m as $k => $v) {
                $v = array_values(array_unique($v));
                sort($v);
                $m[$k] = $v;
            }
            return $m;
        };
        return ['rmBriefs' => $norm($rmBriefs), 'rmPlans' => $norm($rmPlans), 'brPlans' => $norm($brPlans)];
    }

    // ------------------------------------------------------------------ next

    /** Próximo código libre: máximo entre archivos e índice + 1. */
    public function nextCode(string $type): string
    {
        $t = self::TYPES[$type] ?? throw new \InvalidArgumentException("Tipo inválido: {$type}");
        $max = 0;
        $sources = glob($this->path($t['dir']) . '/' . $t['prefix'] . '-*.md') ?: [];
        foreach ($sources as $f) {
            if (preg_match('/' . $t['prefix'] . '-(\d{3})/', basename($f), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        $index = $this->path($t['dir'] . '/INDEX.md');
        if (is_file($index)) {
            foreach ($this->indexRows((string) file_get_contents($index)) as $code => $_) {
                if (preg_match('/-(\d{3})$/', $code, $m) && str_starts_with($code, $t['prefix'] . '-')) {
                    $max = max($max, (int) $m[1]);
                }
            }
        }
        return sprintf('%s-%03d', $t['prefix'], $max + 1);
    }

    /**
     * Filas del índice: código => estado (canónico si se pudo).
     *
     * @return array<string,string>
     */
    private function indexRows(string $content): array
    {
        $rows = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $content)) as $line) {
            if (!preg_match('/^\|\s*\[?((?:RM|BR|PL)-\d{3})\]?/u', $line, $m)) {
                continue;
            }
            $cells = array_map('trim', explode('|', trim(trim($line), '|')));
            $rows[$m[1]] = $cells[2] ?? '';
        }
        return $rows;
    }

    // ------------------------------------------------------------------ lint

    /**
     * Valida coherencia. errors = estructura rota; warnings = desalineado/propagación;
     * suggestions = próxima fase sugerida (lo que muestra /roadmap review).
     *
     * @return array{errors:list<string>,warnings:list<string>,suggestions:list<string>,counts:array<string,int>}
     */
    public function lint(?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $loaded = $this->load();
        $items = $loaded['items'];
        $errors = $warnings = $suggestions = [];
        foreach ($loaded['problems'] as $p) {
            $errors[] = "{$p['code']}: {$p['msg']}";
        }
        $exists = static fn (string $type, string $code): bool => isset($items[$type][$code]);
        $links = $this->links($items);
        $closed = ['✅ Entregado', '❌ Descartado', '✅ Completado', '🔁 Reemplazado'];

        foreach ($items as $type => $byType) {
            foreach ($byType as $code => $it) {
                if ($it['status'] === null) {
                    $errors[] = "{$code}: estado inválido '" . ($it['rawStatus'] ?? '') . "' (válidos: " . implode(', ', self::STATUSES[$type]) . ')';
                }
                if ($it['title'] === '' || str_contains($it['title'], '<')) {
                    $warnings[] = "{$code}: title vacío o con placeholder de plantilla";
                }
                foreach (['start_tentative', 'due_tentative', 'created', 'updated'] as $k) {
                    $v = self::one($it['meta'][$k] ?? null);
                    if ($v !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                        $warnings[] = "{$code}: {$k} '{$v}' no es YYYY-MM-DD";
                    }
                }
            }
        }

        // Vínculos: existencia y tipo.
        $check = function (string $from, string $field, ?string $target, string $type) use ($exists, &$errors): void {
            if ($target === null) {
                return;
            }
            $prefix = self::TYPES[$type]['prefix'] . '-';
            if (!str_starts_with($target, $prefix)) {
                $errors[] = "{$from}: {$field} '{$target}' debería ser un {$prefix}NNN";
            } elseif (!$exists($type, $target)) {
                $errors[] = "{$from}: {$field} apunta a {$target}, que no existe";
            }
        };
        foreach ($items['rm'] as $code => $rm) {
            foreach (self::listOf($rm['meta']['briefs'] ?? null) as $b) {
                $check($code, 'briefs', $b, 'br');
            }
            foreach (self::listOf($rm['meta']['plans'] ?? null) as $p) {
                $check($code, 'plans', $p, 'pl');
            }
        }
        foreach ($items['br'] as $code => $br) {
            $check($code, 'rm', self::one($br['meta']['rm'] ?? null), 'rm');
            foreach (self::listOf($br['meta']['plans'] ?? null) as $p) {
                $check($code, 'plans', $p, 'pl');
            }
        }
        foreach ($items['pl'] as $code => $pl) {
            $check($code, 'rm', self::one($pl['meta']['rm'] ?? null), 'rm');
            $check($code, 'brief', self::one($pl['meta']['brief'] ?? null), 'br');
            $check($code, 'replaces', self::one($pl['meta']['replaces'] ?? null), 'pl');
            $origin = self::one($pl['meta']['origin'] ?? null);
            if ($origin !== null && !in_array($origin, self::ORIGINS, true)) {
                $warnings[] = "{$code}: origin '{$origin}' no reconocido (" . implode(', ', self::ORIGINS) . ')';
            }
        }

        // Vínculos en un solo sentido (el índice usa la unión, pero el archivo queda incompleto).
        foreach ($items['br'] as $code => $br) {
            $rm = self::one($br['meta']['rm'] ?? null);
            if ($rm !== null && $exists('rm', $rm) && !in_array($code, self::listOf($items['rm'][$rm]['meta']['briefs'] ?? null), true)) {
                $warnings[] = "{$rm}: no lista a {$code} en 'briefs' (el brief sí apunta al RM)";
            }
        }
        foreach ($items['pl'] as $code => $pl) {
            $rm = self::one($pl['meta']['rm'] ?? null);
            if ($rm !== null && $exists('rm', $rm) && !in_array($code, self::listOf($items['rm'][$rm]['meta']['plans'] ?? null), true)) {
                $warnings[] = "{$rm}: no lista a {$code} en 'plans' (el plan sí apunta al RM)";
            }
            $br = self::one($pl['meta']['brief'] ?? null);
            if ($br !== null && $exists('br', $br) && !in_array($code, self::listOf($items['br'][$br]['meta']['plans'] ?? null), true)) {
                $warnings[] = "{$br}: no lista a {$code} en 'plans' (el plan sí apunta al brief)";
            }
        }

        // Fechas del roadmap.
        foreach ($items['rm'] as $code => $rm) {
            $start = self::one($rm['meta']['start_tentative'] ?? null);
            $due = self::one($rm['meta']['due_tentative'] ?? null);
            if ($start && $due && $start > $due) {
                $warnings[] = "{$code}: inicio tentativo ({$start}) posterior a la entrega ({$due})";
            }
            if ($due && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) && $due < $today && !in_array($rm['status'], ['✅ Entregado', '❌ Descartado'], true)) {
                $warnings[] = "{$code}: entrega tentativa vencida ({$due}) — re-planificar y anotar en el historial";
            }
        }

        // Planes: fases y estados.
        foreach ($items['pl'] as $code => $pl) {
            $ph = $pl['phases'];
            $active = in_array($pl['status'], ['👍 Aprobado', '⚙️ En ejecución', '✅ Completado'], true);
            if ($active && $ph['total'] === 0) {
                $warnings[] = "{$code}: plan {$pl['status']} sin fases con formato '### F<n> — … · estado: …'";
            }
            if ($pl['status'] === '✅ Completado' && $ph['done'] < $ph['total']) {
                $warnings[] = "{$code}: Completado con fases sin terminar ({$ph['done']}/{$ph['total']})";
            }
            $brief = self::one($pl['meta']['brief'] ?? null);
            $size = strtoupper((string) self::one($pl['meta']['size'] ?? null));
            $exception = self::one($pl['meta']['exception'] ?? null);
            if ($brief === null && in_array($size, ['L', 'XL'], true) && $exception === null) {
                $warnings[] = "{$code}: plan {$size} sin brief y sin 'exception' que lo justifique";
            }
        }

        // Propagación de estados.
        foreach ($items['pl'] as $code => $pl) {
            $rm = self::one($pl['meta']['rm'] ?? null);
            if ($pl['status'] === '⚙️ En ejecución' && $rm && $exists('rm', $rm)
                && in_array($items['rm'][$rm]['status'], ['💡 Idea', '📋 Planificado'], true)) {
                $warnings[] = "{$rm}: debería estar '🟡 En curso' ({$code} está en ejecución)";
            }
            $br = self::one($pl['meta']['brief'] ?? null);
            if (in_array($pl['status'], ['👍 Aprobado', '⚙️ En ejecución', '✅ Completado'], true) && $br && $exists('br', $br)
                && in_array($items['br'][$br]['status'], ['✏️ Borrador', '❓ En indagación', '✅ Listo'], true)) {
                $warnings[] = "{$br}: debería estar '🧭 Planificado' ({$code} está {$pl['status']})";
            }
        }
        foreach ($items['br'] as $code => $br) {
            if ($br['status'] === '✅ Listo' && $br['blocking'] > 0) {
                $warnings[] = "{$code}: marcado Listo con {$br['blocking']} pregunta(s) bloqueante(s) sin responder";
            }
        }

        // Sugerencias de próxima fase.
        foreach ($items['rm'] as $code => $rm) {
            $plans = $links['rmPlans'][$code] ?? [];
            if (in_array($rm['status'], ['📋 Planificado', '🟡 En curso'], true) && !($links['rmBriefs'][$code] ?? []) && !$plans) {
                $suggestions[] = "{$code} sin brief ni plan → /brief new \"{$rm['title']}\" {$code}";
            }
            if ($plans && $rm['status'] !== '✅ Entregado' && $rm['status'] !== '❌ Descartado') {
                $allDone = true;
                foreach ($plans as $p) {
                    if (!$exists('pl', $p) || !in_array($items['pl'][$p]['status'], ['✅ Completado', '🔁 Reemplazado', '❌ Descartado'], true)) {
                        $allDone = false;
                    }
                }
                if ($allDone) {
                    $suggestions[] = "{$code}: todos sus planes están cerrados → /roadmap update {$code} --estado entregado";
                }
            }
        }
        foreach ($items['br'] as $code => $br) {
            if ($br['status'] === '✅ Listo' && !($links['brPlans'][$code] ?? [])) {
                $suggestions[] = "{$code} listo sin plan → /plan new {$code}" . (($rm = self::one($br['meta']['rm'] ?? null)) ? " {$rm}" : '');
            }
            if ($br['status'] === '❓ En indagación' && $br['blocking'] > 0) {
                $suggestions[] = "{$code}: {$br['blocking']} pregunta(s) bloqueante(s) → /brief refine {$code}";
            }
        }
        foreach ($items['pl'] as $code => $pl) {
            if (self::one($pl['meta']['rm'] ?? null) === null && !in_array($pl['status'], $closed, true)) {
                $suggestions[] = "{$code} sin RM → vincularlo a una iniciativa o crear una (/roadmap new)";
            }
            if ($pl['status'] === '👍 Aprobado' || ($pl['status'] === '⚙️ En ejecución' && $pl['phases']['pending'] > 0)) {
                $suggestions[] = "{$code}: fases pendientes → /plan run {$code}";
            }
            if ($pl['status'] === '⚙️ En ejecución' && $pl['phases']['total'] > 0 && $pl['phases']['done'] === $pl['phases']['total']) {
                $suggestions[] = "{$code}: todas las fases hechas → /plan close {$code}";
            }
        }

        // Índices vs archivos.
        foreach (self::TYPES as $type => $t) {
            $index = $this->path($t['dir'] . '/INDEX.md');
            if (!is_file($index)) {
                if ($this->exists()) {
                    $warnings[] = "falta {$t['dir']}/INDEX.md → planning reindex";
                }
                continue;
            }
            $rows = $this->indexRows((string) file_get_contents($index));
            foreach ($rows as $code => $st) {
                if (!$exists($type, $code)) {
                    $errors[] = "{$t['dir']}/INDEX.md: fila {$code} sin archivo";
                }
            }
            foreach ($items[$type] as $code => $it) {
                if (!isset($rows[$code])) {
                    $warnings[] = "{$t['dir']}/INDEX.md: falta {$code} → planning reindex";
                } elseif ($it['status'] !== null && self::statusKey($rows[$code]) !== self::statusKey($it['status'])) {
                    $warnings[] = "{$t['dir']}/INDEX.md: {$code} figura '{$rows[$code]}' pero el archivo dice '{$it['status']}' → planning reindex";
                }
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'suggestions' => array_values(array_unique($suggestions)),
            'counts' => ['rm' => count($items['rm']), 'br' => count($items['br']), 'pl' => count($items['pl'])],
        ];
    }

    // ------------------------------------------------------------------ reindex

    /**
     * Regenera la tabla de cada INDEX.md desde el front-matter (conserva el texto de cabecera)
     * y actualiza "Próximo código libre". Determinístico.
     *
     * @return list<string> índices escritos (relativos a planning/)
     */
    public function reindex(): array
    {
        $items = $this->load()['items'];
        $links = $this->links($items);
        $written = [];
        foreach (self::TYPES as $type => $t) {
            if (!is_dir($this->path($t['dir'])) && $items[$type] === []) {
                continue;
            }
            $rel = $t['dir'] . '/INDEX.md';
            $file = $this->path($rel);
            $current = is_file($file) ? (string) file_get_contents($file) : '';
            if ($current === '') {
                $tpl = $this->templatesDir() . '/' . $rel;
                $current = is_file($tpl) ? (string) file_get_contents($tpl) : "# {$t['label']} — Índice ({$t['prefix']})\n\n> Próximo código libre: **{$t['prefix']}-001**\n";
            }
            $table = $this->renderTable($type, $items, $links);
            $next = $this->nextCode($type);
            $new = $this->replaceTable($current, $table);
            $new = preg_replace('/(Próximo código libre:\s*\*\*)[A-Z]{2}-\d{3}(\*\*)/u', '${1}' . $next . '${2}', $new) ?? $new;
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }
            if ($new !== $current || !is_file($file)) {
                file_put_contents($file, $new);
                $written[] = $rel;
            }
        }
        return $written;
    }

    /**
     * @param array<string,array<string,array<string,mixed>>> $items
     * @param array<string,array<string,list<string>>> $links
     */
    private function renderTable(string $type, array $items, array $links): string
    {
        $h = self::INDEX_HEADERS[$type];
        $lines = ['| ' . implode(' | ', $h) . ' |', '|' . implode('|', array_map(static fn ($c) => str_repeat('-', mb_strlen($c) + 2), $h)) . '|'];
        $cell = static fn (?string $v): string => $v === null || $v === '' ? '—' : str_replace('|', '\|', $v);
        $codes = static fn (array $l): string => $l ? implode(', ', $l) : '—';
        foreach ($items[$type] as $code => $it) {
            $m = $it['meta'];
            $link = '[' . $code . '](' . basename($it['file']) . ')';
            $status = $it['status'] ?? ($it['rawStatus'] ?? '');
            $row = match ($type) {
                'rm' => [$link, $cell($it['title']), $cell($status), $cell(self::one($m['priority'] ?? null)),
                    $cell(self::one($m['start_tentative'] ?? null)), $cell(self::one($m['due_tentative'] ?? null)),
                    $codes($links['rmBriefs'][$code] ?? []), $codes($links['rmPlans'][$code] ?? [])],
                'br' => [$link, $cell($it['title']), $cell($status), $cell(self::one($m['rm'] ?? null)),
                    $codes($links['brPlans'][$code] ?? []), (string) $it['blocking'], $cell(self::one($m['updated'] ?? null))],
                'pl' => [$link, $cell($it['title']), $cell($status), $cell(self::one($m['brief'] ?? null)),
                    $cell(self::one($m['rm'] ?? null)), $cell(self::one($m['origin'] ?? null)),
                    $it['phases']['done'] . '/' . $it['phases']['total'], $cell(self::one($m['updated'] ?? null))],
            };
            $lines[] = '| ' . implode(' | ', $row) . ' |';
        }
        if ($items[$type] === []) {
            $lines[] = '|' . str_repeat(' |', count($h));
        }
        return implode("\n", $lines);
    }

    /** Reemplaza la tabla que empieza en "| Código" (o la agrega al final). */
    private function replaceTable(string $content, string $table): string
    {
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);
        $start = null;
        foreach ($lines as $i => $l) {
            if (preg_match('/^\|\s*Código\s*\|/u', $l)) {
                $start = $i;
                break;
            }
        }
        if ($start === null) {
            return rtrim($content, "\n") . "\n\n" . $table . "\n";
        }
        $end = $start;
        while (isset($lines[$end + 1]) && str_starts_with(trim($lines[$end + 1]), '|')) {
            $end++;
        }
        array_splice($lines, $start, $end - $start + 1, explode("\n", $table));
        return rtrim(implode("\n", $lines), "\n") . "\n";
    }
}
