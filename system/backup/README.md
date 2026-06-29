# backup/ — Backup y restauración

Backups en PHP puro (sin `mysqldump`): la base se vuelca a SQL y los archivos del
proyecto se comprimen en ZIP. Se guardan en `system/storage/backups/`.

## Desde el panel
`/admin/backup`: crear backup de base / archivos / completo, descargar, restaurar la
base (⚠️ sobrescribe) o eliminar. Incluye historial de actividad.

## Automático (CLI)
```
php system/backup/run.php
```
- Crea backup de base + archivos.
- Elimina backups con más de `BACKUP_RETENTION_DAYS` días (`.env`, default 30).

Programalo en el **cronmaster** (`/admin/cron`, comando `php system/backup/run.php`,
cron `0 3 * * *`) o directamente en el cron del SO.

## Notas
- Requiere la extensión `ZipArchive` (incluida en XAMPP) para el backup de archivos.
- El backup de archivos excluye `.git`, `vendor`, `node_modules`, `storage/backups` y
  `storage/cache`.
- La restauración usa el backup `.sql` para reconstruir las tablas (DROP + CREATE +
  INSERT). Hacé un backup antes de restaurar.
