---
name: nuevo-modulo
usage: /nuevo-modulo <Model> <tabla> "campo:tipo ..."
spawns: [module-generator]
---

## Qué hace
Genera un módulo CRUD completo (ABM) y lo engancha al backend de administración.

## Proceso
1. Invoca el agente `module-generator`.
2. En el stack php-mvc ejecuta:
   `php system/console/make-module.php <Model> <tabla> "campo:tipo ..."`
3. Aplica la migración y verifica el CRUD en `/admin/<tabla>`.

## Ejemplo
`/nuevo-modulo Producto productos "nombre:string precio:decimal stock:int activo:bool"`

## Restricciones
- El módulo queda aislado (no modifica el núcleo); se engancha por `config/routes/` y
  `config/modules_menu.php`.
