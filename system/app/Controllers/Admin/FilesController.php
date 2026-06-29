<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\FileManager;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * File manager: navegación de carpetas/subcarpetas y subida de archivos.
 */
final class FilesController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('files.manage');
    }

    public function index(Request $request): Response
    {
        $rel = (string) $request->query('path', '');
        $listing = FileManager::list($rel);

        return $this->view('admin/files/index', [
            'user'       => Auth::user(),
            'rel'        => FileManager::normalizeRel($rel) ?? '',
            'breadcrumb' => FileManager::breadcrumb($rel),
            'dirs'       => $listing['dirs'],
            'files'      => $listing['files'],
            'shares'     => \App\Services\FileShare::map(),
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function share(Request $request): Response
    {
        $this->verifyCsrf($request);
        $rel = (string) $request->input('path', '');
        $parent = (string) $request->input('parent', '');

        if (FileManager::resolve($rel) === null || !is_file((string) FileManager::resolve($rel))) {
            Session::flash('error', 'Archivo no encontrado.');
        } else {
            $token = \App\Services\FileShare::share($rel);
            Session::flash('success', 'Link público generado: ' . Url::to('/a/' . $token));
        }
        return $this->redirect($this->backTo($parent));
    }

    public function unshare(Request $request): Response
    {
        $this->verifyCsrf($request);
        \App\Services\FileShare::unshare((string) $request->input('path', ''));
        Session::flash('success', 'Link público revocado.');
        return $this->redirect($this->backTo((string) $request->input('parent', '')));
    }

    public function upload(Request $request): Response
    {
        $this->verifyCsrf($request);
        $rel = (string) $request->input('path', '');

        $result = FileManager::upload($rel, $_FILES['file'] ?? []);
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? "Archivo «{$result['name']}» subido." : ($result['error'] ?? 'Error.'));

        return $this->redirect($this->backTo($rel));
    }

    public function mkdir(Request $request): Response
    {
        $this->verifyCsrf($request);
        $rel = (string) $request->input('path', '');

        $result = FileManager::makeDir($rel, (string) $request->input('name', ''));
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Carpeta creada.' : ($result['error'] ?? 'Error.'));

        return $this->redirect($this->backTo($rel));
    }

    public function delete(Request $request): Response
    {
        $this->verifyCsrf($request);
        $rel = (string) $request->input('path', '');
        $parent = (string) $request->input('parent', '');

        $result = FileManager::delete($rel);
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Eliminado.' : ($result['error'] ?? 'Error.'));

        return $this->redirect($this->backTo($parent));
    }

    public function download(Request $request): Response
    {
        $rel = (string) $request->query('path', '');
        $path = FileManager::resolve($rel);
        if ($path === null || !is_file($path)) {
            return $this->abort(404, 'Archivo no encontrado.');
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

    public function raw(Request $request): Response
    {
        $rel = (string) $request->query('path', '');
        $path = FileManager::resolve($rel);
        if ($path === null || !is_file($path)) {
            return $this->abort(404, 'Archivo no encontrado.');
        }

        $mimes = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf', 'txt' => 'text/plain',
        ];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function rename(Request $request): Response
    {
        $this->verifyCsrf($request);
        $result = FileManager::rename((string) $request->input('path', ''), (string) $request->input('name', ''));
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Renombrado.' : ($result['error'] ?? 'Error.'));
        return $this->redirect($this->backTo((string) $request->input('parent', '')));
    }

    public function move(Request $request): Response
    {
        $this->verifyCsrf($request);
        $result = FileManager::move((string) $request->input('path', ''), (string) $request->input('dest', ''));
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Movido.' : ($result['error'] ?? 'Error.'));
        return $this->redirect($this->backTo((string) $request->input('parent', '')));
    }

    private function backTo(string $rel): string
    {
        $norm = FileManager::normalizeRel($rel) ?? '';
        return Url::to('/admin/files') . ($norm !== '' ? '?path=' . rawurlencode($norm) : '');
    }
}
