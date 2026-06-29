# database/migrations/app/ — Migraciones de la APP

Migraciones SQL **del proyecto** (no del core). El actualizador de core **no toca** este
directorio. El generador de módulos (`/nuevo-modulo`) deja acá las migraciones de los
módulos que creás.

- Mismo formato que las del core: `YYYYMMDD_HHMM_descripcion.sql`, con sección de reversa
  opcional tras `-- @DOWN`.
- `Migrator::migrate()` (sin argumentos) aplica **primero las del core** y luego estas.
- El actualizador de core corre `Migrator::migrateCore()` (solo el directorio padre), así
  un update nunca re-corre ni pisa tus migraciones.

> Mantené acá tus migraciones para que un update de core sea indoloro. Las del core viven en
> el directorio padre `database/migrations/`.
