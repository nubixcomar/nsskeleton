---
name: module-generator
summary: Genera un módulo CRUD completo (migración, modelo, controlador, vistas, rutas, menú) desde una definición de campos.
generic: true
---

## Rol
Acelerador de desarrollo: a partir de un nombre y una lista de campos, crea un módulo
ABM/CRUD funcional y enganchado al backend, sin escribir código repetitivo.

## Entrada
- Nombre del modelo (singular, ej. `Producto`).
- Tabla (plural, ej. `productos`).
- Campos: `nombre:tipo` separados por espacio. Tipos: `string`, `text`, `int`,
  `decimal`, `bool`, `date`, `datetime`.

> Detalle del generador (tipos soportados, FKs `campo:fk:tabla`, reglas inline
> `campo:tipo:required,unique`, qué archivos produce) en el
> [manual del core](../../knowledge/core-manual.md) §6 y la receta de módulo nuevo.

## Tarea
1. Ejecutar el generador del stack (en php-mvc:
   `php system/console/make-module.php <Model> <tabla> "campo:tipo ..."`).
2. Aplicar la migración (`php system/database/migrate.php`).
3. Verificar el CRUD en `/admin/<tabla>` (listar, crear, editar, eliminar).
4. Ajustar validaciones/labels si el dominio lo requiere.

## Reglas
- Respetar el aislamiento del feature (ver [`../../rules/new-features-rules.md`](../../rules/new-features-rules.md)):
  el módulo queda en sus propios archivos (modelo, controlador, vistas, rutas) y se
  engancha por `config/routes/` y `config/modules_menu.php` sin tocar el núcleo.
- Los paths concretos viven en [`../../adapters/php-mvc/conventions.md`](../../adapters/php-mvc/conventions.md).

## Salida
- Migración + modelo + controlador CRUD + vistas (index/form) + rutas + ítem de menú.
- Walkthrough + línea en `logs/module-generator.log` según
  [`../../methodology/logging.md`](../../methodology/logging.md).
