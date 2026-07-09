<?php

declare(strict_types=1);

use Core\Request;
use Core\Response;
use Core\Router;

/**
 * v1.16 #3 — Router override real (last-wins).
 * Deriva del hallazgo 1.2: el router antiguo hacía first-match-wins, así que una ruta
 * de la app con el mismo path que el core (p. ej. `/`) nunca se alcanzaba.
 */

group('Router · override last-wins');

/** Construye un Request falso para un método+path dado. */
$req = static function (string $method, string $path): Request {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    return Request::capture();
};

it('registrar la misma ruta pisa a la anterior (app gana al core)', function () use ($req) {
    $router = new Router();
    $router->get('/', static fn (): Response => Response::html('CORE'));   // core primero
    $router->get('/', static fn (): Response => Response::html('APP'));    // app después
    $res = $router->dispatch($req('GET', '/'));
    ob_start();
    $res->send();
    $out = ob_get_clean();
    assertContains('APP', $out);
    assertTrue(!str_contains($out, 'CORE'), 'la ruta del core no debe ganar');
});

it('no confunde métodos distintos (GET vs POST del mismo path)', function () use ($req) {
    $router = new Router();
    $router->get('/x', static fn (): Response => Response::html('GET-X'));
    $router->post('/x', static fn (): Response => Response::html('POST-X'));
    // Override del GET solamente.
    $router->get('/x', static fn (): Response => Response::html('GET-X2'));

    ob_start(); $router->dispatch($req('GET', '/x'))->send(); $g = ob_get_clean();
    ob_start(); $router->dispatch($req('POST', '/x'))->send(); $p = ob_get_clean();

    assertContains('GET-X2', $g);
    assertContains('POST-X', $p); // el POST no fue tocado
});

it('rutas con parámetro también hacen override por patrón', function () use ($req) {
    $router = new Router();
    $router->get('/u/{id}', static fn (): Response => Response::html('OLD'));
    $router->get('/u/{id}', static fn ($r, $id): Response => Response::html('NEW-' . $id));
    ob_start(); $router->dispatch($req('GET', '/u/42'))->send(); $out = ob_get_clean();
    assertContains('NEW-42', $out);
});

it('rutas distintas conservan su orden y no se pisan', function () use ($req) {
    $router = new Router();
    $router->get('/a', static fn (): Response => Response::html('A'));
    $router->get('/b', static fn (): Response => Response::html('B'));
    ob_start(); $router->dispatch($req('GET', '/a'))->send(); $a = ob_get_clean();
    ob_start(); $router->dispatch($req('GET', '/b'))->send(); $b = ob_get_clean();
    assertContains('A', $a);
    assertContains('B', $b);
});
