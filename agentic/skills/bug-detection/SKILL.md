---
name: bug-detection
summary: Detecta y documenta bugs mediante análisis estático, sin modificar código.
generic: true
---

## Rol

Especialista senior en testing, QA y depuración. Analiza estáticamente el código
(controladores, modelos, servicios, vistas, endpoints) para **detectar, clasificar
y documentar** bugs y problemas potenciales. Es una tarea **continua e incremental**:
puede ejecutarse varias veces al día y cada corrida alimenta el historial global.

## Entrada

El scope a analizar (un módulo, un archivo, una carpeta o un conjunto de rutas).
Si no se indica scope, se asume el módulo o área de trabajo en curso.

> Los paths concretos del stack (ubicación de controladores, modelos, servicios,
> front controller, etc.) viven en el adapter del proyecto —
> [`../../adapters/php-mvc/conventions.md`](../../adapters/php-mvc/conventions.md)—,
> no en este skill.

## Tarea

0. **Skip list (paso previo obligatorio).** Leer la sección *skip list* de
   `logs/bugs-resume.md`. Todo archivo allí listado se ignora por completo: no se
   analiza, no se reporta, no se menciona en el walkthrough. Si el scope contiene
   solo archivos excluidos, finalizar sin hallazgos.
1. **Lectura desde disco (obligatoria).** Releer cada archivo del scope **ahora**
   con la herramienta de lectura, aunque ya se haya leído antes en la sesión. El
   código pudo cambiar entre invocaciones; analizar contenido cacheado genera
   falsos positivos y negativos. **Leer primero, analizar después. Sin excepción.**
2. **Consultar bugs conocidos.** Antes de reportar, revisar en `logs/bugs-resume.md`
   las tablas de *Bugs activos* (OPEN/WIP) e *Historial* (DONE/DROP) y la sección de
   bugs conocidos. No duplicar ni re-reportar lo que ya figura allí.
3. **Analizar** estáticamente el código y los flujos lógicos del scope.
4. **Detectar** los patrones prohibidos (abajo) y cualquier otro bug, fallo lógico,
   error de validación o de tipos.
5. **Clasificar** cada hallazgo por prioridad (ver Reglas).
6. **Verificación proactiva de resueltos.** Si un bug listado como OPEN/WIP ya no
   existe en el código actual, moverlo en `logs/bugs-resume.md` de *Bugs activos*
   al *Historial* (DONE) con una breve nota de resolución.
7. **Documentar** los hallazgos (ver Salida).

## Reglas

- **PROHIBIDO MODIFICAR CÓDIGO.** Esta tarea es estrictamente de revisión y
  documentación. No se escribe, refactoriza ni corrige ningún archivo de la app.
- **Leer comentarios y contexto cercano antes de reportar.** Mucho de lo que parece
  un bug es una regla de negocio intencional documentada. Verificarlo para evitar
  falsos positivos.
- **Prioridades:** `CRÍTICO` > `GRAVE` > `LEVE` > `MEJORA`. Definiciones en
  [`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md).
- **IDs:** secuenciales `BUG-NNN`, sin reutilizar (ver bug-tracking.md). Verificar el
  último ID usado para continuar la secuencia.
- **No duplicidad:** si el bug ya existe, no agregar otra fila; a lo sumo actualizar
  estado o detalle.
- **Redactar en español**, técnico y preciso, apuntando a archivo y línea exactos,
  explicando la causa subyacente.

### Patrones prohibidos (detección obligatoria)

Estos patrones SIEMPRE se reportan cuando aparecen en código de producción. La lista
genérica para PHP está en
[`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md):

1. **Debug/dump activo** — `var_dump`, `print_r`, `dd()`, `debug()` u otra impresión
   de depuración **sin comentar**. Filtra info y/o corrompe la respuesta. → **GRAVE**.
   (Si está comentado, no se reporta.)
2. **Input pisado por literal** — leer el body real del request (ej. `php://input`,
   datos de POST) y luego sobrescribirlo con un string/JSON hardcodeado. El endpoint
   procesará siempre el payload falso. → **CRÍTICO** si pisa input real; **GRAVE** si
   es un literal aislado. (Fixtures/tests/config estática no se reportan.)
3. **Respuesta AJAX/JSON incompleta** — `render`/`exit` (o su equivalente del stack)
   **comentado** en endpoints que deben responder JSON: el ciclo no cierra bien y
   puede devolver HTML o response incompleto. → **GRAVE**.
4. **Falta de `return`/`exit` tras render** — la ejecución continúa y accede a
   variables inválidas o duplica salida. → **GRAVE**.
5. **SQL concatenado con input** — concatenación de input del usuario en una query
   sin binding/escape. Riesgo de inyección. → **CRÍTICO**.

## Salida

Registrar siempre, según
[`../../methodology/logging.md`](../../methodology/logging.md) y
[`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md):

1. **`logs/bugs-resume.md`** — actualizar el archivo maestro: alta de los bugs nuevos
   en *Bugs activos*, reordenamiento por prioridad, estadísticas, rankings de archivos
   y firma del agente en el encabezado.
2. **`logs/bug-detection.log`** — *append* de cada bug detectado (formato
   detección: timestamp, ID, prioridad, módulo, archivo:línea, detalle, impacto).
3. **`logs/walkthrough/YYYY-MM-DD_HHMM_bug-detection_<scope>.md`** — informe de la
   sesión, con hallazgos agrupados por prioridad. Nunca se sobrescribe.
