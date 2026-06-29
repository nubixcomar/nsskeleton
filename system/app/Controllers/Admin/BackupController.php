<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BackupLog;
use App\Services\Backup;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Backup y restauración del sistema y la base de datos.
 */
final class BackupController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('backup.manage');
    }

    public function index(): Response
    {
        return $this->view('admin/backup/index', [
            'user'    => Auth::user(),
            'backups' => Backup::list(),
            'history' => BackupLog::recent(15),
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function createDb(Request $request): Response
    {
        $this->verifyCsrf($request);
        $r = Backup::createDatabaseBackup();
        $this->flashResult($r, 'Backup de base de datos creado.');
        return $this->redirect(Url::to('/admin/backup'));
    }

    public function createFiles(Request $request): Response
    {
        $this->verifyCsrf($request);
        $r = Backup::createFilesBackup();
        $this->flashResult($r, 'Backup de archivos creado.');
        return $this->redirect(Url::to('/admin/backup'));
    }

    public function createFull(Request $request): Response
    {
        $this->verifyCsrf($request);
        $db = Backup::createDatabaseBackup();
        $files = Backup::createFilesBackup();

        if (($db['ok'] ?? false) && ($files['ok'] ?? false)) {
            Session::flash('success', 'Backup completo (base + archivos) creado.');
        } else {
            Session::flash('error', 'Backup parcial. DB: ' . ($db['ok'] ? 'ok' : ($db['error'] ?? 'error'))
                . ' · Archivos: ' . ($files['ok'] ? 'ok' : ($files['error'] ?? 'error')));
        }
        return $this->redirect(Url::to('/admin/backup'));
    }

    public function download(Request $request, string $name): Response
    {
        $path = Backup::safePath($name);
        if ($path === null) {
            return $this->abort(404, 'Backup no encontrado.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function restore(Request $request): Response
    {
        $this->verifyCsrf($request);
        $name = (string) $request->input('file', '');
        $r = Backup::restoreDatabase($name);

        if ($r['ok']) {
            Session::flash('success', "Base de datos restaurada desde {$name}.");
        } else {
            Session::flash('error', 'No se pudo restaurar: ' . ($r['error'] ?? 'error'));
        }
        return $this->redirect(Url::to('/admin/backup'));
    }

    public function destroy(Request $request): Response
    {
        $this->verifyCsrf($request);
        $name = (string) $request->input('file', '');
        Backup::delete($name);
        Session::flash('success', 'Backup eliminado.');
        return $this->redirect(Url::to('/admin/backup'));
    }

    /** @param array{ok:bool,error?:string} $r */
    private function flashResult(array $r, string $okMessage): void
    {
        if ($r['ok']) {
            Session::flash('success', $okMessage);
        } else {
            Session::flash('error', 'Error: ' . ($r['error'] ?? 'desconocido'));
        }
    }
}
