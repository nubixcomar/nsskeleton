<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Crea un proyecto derivado a partir del skeleton "master" (comando /nuevo-proyecto).
 *
 * Parte testeable (sin efectos de red/DB): nombres, filtro de la copia limpia, archivos que se
 * reinician en el proyecto nuevo, marcador `nsproject.json` y detección del ROL de un árbol
 * (master del skeleton vs proyecto derivado), que también usa /release para no generar
 * paquetes de core desde un derivado.
 *
 * La orquestación (preguntar, crear DB, migrar, git) vive en `system/console/new-project.php`.
 */
final class ProjectCreator
{
    /** Marcador de proyecto derivado (archivo de la app, en la raíz; el core no lo trae). */
    public const MARKER = 'nsproject.json';

    /** Repo oficial del skeleton: un derivado nunca debe tenerlo como remoto. */
    public const SKELETON_REPO = 'nubixcomar/nsskeleton';

    /** Prefijos (relativos a la raíz, con /) que NUNCA van a un proyecto nuevo. */
    private const EXCLUDE_PREFIXES = [
        '.git/', '.claude/', '.openai/', '.cursor/', '.idea/', '.vscode/',
        'vendor/', 'node_modules/', 'secrets/',
        'landing/',                     // sitio + empaquetadores del skeleton (solo master)
        'tools/bin/',                   // binario de Tailwind (~108 MB; lo baja tools/build-css.sh)
        'system/storage/core-base/',    // base del merge del master (el derivado captura la suya)
        'planning/',                    // roadmap/briefs/planes del PROPIO skeleton (/roadmap init
                                        // lo crea vacío; agentic/templates/planning/ sí se copia)
    ];

    /** Nombres de archivo que nunca se copian, estén donde estén. */
    private const EXCLUDE_NAMES = ['.env', 'Thumbs.db', '.DS_Store', self::MARKER];

    // ── Nombres ──────────────────────────────────────────────────────────────

    /** "Mi Jardín 2" → "mi-jardin-2" (carpeta / URL). */
    public static function slug(string $name): string
    {
        // Mapa explícito (iconv//TRANSLIT en Windows produce "'i" para "í").
        $s = strtr(trim($name), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e',
            'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ã' => 'a', 'õ' => 'o', 'ç' => 'c', 'ä' => 'a',
            'ë' => 'e', 'ï' => 'i', 'ö' => 'o',
        ]);
        $s = strtolower($s);
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    /** "mi-jardin-2" → "mi_jardin_2" (identificador MySQL válido, máx. 64). */
    public static function dbName(string $slug): string
    {
        $db = (string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($slug));
        $db = trim($db, '_');
        if ($db !== '' && ctype_digit($db[0])) {
            $db = 'p_' . $db;
        }
        return substr($db, 0, 64);
    }

