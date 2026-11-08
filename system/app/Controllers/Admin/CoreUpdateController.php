<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Audit;
use App\Services\CoreRelease;
use App\Services\CoreUpdate;
use App\Services\CoreUpdater;
use Core\Auth;
use Core\Config;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Actualización de core desde el panel (v1.20).
 *
 * Flujo: Buscar versión (latest.json) → Preparar (descarga + verificación sha256 +
 * plan + vista previa del merge) → Aplicar (confirmación escribiendo la versión) →
 * Rollback si hace falta. Usa App\Services\CoreUpdate, el mismo flujo que el CLI.
 *
 * Seguridad: permiso `core.update` (por defecto solo superadmin), CSRF en todo POST,
 * paquete únicamente desde latest.json con sha256 verificado, lock contra ejecuciones
 * simultáneas y registro en auditoría. Desactivable con CORE_UPDATE_UI=false.
 */
final class CoreUpdateController extends AdminController
{
    private CoreUpdate $cu;

    public function __construct()
    {
        parent::__construct();
        if (!(bool) Config::get('core_update', 'ui_enabled', true)) {
            \Core\View::render('errors/404', [], 'layouts/error', 404)->send();
            exit;
        }
        $this->cu = CoreUpdate::forInstall();
    }

    protected function requiredPermission(): string
    {
        return 'core.update';
    }

