<?php

declare(strict_types=1);

use App\Services\Mailer;

group('Mailer::render (plantillas)');

it('renderiza la plantilla de notificación dentro del layout', function () {
    $html = Mailer::render('emails/notification', [
        'title' => 'Asunto Importante',
        'body'  => '<p>Cuerpo del mensaje</p>',
    ]);
    assertContains('Asunto Importante', $html);
    assertContains('Cuerpo del mensaje', $html);
    assertContains('<!DOCTYPE html>', $html); // vino envuelto en el layout
});

it('incluye el botón de acción si se pasa actionUrl', function () {
    $html = Mailer::render('emails/notification', [
        'title'      => 'Hola',
        'body'       => 'x',
        'actionUrl'  => 'https://ejemplo.com/abrir',
        'actionText' => 'Abrir panel',
    ]);
    assertContains('https://ejemplo.com/abrir', $html);
    assertContains('Abrir panel', $html);
});