    public static function isValidDbName(string $db): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]{1,64}$/', $db);
    }

    // ── Copia limpia ─────────────────────────────────────────────────────────

    /**
     * ¿Este archivo (ruta relativa con /) va al proyecto nuevo?
     * Deja afuera git, secretos, IA materializada, landing, binarios, runtime de storage y la
     * trazabilidad del skeleton (los logs del derivado arrancan vacíos).
     */
    public static function shouldCopy(string $rel): bool
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $base = basename($rel);

        if (in_array($base, self::EXCLUDE_NAMES, true) || str_ends_with($base, '.log.tmp')) {
            return false;
        }
        foreach (self::EXCLUDE_PREFIXES as $p) {
            if (str_starts_with($rel, $p)) {
                return false;
            }
        }
        // logs/: solo el README (la historia del skeleton no es del proyecto).
        if (str_starts_with($rel, 'logs/')) {
            return $rel === 'logs/README.md';
        }
        // Runtime de storage: solo los .gitkeep.
        if (preg_match('#^system/storage/(backups|cache|uploads|sessions|logs)/#', $rel)) {
            return $base === '.gitkeep';
        }
        // Logo subido desde el panel del master.
        if (str_starts_with($rel, 'system/public/assets/branding/logo.')) {
            return false;
        }
        return true;
    }

    /**
     * Copia `$src` en `$dest` aplicando shouldCopy(). Devuelve la cantidad de archivos copiados.
     */
    public static function copyTree(string $src, string $dest): int
    {
        $src = rtrim(str_replace('\\', '/', $src), '/');
        $dest = rtrim(str_replace('\\', '/', $dest), '/');
        $count = 0;

        $dirFilter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $f) use ($src): bool {
                if (!$f->isDir()) {
                    return true;
                }
                // Podar directorios excluidos enteros (no recorrer .git ni node_modules).
                $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($src) + 1) . '/';
                foreach (self::EXCLUDE_PREFIXES as $p) {
                    if (str_starts_with($rel, $p)) {
                        return false;
                    }
                }
                return true;
            }
        );

        foreach (new \RecursiveIteratorIterator($dirFilter) as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $abs = str_replace('\\', '/', $file->getPathname());
            $rel = substr($abs, strlen($src) + 1);
            if (!self::shouldCopy($rel)) {
                continue;
            }
            $target = $dest . '/' . $rel;
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("No se pudo crear {$dir}");
            }
            if (!copy($abs, $target)) {
                throw new \RuntimeException("No se pudo copiar {$rel}");
            }
            $count++;
        }
        return $count;
    }

    // ── Archivos del proyecto nuevo ──────────────────────────────────────────

    /**
     * Archivos de la APP que se reinician en el derivado (el master trae los suyos propios).
     * @param array{name:string,slug:string,core_version:string,date:string} $info
     * @return array<string,string> ruta relativa → contenido
     */
    public static function projectFiles(array $info): array
    {
        $name = $info['name'];
        $date = $info['date'];
        $ver = $info['core_version'];

        return [
            'docs/roadmap.md' => "# Roadmap de funcionalidades y versiones — {$name}\n\n"
                . "> La fuente de verdad del roadmap es [`planning/roadmap/INDEX.md`](../planning/roadmap/INDEX.md)\n"
                . "> (metodología Roadmap → Brief → Plan: `/roadmap`, `/brief`, `/plan`). Este archivo es el\n"
                . "> resumen por versión (ver [`agentic/methodology/sprints.md`](../agentic/methodology/sprints.md)).\n\n"
                . "## Leyenda de estados\n"
                . "`📋 Pendiente` · `🟡 En progreso` · `✅ Completo` · `🧪 En testing` · `⛔ Bloqueado` · `❌ Descartado`\n\n"
                . "---\n\n"
                . "## v1.0 — MVP\n"
                . "| # | Funcionalidad | Estado | Sprint | Notas |\n"
                . "|---|---------------|--------|--------|-------|\n"
                . "| 1 | Proyecto creado desde nsSkeleton {$ver} | ✅ | S0 | /nuevo-proyecto |\n",
            'logs/features-resume.md' => "# Resumen de features implementados\n\n"
                . "**Última actualización:** {$date} | Agente: new-project | Modelo: n/a (CLI)\n\n"
                . "Histórico acumulativo de features construidos (ver `agentic/methodology/sprints.md`).\n\n"
                . "| # | Feature | Versión | Fecha | Estado | Agente | Modelo |\n"
                . "|---|---------|---------|-------|--------|--------|--------|\n",
            'logs/bugs-resume.md' => "# bugs-resume.md — Registro maestro de bugs\n\n"
                . "**Último control:** {$date} | Agente: new-project | Modelo: n/a (CLI)\n"
                . "**Última detección:** — (sin detecciones aún)\n"
                . "**Última reparación:** — (sin reparaciones aún)\n\n"
                . "---\n\n"
                . "Ver [`agentic/methodology/bug-tracking.md`](../agentic/methodology/bug-tracking.md).\n",
            'logs/walkthrough/.gitkeep' => '',
        ];
    }

    /**
     * Contenido de `nsproject.json`: identifica al árbol como proyecto derivado y deja
     * constancia de desde qué core salió.
     * @param array<string,mixed> $info
     */
    public static function marker(array $info): string
    {
        $data = [
            'role' => 'project',
            'name' => (string) ($info['name'] ?? ''),
            'slug' => (string) ($info['slug'] ?? ''),
            'created_at' => (string) ($info['created_at'] ?? date('c')),
            'created_from' => [
                'core_version' => (string) ($info['core_version'] ?? ''),
                'skeleton_commit' => (string) ($info['skeleton_commit'] ?? ''),
                'skeleton_dirty' => (bool) ($info['skeleton_dirty'] ?? false),
            ],
        ];
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    // ── Rol del árbol ────────────────────────────────────────────────────────

    /**
     * ¿Este árbol es el master del skeleton o un proyecto derivado?
     *  - Tiene `nsproject.json`                       → project (lo escribe /nuevo-proyecto).
     *  - No tiene `landing/build-core-package.php`    → project (sin empaquetador de core).
     *  - Si no                                        → master.
     * Un derivado viejo creado clonando el repo se detecta como master: marcalo con
     * `php system/console/project-role.php --mark`.
     * @return array{role:string,reason:string}
     */
    public static function role(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (is_file($root . '/' . self::MARKER)) {
            return ['role' => 'project', 'reason' => 'tiene ' . self::MARKER];
        }
        if (!is_file($root . '/landing/build-core-package.php')) {
            return ['role' => 'project', 'reason' => 'no tiene landing/build-core-package.php'];
        }
        return ['role' => 'master', 'reason' => 'tiene el empaquetador del core y no tiene ' . self::MARKER];
    }

    public static function isMaster(string $root): bool
    {
        return self::role($root)['role'] === 'master';
    }

    /** ¿La URL de remoto apunta al repo del skeleton? (un derivado no debe pushear ahí). */
    public static function remoteIsSkeleton(string $url): bool
    {
        $u = strtolower(trim($url));
        $u = (string) preg_replace('#\.git$#', '', $u);
        return str_contains($u, self::SKELETON_REPO);
    }

    /** Un directorio destino es usable si no existe o está vacío. */
    public static function destIsFree(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return false;
        }
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        return $items === [];
    }
}
