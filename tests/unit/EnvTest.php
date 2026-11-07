<?php

declare(strict_types=1);

/**
 * Core\Env — parseo del .env (v1.20.2). Regresión: los comentarios al final de línea que usa
 * .env.example quedaban dentro del valor (APP_ENV="local   # local | staging | production").
 */

use Core\Env;

group('Env (.env parser)');

it('quita comentarios al final de línea en valores sin comillas', function () {
    assertEquals('local', Env::parseValue('local                 # local | staging | production'));
    assertEquals('7200', Env::parseValue('7200         # segundos (gc_maxlifetime); 7200 = 2 horas'));
    assertEquals('tls', Env::parseValue("tls\t# tls | ssl | none"));
    assertEquals('', Env::parseValue('                  # openai | deepseek'));
    assertEquals('', Env::parseValue('# solo comentario'));
});

it('conserva # pegado al valor (contraseñas, anclas)', function () {
    assertEquals('abc#123', Env::parseValue('abc#123'));
    assertEquals('https://x.com/a#frag', Env::parseValue('https://x.com/a#frag'));
});

it('comillas: toma el contenido y descarta lo que sigue', function () {
    assertEquals('mi clave # con numeral', Env::parseValue('"mi clave # con numeral"   # comentario'));
    assertEquals('${APP_NAME}', Env::parseValue('"${APP_NAME}"'));
    assertEquals('a b', Env::parseValue("'a b'"));
    assertEquals('', Env::parseValue('""'));
    assertEquals('"sin cerrar', Env::parseValue('"sin cerrar'));
});

it('valores comunes sin comentario no cambian', function () {
    assertEquals('http://localhost/x/system/public', Env::parseValue('http://localhost/x/system/public'));
    assertEquals('true', Env::parseValue(' true '));
    assertEquals('', Env::parseValue(''));
});

it('load() aplica el parseo y castea true/false sobre un .env real', function () {
    $f = sys_get_temp_dir() . '/nsenv_' . bin2hex(random_bytes(4)) . '.env';
    $p = 'NSENVTEST_' . strtoupper(bin2hex(random_bytes(3))) . '_';
    file_put_contents($f, implode("\n", [
        '# comentario de línea',
        "{$p}DEBUG=true                # false en producción",
        "{$p}ENC=tls           # tls | ssl | none",
        "{$p}EMPTY=                  # openai | deepseek",
        "{$p}PASS=\"p#ss w0rd\"  # entre comillas",
        "{$p}REF=\"\${{$p}ENC}-x\"",
    ]) . "\n");
    try {
        Env::load($f);
        assertTrue(Env::get("{$p}DEBUG") === true, 'APP_DEBUG con comentario debe castear a true');
        assertEquals('tls', Env::get("{$p}ENC"));
        // Vacío → devuelve el default (contrato de Env::get), no el texto del comentario.
        assertEquals('def', Env::get("{$p}EMPTY", 'def'));
        assertEquals('p#ss w0rd', Env::get("{$p}PASS"));
        assertEquals('tls-x', Env::get("{$p}REF"));
    } finally {
        @unlink($f);
    }
});

it('.env.example: ninguna clave queda con un comentario como valor', function () {
    $lines = file(PROJECT_PATH . '/.env.example', FILE_IGNORE_NEW_LINES) ?: [];
    $bad = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (str_contains(Env::parseValue($v), '#')) {
            $bad[] = trim($k);
        }
    }
    assertEquals([], $bad, 'claves con # en el valor parseado: ' . implode(', ', $bad));
});
