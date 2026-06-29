# Walkthrough — Fase D2: datos demo / seeders

**Fecha y hora:** 2026-06-23 07:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S5 (v1.4) | **Versión:** 1.3 → 1.4

---

## Resumen ejecutivo
Se agregó un seeder de datos de ejemplo (idempotente, con undo) para que un proyecto
nuevo arranque con contenido. Verificado.

## Cambios realizados
- **`App\Services\DemoSeeder`**: `seed` (2 admins editor/viewer + 2 tareas cron demo,
  no duplica), `undo` (elimina), `isSeeded`.
- **CLI** `system/database/seed-demo.php` (`--undo`).
- **`database/README.md`**: documentado.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **130/130 PASS** (+1 feature `DemoSeeder`).
- **Smoke**: primera corrida → +2 admins, +2 tareas; segunda → +0 (idempotente);
  `--undo` → elimina 2 + 2. Estado final limpio.

## Decisiones de diseño
- Idempotente por marcadores (emails demo, prefijo `[demo]` en cron).
- `undo` es explícito (lo corre el humano), no automático.
- Admins demo con roles distintos (editor/viewer) para probar el RBAC.

## Pendientes / follow-ups
- **D3** Módulo showcase end-to-end (generado con el generador B1) — siguiente.

## Referencias
- `system/app/Services/DemoSeeder.php`, `system/database/seed-demo.php`,
  `tests/feature/DemoSeederTest.php`.
