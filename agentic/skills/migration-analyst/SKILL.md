---
name: migration-analyst
summary: Evalúa scripts SQL y cambios de esquema ANTES de ejecutarlos (riesgos, locks, pérdida de datos, rollback), sin tocar la base.
generic: true
---

## Rol

Especialista en análisis de migraciones de datos. Evalúa scripts SQL, imports
masivos o cambios de esquema **antes** de que se ejecuten, anticipando riesgos:
operaciones destructivas, locks, pérdida de datos, ruptura de integridad y
ausencia de plan de rollback. Es una tarea de **revisión**: no ejecuta nada.

## Entrada

El script SQL, el archivo de migración o la descripción del cambio de esquema a
analizar. Si no se indica, se asume la última migración pendiente del proyecto.

> Las migraciones concretas y la convención de nombre/ubicación viven en el adapter
> del stack —[`../../adapters/php-mvc/conventions.md`](../../adapters/php-mvc/conventions.md)
> (en el stack por defecto: `system/database/migrations/`)— y en
> [`../../../docs/stack.md`](../../../docs/stack.md). Este skill **referencia** esos
> paths, no los incrusta ni copia el SQL en sus informes más de lo necesario.

## Tarea

1. **Lectura desde disco (obligatoria).** Releer el script/migración **ahora** con
   la herramienta de lectura, aunque ya se haya visto en la sesión. Analizar
   contenido cacheado produce falsos positivos. Leer primero, analizar después.
2. **Revisar línea por línea** identificando operaciones destructivas:
   `DROP`, `TRUNCATE`, `DELETE`/`UPDATE` sin `WHERE`, `ALTER ... DROP COLUMN`,
   reescrituras masivas.
3. **Detectar cambios de esquema que rompan la capa de datos o el ORM**: renombrado
   o eliminación de columnas, cambios de tipo, cambios de colación/charset,
   modificación de claves primarias o únicas.
4. **Estimar el impacto sobre tablas de alto volumen**: locks de tabla, tiempo de
   ejecución, bloqueo de escrituras. Recomendar operación online / sin bloqueo
   cuando el motor lo soporte (p. ej. `ALGORITHM=INPLACE, LOCK=NONE` en MySQL/MariaDB).
5. **Verificar integridad referencial**: ¿el cambio rompe claves foráneas existentes,
   deja huérfanos o viola constraints? ¿El orden de las operaciones es seguro?
6. **Proponer un plan de rollback** para cada operación de riesgo (script inverso o,
   cuando no es reversible, backup previo obligatorio).
7. **Clasificar el riesgo global**: `ALTO` / `MEDIO` / `BAJO`.

## Reglas

- **PROHIBIDO EJECUTAR.** No correr el script, no aplicar la migración, no modificar
  la base de datos ni ningún archivo de la app. Esta tarea es solo análisis.
- **Siempre proponer el rollback antes de aprobar** la migración. Sin rollback (o
  backup que lo sustituya), el riesgo no puede bajarse de `ALTO`.
- Si el riesgo es `ALTO`: recomendar explícitamente **backup previo** y ejecución en
  **horario de bajo tráfico**, y desaconsejar el despliegue automático.
- Los riesgos detectados que constituyan un defecto real (p. ej. `DELETE` sin `WHERE`
  por error, FK rota) se registran como **bug/observación** según
  [`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md), con su ID
  `BUG-NNN` y prioridad.
- **Redactar en español**, técnico y preciso, apuntando a la línea exacta del script
  y explicando la causa y el impacto.
- Agnóstico al motor: razonar sobre el SQL real; señalar diferencias de comportamiento
  entre motores (MySQL/MariaDB/PostgreSQL) cuando sean relevantes.

## Salida

Registrar siempre, según [`../../methodology/logging.md`](../../methodology/logging.md):

1. **`logs/migration-analyst.log`** — *append* de 1 línea por análisis
   (timestamp, tipo `AUDIT`, migración/módulo, síntesis del riesgo global, modelo).
2. **`logs/walkthrough/YYYY-MM-DD_HHMM_migration-analyst_<migracion>.md`** — informe
   de la sesión: tabla de operaciones de riesgo (operación · línea · impacto ·
   rollback), nivel de riesgo global, recomendaciones (backup, ventana, online DDL) y
   estado de aprobación. Nunca se sobrescribe.
3. **`logs/bugs-resume.md`** — si el análisis detecta defectos reales, dar de alta los
   bugs/observaciones según [`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md).
