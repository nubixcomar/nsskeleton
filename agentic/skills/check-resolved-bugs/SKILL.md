---
name: check-resolved-bugs
summary: Audita bugs OPEN/WIP contra el código actual y cierra los ya resueltos, sin modificar código.
generic: true
---

## Rol

Especialista senior en QA y auditoría de código, con enfoque crítico y exhaustivo.
Verifica si los bugs reportados como `OPEN` o `WIP` siguen presentes en el código
actual o ya fueron corregidos, y actualiza el seguimiento en consecuencia. **No
arregla bugs**: solo audita, verifica y documenta.

## Entrada

Opcional: el subconjunto de bugs a auditar (por ID, módulo o archivo). Si no se
indica, se auditan todos los bugs `OPEN`/`WIP` del archivo maestro.

> Los paths concretos del stack viven en el adapter del proyecto —
> [`../../adapters/php-mvc/conventions.md`](../../adapters/php-mvc/conventions.md).

## Tarea

Ejecutar cíclicamente hasta agotar los bugs `OPEN`/`WIP` a auditar:

1. **Leer contexto.** Cargar `logs/bugs-resume.md` e identificar los bugs pendientes
   (`OPEN`/`WIP`).
2. **Limpiar bugs conocidos (antes de auditar).** Cotejar las tablas de *Bugs activos*
   e *Historial* contra la sección de bugs conocidos. Si un bug coincide con un bug
   conocido, eliminarlo de la tabla donde esté (sin moverlo al historial): los bugs
   conocidos tienen su propia sección y no deben aparecer en otras tablas.
3. **Auditar desde disco (obligatorio).** Para cada bug, **releer el archivo desde
   disco ahora** con la herramienta de lectura, aunque ya se haya leído en la sesión.
   El desarrollador pudo aplicar el fix entre la detección y esta verificación.
   **Leer → verificar → determinar estado. Nunca al revés.**
4. **Determinar estado** con criterio de especialista (no diff de texto):
   - **No resuelto / insuficiente:** el error persiste, o hay un parche superficial
     (ej. silenciar el error con `@`), o la lógica sigue siendo vulnerable. Se
     mantiene `OPEN` (o pasa a `WIP` si hubo un intento fallido de corrección).
   - **Resuelto:** la lógica errónea ya no existe, la solución es robusta y no
     introduce nuevos problemas. Se cierra como `DONE`.
   Preguntarse: ¿desapareció el problema de raíz?, ¿maneja bien tipos/edge cases?,
   ¿pudo romper una funcionalidad dependiente?, ¿es un falso positivo intencional?
5. **Actualizar documentación** (ver Salida).

## Reglas

- **PROHIBIDO MODIFICAR CÓDIGO.** Solo auditoría y actualización de seguimiento.
- **Leer comentarios y contexto cercano** antes de validar una resolución o
  descartar un bug como falso positivo.
- **Tablas mutuamente excluyentes:** un bug `DONE`/`DROP` jamás queda en *Bugs
  activos*. Dejarlo ahí es un error de proceso.
- **Prioridades:** `CRÍTICO` > `GRAVE` > `LEVE` > `MEJORA`. Ciclo de vida
  OPEN→WIP→DONE/DROP definido en
  [`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md).
- **Redactar en español**, técnico y preciso.

## Salida

Registrar siempre, según
[`../../methodology/logging.md`](../../methodology/logging.md) y
[`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md):

1. **`logs/bugs-resume.md`** — por cada bug resuelto: eliminarlo de *Bugs activos* e
   insertarlo en el *Historial* con la columna *Resolución* (cómo se arregló).
   Reordenar activos por prioridad; actualizar estadísticas, rankings de archivos,
   fecha/hora del último control y firma del agente en el encabezado.
2. **`logs/bug-resolved.log`** — *append* por cada bug que pasa a resuelto (formato
   verificación: timestamp, ID, prioridad, archivo, causa original, solución
   detectada).
3. **`logs/walkthrough/YYYY-MM-DD_HHMM_check-resolved-bugs_<scope>.md`** — informe de
   la auditoría: bugs revisados, resueltos, persistentes y pendientes críticos/graves.
   Nunca se sobrescribe.
