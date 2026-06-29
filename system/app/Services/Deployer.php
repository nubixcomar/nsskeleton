<?php

declare(strict_types=1);

namespace App\Services;

use Core\Env;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Deploy del proyecto por FTP o git, leyendo credenciales del `.env`.
 * La ejecución real es opt-in (--run en el CLI): nunca sube/pushea por sí solo.
 */
final class Deployer
{
    private const EXCLUDE = ['/.git/', '/vendor/', '/node_modules/', '/tools/bin/', '/landing/downloads/'];

    /** @return array<string,mixed> */
    public static function config(): array
    {
        return [
            'ftp_host'   => (string) Env::get('FTP_HOST', ''),
            'ftp_port'   => (int) Env::get('FTP_PORT', 21),
            'ftp_user'   => (string) Env::get('FTP_USER', ''),
            'ftp_pass'   => (string) Env::get('FTP_PASS', ''),
            'ftp_path'   => (string) Env::get('FTP_REMOTE_PATH', '/'),
            'git_remote' => (string) Env::get('GIT_REMOTE_URL', ''),
            'branch'     => (string) Env::get('DEPLOY_BRANCH', 'main'),
        ];
    }

    public static function ftpConfigured(): bool
    {
        $c = self::config();
        return $c['ftp_host'] !== '' && $c['ftp_user'] !== '';
    }

    /** Archivos a deployar (relativos), con exclusiones (secretos, runtime, deps). @return array<int,string> */
    public static function filesToDeploy(): array
    {
        $root = str_replace('\\', '/', PROJECT_PATH);
        $out = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(PROJECT_PATH, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $abs = str_replace('\\', '/', $entry->getPathname());
            if (basename($abs) === '.env') {
                continue;
            }
            foreach (self::EXCLUDE as $ex) {
                if (str_contains($abs, $ex)) {
                    continue 2;
                }
            }
            if (preg_match('#/storage/(backups|cache|uploads|logs)/#', $abs) && basename($abs) !== '.gitkeep') {
                continue;
            }
            $out[] = ltrim(str_replace($root, '', $abs), '/');
        }
        sort($out);
        return $out;
    }

    /** @return array<int,string> comandos git que se ejecutarían */
    public static function gitCommands(): array
    {
        $c = self::config();
        return [
            'git add -A',
            'git commit -m "deploy: ' . date('Y-m-d H:i') . '"',
            'git push origin ' . $c['branch'],
        ];
    }

    /** @return array{ok:bool,uploaded:int,error:string} */
    public static function ftpDeploy(?callable $log = null): array
    {
        if (!function_exists('ftp_connect')) {
            return ['ok' => false, 'uploaded' => 0, 'error' => 'La extensión FTP de PHP no está disponible.'];
        }
        $c = self::config();
        $conn = @ftp_connect($c['ftp_host'], $c['ftp_port'], 15);
        if ($conn === false) {
            return ['ok' => false, 'uploaded' => 0, 'error' => "No se pudo conectar a {$c['ftp_host']}:{$c['ftp_port']}."];
        }
        if (!@ftp_login($conn, $c['ftp_user'], $c['ftp_pass'])) {
            ftp_close($conn);
            return ['ok' => false, 'uploaded' => 0, 'error' => 'Login FTP fallido.'];
        }
        @ftp_pasv($conn, true);

        $uploaded = 0;
        foreach (self::filesToDeploy() as $rel) {
            $remote = rtrim($c['ftp_path'], '/') . '/' . $rel;
            self::ftpEnsureDir($conn, dirname($remote));
            if (@ftp_put($conn, $remote, PROJECT_PATH . '/' . $rel, FTP_BINARY)) {
                $uploaded++;
                if ($log !== null) {
                    $log($rel);
                }
            }
        }
        ftp_close($conn);
        return ['ok' => true, 'uploaded' => $uploaded, 'error' => ''];
    }

    private static function ftpEnsureDir($conn, string $dir): void
    {
        $parts = explode('/', trim($dir, '/'));
        $path = '';
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $path .= '/' . $p;
            if (!@ftp_chdir($conn, $path)) {
                @ftp_mkdir($conn, $path);
            }
        }
        @ftp_chdir($conn, '/');
    }
}
