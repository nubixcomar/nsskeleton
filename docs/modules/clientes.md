# Módulo showcase — Clientes

**Última actualización:** 2026-06-23 | Agente: module-generator | Modelo: claude-opus-4-8

Ejemplo end-to-end de un módulo CRUD generado con el comando `/nuevo-modulo`
(`php system/console/make-module.php Cliente clientes "..."`). Sirve como referencia de
todo lo que produce el generador y de cómo se integra con el resto del sistema.

## Cómo se generó
```
php system/console/make-module.php Cliente clientes \
  "nombre:string email:string telefono:string direccion:text activo:bool"
php system/database/migrate.php
```

## Qué quedó creado
| Componente | Ubicación |
|------------|-----------|
| Migración (con `@DOWN`) | `system/database/migrations/<stamp>_create_clientes.sql` |
| Modelo     | `system/app/Models/Cliente.php` |
| Controlador CRUD | `system/app/Controllers/Admin/ClienteController.php` |
| Vistas (listado con búsqueda+paginación, formulario) | `system/app/Views/admin/clientes/` |
| Rutas      | `system/config/routes/clientes.php` |
| Ítem de menú | `system/config/modules_menu.php` |

## Campos
| Campo | Tipo | Input |
|-------|------|-------|
| nombre | string | text |
| email | string | text |
| telefono | string | text |
| direccion | text | textarea |
| activo | bool | checkbox |

## Acceso
- **Backend**: `/admin/clientes` (listar, crear, editar, eliminar) — con búsqueda y
  paginación.
- **API REST**: registrado en `config/api.php` → `GET/POST /api/clientes`,
  `GET/PUT/DELETE /api/clientes/{id}` (requiere Bearer token; ver `/admin/api-tokens`).

## Extender
- Agregar validaciones específicas en `ClienteController::data()`.
- Relacionar con otros módulos (ej. facturas, pedidos) creando sus migraciones y FKs.
- Ajustar columnas visibles en `Views/admin/clientes/index.php`.

> Es la base ideal para los sistemas reales del usuario (facturación, WMS, stock):
> generar las entidades con `/nuevo-modulo` y luego enriquecerlas.
