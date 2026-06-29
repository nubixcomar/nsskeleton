<?php

declare(strict_types=1);

use App\Services\Breadcrumb;

group('Breadcrumb (core)');

$labels = static fn (array $trail): array => array_map(static fn ($c) => $c['label'], $trail);

it('en el dashboard sólo muestra Inicio', function () use ($labels) {
    assertEquals(['Inicio'], $labels(Breadcrumb::trail('/admin')));
});

it('módulo dentro de un grupo: Inicio > Grupo > Módulo', function () use ($labels) {
    assertEquals(['Inicio', 'Usuarios', 'Administradores'], $labels(Breadcrumb::trail('/admin/users')));
});

it('detecta la acción (editar)', function () use ($labels) {
    assertEquals(['Inicio', 'Usuarios', 'Administradores', 'Editar'], $labels(Breadcrumb::trail('/admin/users/5/edit')));
});

it('detecta permisos y nuevo', function () use ($labels) {
    $t = Breadcrumb::trail('/admin/users/5/permissions');
    assertEquals('Permisos', $t[count($t) - 1]['label']);
    assertEquals(['Inicio', 'Sistema', 'Tareas / Cron', 'Nuevo'], $labels(Breadcrumb::trail('/admin/cron/create')));
});

it('módulo en otro grupo (Configuración)', function () use ($labels) {
    assertEquals(['Inicio', 'Configuración', 'Conector IA'], $labels(Breadcrumb::trail('/admin/ai')));
});

it('rutas fuera del menú usan etiquetas extra', function () use ($labels) {
    assertEquals(['Inicio', 'Mi perfil'], $labels(Breadcrumb::trail('/admin/profile')));
    assertEquals(['Inicio', 'Seguridad (2FA)'], $labels(Breadcrumb::trail('/admin/security/2fa')));
});

it('agrega el nombre del registro con $extra (Editar > Juan Pérez)', function () use ($labels) {
    $t = Breadcrumb::trail('/admin/users/5/edit', 'Juan Pérez');
    assertEquals(['Inicio', 'Usuarios', 'Administradores', 'Editar', 'Juan Pérez'], $labels($t));
    assertNull($t[count($t) - 1]['url']);
});

it('$extra vacío no agrega nada', function () use ($labels) {
    assertEquals(['Inicio', 'Usuarios', 'Administradores', 'Editar'], $labels(Breadcrumb::trail('/admin/users/5/edit', '')));
    assertEquals(['Inicio', 'Usuarios', 'Administradores', 'Editar'], $labels(Breadcrumb::trail('/admin/users/5/edit', null)));
});

it('el último crumb no tiene url (página actual), el primero sí', function () {
    $t = Breadcrumb::trail('/admin/users/5/edit');
    assertNull($t[count($t) - 1]['url']);
    assertEquals('/admin', $t[0]['url']);
});
