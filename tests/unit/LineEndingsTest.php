<?php

declare(strict_types=1);

/**
 * Saltos de línea del core (v1.20.1). core-lock.json se calcula sobre el disco del master; git
 * guarda LF (.gitattributes eol=lf). Si un archivo de texto del core queda en CRLF en el master,
 * el lock no coincide con lo que recibe un clon y el actualizador lo ve como "editado".
 * Solo aplica al master (en un derivado el lock viene dado y no se regenera).
 */

group('Saltos de línea del core');

it('ningún archivo de texto del core tiene CRLF (el lock debe coincidir con git)', function () {
    if (is_file(PROJECT_PATH . '/nsproject.json') || !is_file(PROJECT_PATH . '/landing/build-core-package.php')) {
        return 'skip'; // proyecto derivado
    }
    $lock = json_decode((string) file_get_contents(PROJECT_PATH . '/core-lock.json'), true);
    $files = array_keys(is_array($lock) ? ($lock['files'] ?? []) : []);
    $text = '/\.(php|md|json|sql|js|css|html|yml|yaml|sh|txt|example|htaccess|gitignore|gitattributes|gitkeep)$|^VERSION$/';

    $crlf = [];
    foreach ($files as $rel) {
        if (!preg_match($text, $rel) && !preg_match('#(^|/)\.[a-z]+$#', $rel)) {
            continue;
        }
        $abs = PROJECT_PATH . '/' . $rel;
        if (is_file($abs) && str_contains((string) file_get_contents($abs), "\r\n")) {
            $crlf[] = $rel;
        }
    }
    assertEquals([], $crlf, 'archivos del core con CRLF (corré `git add --renormalize . && git checkout -- .`): ' . implode(', ', array_slice($crlf, 0, 10)));
});
