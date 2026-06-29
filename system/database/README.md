# database/ — Migraciones y seeds

## Requisito
MySQL/MariaDB corriendo (en XAMPP: iniciar el módulo **MySQL** en el panel de control).
Configurar credenciales en el `.env` de la raíz (`DB_*`).

## Aplicar migraciones
```
php system/database/migrate.php
```
- Crea la base si no existe.
- Crea/usa la tabla de control `schema_migrations`.
- Aplica en orden los `*.sql` de `migrations/` que falten.

## Cargar datos iniciales (admin por defecto)
```
php system/database/seed.php
```
Crea un administrador inicial **solo si no existe ninguno**:
- email: `admin@nsskeleton.local`
- password: `admin1234`  ← cambialo tras el primer login.

## Datos de ejemplo (opcional)
```
php system/database/seed-demo.php          # carga admins/tareas demo (idempotente)
php system/database/seed-demo.php --undo    # los elimina
```
Admins demo: `editor@demo.local`, `viewer@demo.local` (password `demo1234`).

## Convenciones
- Migraciones: `YYYYMMDD_NNNN_descripcion.sql`, idempotentes donde sea posible
  (`CREATE TABLE IF NOT EXISTS`, etc.).
- Una migración por cambio de esquema. No editar migraciones ya aplicadas: crear una nueva.
- Cambios riesgosos pasan antes por el agente `migration-analyst`
  (ver `agentic/skills/migration-analyst/SKILL.md`).
