# Walkthrough — Iteración 6: backup y restauración

**Fecha y hora:** 2026-06-22 18:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 0.1.0

---

## Resumen ejecutivo
Se implementó el sistema de backup/restauración en PHP puro (sin `mysqldump`): volcado
SQL de la base, ZIP de los archivos del proyecto, restauración de base, retención y
panel de gestión. El backup de archivos fue verificado de verdad generando un ZIP real.

## Cambios realizados
- **Migración**: `20260622_0004_create_backup_log.sql` (`backup_log`).
- **Backup** (`App\Services\Backup`): `createDatabaseBackup` (DROP+CREATE+INSERT por
  tabla, FK checks off), `createFilesBackup` (ZipArchive con exclusiones),
  `restoreDatabase`, `list`, `delete`, `cleanup` (retención), `safePath` (anti-traversal),
  log de auditoría best-effort.
- **Modelo**: `App\Models\BackupLog`.
- **CLI**: `system/backup/run.php` (backup completo + retención) + `system/backup/README.md`.
- **Controlador**: `Admin\BackupController` (index, createDb, createFiles, createFull,
  download streaming, restore, destroy).
- **Vista**: `admin/backup/index` (acciones, listado, historial, ayuda).
- **Rutas + menú**: registradas; ítem "Backups" activado.

## Verificación
- `php -l` en todo `system/` → sin errores.
- **createFilesBackup (real, sin DB)**: generó un ZIP con **163 entradas** incluyendo
  README.md; luego se limpió correctamente.
- **safePath**: rechaza `../../config/database.php` → null (anti path-traversal).
- `GET /admin/backup` sin sesión → 302 a login (guard OK).
- ⚠️ Backup/restore de base de datos requieren MySQL arriba; pendiente de verificar con
  la base levantada.

## Decisiones de diseño
- Dump SQL en PHP puro (portátil, no depende de binarios externos).
- Restauración por reconstrucción (DROP IF EXISTS + CREATE + INSERT) con FK checks off.
- Descarga por streaming (`readfile`) para no cargar el archivo entero como string.
- Retención configurable vía `BACKUP_RETENTION_DAYS`.

## Pendientes / follow-ups
- **Iteración 7**: librería de gráficos (barras, torta, dashboards) con Chart.js.
- Verificar dump/restore de base con MySQL arriba.

## Referencias
- `system/app/Services/Backup.php`, `system/backup/`, `system/app/Controllers/Admin/BackupController.php`.
