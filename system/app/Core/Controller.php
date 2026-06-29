<?php

declare(strict_types=1);

namespace Core;

/**
 * Controlador base con helpers de respuesta.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    /** Aborta con un código de estado y un mensaje simple. */
    protected function abort(int $status, string $message = ''): Response
    {
        return Response::html("<h1>{$status}</h1><p>" . View::e($message) . '</p>', $status);
    }
}
