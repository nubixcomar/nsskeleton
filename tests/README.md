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

## Cobertura inicial (v1.1)
- `unit/CronExpressionTest` · `unit/FileManagerTest` (incl. anti-traversal) ·
  `unit/ChartsTest` · `unit/ConnectorsTest` (SMTP/IA/HTTP error-paths).
- `feature/DatabaseTest` (conexión + dump; skip sin MySQL).