    public function index(): Response
    {
        $this->cu->captureBase(); // captura oportunista de originales aún sin editar

        $pending = $this->pending();
        $inspect = null;
        if ($pending !== null) {
            $inspect = $this->cu->inspect($pending['root'], $pending['lock_file'], $this->mergeEnabled());
        }

        return $this->view('admin/core-update/index', [
            'user'        => Auth::user(),
            'installed'   => $this->cu->installedVersion(),
            'latestUrl'   => CoreRelease::configuredUrl(),
            'check'       => $this->readJson('core-update-check.json'),
            'pending'     => $pending,
            'inspect'     => $inspect,
            'merge'       => $this->mergeEnabled(),
            'base'        => $this->cu->baseStatus(),
            'editedNoBase' => $this->cu->editedWithoutBase(),
            'history'     => $this->cu->history(15),
            'lastResult'  => $this->readJson('core-update-last.json'),
            'success'     => Session::getFlash('success'),
            'error'       => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    /** Consulta latest.json y guarda el resultado. */
    public function check(Request $request): Response
    {
        $this->verifyCsrf($request);
        $url = CoreRelease::configuredUrl();
        if ($url === '') {
            Session::flash('error', 'Configurá CORE_UPDATE_URL en el .env (URL de latest.json del landing).');
            return $this->back();
        }
        $c = CoreRelease::check($url, $this->cu->installedVersion());
        $this->writeJson('core-update-check.json', $c + ['at' => date('c')]);
        if (!$c['ok']) {
            Session::flash('error', $c['error']);
        } elseif ($c['update_available']) {
            Session::flash('success', "Hay una versión nueva del core: {$c['latest']}.");
        } else {
            Session::flash('success', 'El core está al día.');
        }
        return $this->back();
    }

    /** Descarga + verifica + extrae la release nueva y la deja pendiente de aplicar. */
    public function prepare(Request $request): Response
    {
        $this->verifyCsrf($request);
        @set_time_limit(300);

        $check = $this->readJson('core-update-check.json');
        $rel = $check['release'] ?? null;
        if (!is_array($rel) || empty($check['update_available'])) {
            Session::flash('error', 'Primero buscá una versión nueva.');
            return $this->back();
        }

        $this->discardPending();
        $zip = $this->cu->cacheDir() . '/core-pkg-' . date('Ymd_His') . '.zip';
        $d = CoreRelease::download((string) $rel['url'], $zip, (string) $rel['sha256']);
        if (!$d['ok']) {
            Session::flash('error', $d['error']);
            return $this->back();
        }
        $p = $this->cu->prepareSource($zip);
        if (!$p['ok']) {
            @unlink($zip);
            Session::flash('error', $p['error']);
            return $this->back();
        }

        $this->writeJson('core-update-pending.json', [
            'version'   => $p['version'],
            'root'      => $p['root'],
            'lock_file' => $p['lock_file'],
            'cleanup'   => $p['cleanup'],
            'zip'       => $zip,
            'sha256'    => $d['sha256'],
            'url'       => $rel['url'],
            'at'        => date('c'),
        ]);
        Audit::log('core.update.prepare', 'core ' . $p['version'], 'sha256 ' . $d['sha256']);
        Session::flash('success', "Paquete {$p['version']} descargado y verificado. Revisá el plan antes de aplicar.");
        return $this->back();
    }

    /** Aplica el update pendiente. Exige escribir la versión como confirmación. */
    public function apply(Request $request): Response
    {
        $this->verifyCsrf($request);
        $pending = $this->pending();
        if ($pending === null) {
            Session::flash('error', 'No hay un paquete preparado.');
            return $this->back();
        }
        if (trim((string) $request->input('confirm', '')) !== $pending['version']) {
            Session::flash('error', "Para confirmar, escribí la versión exacta ({$pending['version']}).");
            return $this->back();
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        // Las clases que se usan DESPUÉS de reemplazar archivos se cargan antes, así este
        // request termina con el código con el que empezó (el próximo ya usa el nuevo).
        foreach ([CoreUpdater::class, \App\Services\ThreeWayMerge::class, \App\Services\Migrator::class,
                  Audit::class, Response::class, Url::class, Session::class] as $cls) {
            class_exists($cls);
        }

        $res = $this->cu->run($pending['root'], $pending['lock_file'], [
            'merge'  => $this->mergeEnabled(),
            'actor'  => 'admin:' . (string) (Auth::user()['email'] ?? Auth::user()['id'] ?? '?'),
        ]);

        $this->writeJson('core-update-last.json', $res + ['at' => date('c')]);
        if ($res['backup_dir'] !== '') {
            $this->discardPending();
            @unlink($this->cu->cacheDir() . '/core-update-check.json');
        }

        Audit::log(
            'core.update.apply',
            "core {$res['from']} → {$res['to']}",
            ($res['ok'] ? 'ok' : 'con errores') . ' · merged ' . count($res['merged']) . ' · conflictos ' . count($res['conflicts'])
        );

        if ($res['backup_dir'] === '') {
            Session::flash('error', 'No se aplicó: ' . $res['error']);
        } elseif ($res['ok']) {
            $msg = "Core actualizado a {$res['to']}.";
            if ($res['conflicts'] !== []) {
                $msg .= ' Hay ' . count($res['conflicts']) . ' conflicto(s) para resolver (.new/.merge).';
            }
            Session::flash('success', $msg);
        } else {
            Session::flash('error', 'Update aplicado con errores: ' . implode('; ', array_slice($res['errors'], 0, 3)));
        }
        return $this->back();
    }

    public function rollback(Request $request): Response
    {
        $this->verifyCsrf($request);
        $dir = $this->cu->backupPath((string) $request->input('backup', ''));
        if ($dir === null) {
            Session::flash('error', 'Backup de update no encontrado.');
            return $this->back();
        }
        @set_time_limit(0);
        ignore_user_abort(true);
        $r = $this->cu->rollback($dir);
        Audit::log('core.update.rollback', basename($dir), "restaurados {$r['restored']}");
        if ($r['ok']) {
            Session::flash('success', "Rollback hecho: {$r['restored']} archivo(s) restaurados.");
        } else {
            Session::flash('error', 'Rollback con errores: ' . implode('; ', array_slice($r['errors'], 0, 3)));
        }
        return $this->back();
    }

    public function discard(Request $request): Response
    {
        $this->verifyCsrf($request);
        $this->discardPending();
        Session::flash('success', 'Paquete descartado.');
        return $this->back();
    }

    /** Completa la base del merge desde el paquete de la versión instalada (landing). */
    public function seedBase(Request $request): Response
    {
        $this->verifyCsrf($request);
        @set_time_limit(300);
        $url = CoreRelease::configuredUrl();
        $installed = $this->cu->installedVersion();
        if ($url === '') {
            Session::flash('error', 'Configurá CORE_UPDATE_URL para bajar el paquete de la versión instalada.');
            return $this->back();
        }
        $idx = CoreRelease::fetchIndex($url);
        $rel = $idx['ok'] ? CoreRelease::release($idx['index'], $url, $installed) : null;
        if ($rel === null) {
            Session::flash('error', $idx['error'] ?: "El landing no publica la versión instalada ({$installed}).");
            return $this->back();
        }
        $zip = $this->cu->cacheDir() . '/core-base-' . $installed . '.zip';
        $d = CoreRelease::download($rel['url'], $zip, $rel['sha256']);
        if (!$d['ok']) {
            Session::flash('error', $d['error']);
            return $this->back();
        }
        $p = $this->cu->prepareSource($zip);
        $stored = 0;
        if ($p['ok']) {
            $stored = $this->cu->seedBaseFrom($p['root'])['stored'];
            if ($p['cleanup']) {
                CoreUpdate::rrmdir($p['cleanup']);
            }
        }
        @unlink($zip);
        Session::flash($p['ok'] ? 'success' : 'error', $p['ok'] ? "Base completada: {$stored} original(es) agregados." : $p['error']);
        return $this->back();
    }

    // ── internos ─────────────────────────────────────────────────────────────

    private function back(): Response
    {
        return $this->redirect(Url::to('/admin/core-update'));
    }

    private function mergeEnabled(): bool
    {
        return (bool) Config::get('core_update', 'merge', true);
    }

    /** @return array{version:string,root:string,lock_file:string,cleanup:?string,zip:string,sha256:string,url:string,at:string}|null */
    private function pending(): ?array
    {
        $p = $this->readJson('core-update-pending.json');
        if ($p === null || !is_file((string) ($p['lock_file'] ?? ''))) {
            return null;
        }
        /** @var array{version:string,root:string,lock_file:string,cleanup:?string,zip:string,sha256:string,url:string,at:string} $p */
        return $p;
    }

    private function discardPending(): void
    {
        $p = $this->readJson('core-update-pending.json');
        if (is_array($p)) {
            $cache = str_replace('\\', '/', $this->cu->cacheDir());
            // Solo se borra lo que está dentro de storage/cache (defensa ante un JSON manipulado).
            $cleanup = str_replace('\\', '/', (string) ($p['cleanup'] ?? ''));
            if ($cleanup !== '' && str_starts_with($cleanup, $cache . '/')) {
                CoreUpdate::rrmdir($cleanup);
            }
            $zip = str_replace('\\', '/', (string) ($p['zip'] ?? ''));
            if ($zip !== '' && str_starts_with($zip, $cache . '/')) {
                @unlink($zip);
            }
        }
        @unlink($this->cu->cacheDir() . '/core-update-pending.json');
    }

    /** @return array<string,mixed>|null */
    private function readJson(string $name): ?array
    {
        $f = $this->cu->cacheDir() . '/' . $name;
        if (!is_file($f)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    /** @param array<string,mixed> $data */
    private function writeJson(string $name, array $data): void
    {
        @mkdir($this->cu->cacheDir(), 0775, true);
        file_put_contents(
            $this->cu->cacheDir() . '/' . $name,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
