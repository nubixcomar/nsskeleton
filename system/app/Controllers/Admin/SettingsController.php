<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AppSettings;
use App\Services\Settings;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Configuración general del sistema: nombre, tagline, zona horaria y logo.
 */
final class SettingsController extends AdminController
{
    private const LOGO_EXT = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    private const LOGO_MAX = 1048576; // 1 MB

    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('settings.manage');
    }

    public function show(): Response
    {
        return $this->view('admin/settings', [
            'user'      => Auth::user(),
            'name'      => AppSettings::name(),
            'tagline'   => AppSettings::tagline(),
            'timezone'  => AppSettings::timezone(),
            'logo'      => AppSettings::logo(),
            'appVersion' => \App\Services\Version::app(),
            'coreVersion' => \App\Services\Version::core(),
            'dashPresets' => \App\Services\Dashboard::presets(),
            'dashActive'  => \App\Services\Dashboard::active(),
            'flags'     => \App\Services\FeatureFlags::all(),
            'timezones' => timezone_identifiers_list(),
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function save(Request $request): Response
    {
        $this->verifyCsrf($request);

        $name = trim((string) $request->input('name', ''));
        Settings::set('app.name', $name !== '' ? $name : 'nsSkeleton', 'app');
        Settings::set('app.tagline', trim((string) $request->input('tagline', '')), 'app');

        $appVer = trim((string) $request->input('app_version', ''));
        if ($appVer !== '') {
            Settings::set('app.version', $appVer, 'app');
        }

        $preset = trim((string) $request->input('dashboard_preset', ''));
        if ($preset !== '' && isset(\App\Services\Dashboard::presets()[$preset])) {
            Settings::set('dashboard.preset', $preset, 'app');
        }

        $tz = (string) $request->input('timezone', '');
        if (in_array($tz, timezone_identifiers_list(), true)) {
            Settings::set('app.timezone', $tz, 'app');
        }

        if ($request->input('remove_logo')) {
            Settings::set('app.logo', '', 'app');
        } elseif (!empty($_FILES['logo']['name'])) {
            $result = $this->storeLogo($_FILES['logo']);
            if ($result !== null) {
                Settings::set('app.logo', $result, 'app');
            } else {
                Session::flash('error', 'Logo inválido (png/jpg/webp/svg, máx 1 MB). El resto se guardó.');
            }
        }

        // Feature flags
        foreach (array_keys(\App\Services\FeatureFlags::defaults()) as $flag) {
            \App\Services\FeatureFlags::set($flag, (bool) $request->input('flag_' . $flag));
        }

        \App\Services\Audit::log('settings.update');
        if (Session::getFlash('error') === null) {
            Session::flash('success', 'Configuración guardada.');
        }
        return $this->redirect(Url::to('/admin/settings'));
    }

    /** @param array<string,mixed> $file @return string|null ruta relativa servible, o null si inválido */
    private function storeLogo(array $file): ?string
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > self::LOGO_MAX) {
            return null;
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::LOGO_EXT, true)) {
            return null;
        }

        $dir = BASE_PATH . '/public/assets/branding';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return null;
        }

        // Limpia logos previos para no acumular.
        foreach (glob($dir . '/logo.*') ?: [] as $old) {
            @unlink($old);
        }

        $dest = $dir . '/logo.' . $ext;
        if (!@move_uploaded_file((string) $file['tmp_name'], $dest) && !@rename((string) $file['tmp_name'], $dest)) {
            return null;
        }
        return 'assets/branding/logo.' . $ext;
    }
}
