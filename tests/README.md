# tests/ — Suite de tests

Runner propio, sin dependencias. Convención de la metodología: **cada fase nueva
agrega sus tests acá**.

## Correr
```
php tests/run.php
```
Devuelve código de salida 1 si algún test falla (apto para CI / cron / pre-deploy).

## Estructura
```
tests/
├── bootstrap.php     Helpers de aserción + registro (it, assert*, group)
├── run.php           Descubre y ejecuta unit/ y feature/
├── unit/             Tests sin dependencias externas (no requieren DB)
└── feature/          Tests que tocan servicios externos (DB); se SKIPpean si no hay MySQL
```

## Escribir un test
```php
group('MiServicio');
it('hace lo esperado', function () {
    assertEquals(2, 1 + 1);
});
// Devolver 'skip' desde la closure omite el test (ej. si falta MySQL).
```

Aserciones: `assertTrue`, `assertFalse`, `assertEquals`, `assertNull`, `assertNotNull`,
`assertContains`, `assertCount`.

## Tests de DB aislados con `itDb()` (recomendado)

Para tests que **siembran datos**, usá `itDb()` en vez de `it()`: corre la closure dentro de
una transacción y hace **rollback siempre** al terminar (pase o falle). Así los datos no
quedan en la DB ni ensucian otros tests (evita el "esperaba 2 y obtuvo 4" del hallazgo 1.7).

```php
itDb('cuenta exactamente el fixture sembrado', function () {
    Database::run('INSERT INTO settings (`key`,`value`,`group`) VALUES (?,?,?)', ['x','v','g']);
    $row = Database::selectOne('SELECT COUNT(*) c FROM settings WHERE `group` = ?', ['g']);
    assertEquals(1, (int) $row['c']);        // no hace falta limpiar: el rollback lo revierte
});
```

`itDb()` se saltea solo si no hay MySQL. Para lógica de la app, `Core\Database::transaction($fn)`
commitea al terminar OK y revierte si $fn lanza.

## Cobertura inicial (v1.1)
- `unit/CronExpressionTest` · `unit/FileManagerTest` (incl. anti-traversal) ·
  `unit/ChartsTest` · `unit/ConnectorsTest` (SMTP/IA/HTTP error-paths).
- `feature/DatabaseTest` (conexión + dump; skip sin MySQL).
