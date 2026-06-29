<?php

declare(strict_types=1);

namespace Core;

use Throwable;

/**
 * Kernel de la aplicación: arranca el entorno, enruta la petición y emite la respuesta.
 */
final class App
{
    public function run(): void
    {
        $config = Config::load('app');

        $timezone = $config['timezone'] ?? 'UTC';
        try {
            $timezone = \App\Services\AppSettings::timezone($timezone);
        } catch (Throwable) {
            // si la base no está disponible, se usa el de config/.env
        }
        date_default_timezone_set($timezone);

        $debug = (bool) ($config['debug'] ?? false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting($debug ? E_ALL : 0);

        Session::start();

        try {
            $request = Request::capture();

            $router = new Router();
            $registerRoutes = require BASE_PATH . '/config/routes.php';
            $registerRoutes($router);

            $response = $router->dispatch($request);
        } catch (Throwable $e) {
            $response = $this->handleException($e, $debug);
        }

        foreach (Security::headers(Security::isHttps()) as $name => $value) {
            $response->header($name, $value);
        }

        $response->send();
    }

    private function handleException(Throwable $e, bool $debug): Response
    {
        // Log mínimo a storage/logs.
        $logDir = BASE_PATH . '/storage/logs';
        if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
            $entry = sprintf(
                "[%s] %s: %s in %s:%d\n",
                date('Y-m-d H:i:s'),
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            @file_put_contents($logDir . '/app.log', $entry, FILE_APPEND);
        }

        $detail = $debug
            ? $e::class . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()
                . "\n\n" . $e->getTraceAsString()
            : '';

        if (View::exists('errors/500')) {
            try {
                return View::render('errors/500', ['debug' => $debug, 'detail' => $detail], 'layouts/error', 500);
            } catch (Throwable) {
                // si falla el render, cae al fallback plano
            }
        }

        $body = $debug
            ? '<h1>500 — Error</h1><pre>' . htmlspecialchars($detail) . '</pre>'
            : '<h1>500 — Error interno</h1><p>Ocurrió un problema. Intentá más tarde.</p>';

        return Response::html($body, 500);
    }
}
