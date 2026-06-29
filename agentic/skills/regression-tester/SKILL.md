---
name: regression-tester
summary: Verifica que un cambio reciente no haya roto funciones existentes; analiza impacto, dependencias y casos de prueba.
generic: true
---

## Rol

Especialista en testing de regresión. El objetivo es verificar que un fix, feature
o refactor reciente **no haya roto funcionalidades previamente estables**. No se
buscan bugs nuevos del módulo modificado: el foco es **el impacto colateral del
cambio** sobre el resto del sistema.

## Entrada

- Archivo(s) o módulo(s) modificados recientemente (el scope del cambio).
- Opcionalmente: el bug o feature que motivó el cambio.

Si no se indica el scope, se asume el último cambio de la sesión en curso.

> Los paths concretos del stack (ubicación de controladores, modelos, servicios,
> vistas, rutas, front controller, etc.) viven en el adapter del proyecto —
> [`../../adapters/php-mvc/conventions.md`](../../adapters/php-mvc/conventions.md)—,
> no en este skill.

## Tarea

1. **Lectura desde disco (obligatoria).** Releer **ahora** los archivos modificados
   y los archivos que dependen de ellos, aunque ya se hayan leído en la sesión. El
   código pudo cambiar; analizar contenido cacheado genera falsos positivos.
2. **Mapear el impacto.** A partir del cambio, identificar:
   - Funciones, clases o endpoints que **dependen** del código modificado (quién lo
     llama, quién lo importa, qué firma/contrato consume).
   - Flujos del sistema que atraviesan ese código.
   - Acoplamientos no evidentes (estado global, sesión, caché, esquema de BD,
     formato de respuesta JSON/HTML, eventos, hooks).
3. **Consultar bugs conocidos.** Revisar `logs/bugs-resume.md` para no re-reportar lo
   ya listado (ver Reglas).
4. **Definir casos de prueba.** Para cada flujo en riesgo, describir el caso de
   prueba y el **resultado esperado** (entrada → salida esperada). Indicar cómo se
   verificaría (manual o automatizado) sin ejecutar cambios.
5. **Emitir un veredicto** por flujo y uno global:
   - ✅ **Sin regresión detectada**
   - ⚠️ **Riesgo potencial** (requiere prueba manual / no determinable estáticamente)
   - ❌ **Regresión confirmada**

## Reglas

- **PROHIBIDO MODIFICAR CÓDIGO.** Esta tarea es de análisis y documentación.
- **Foco en regresión**, no en bugs propios del módulo modificado (eso es trabajo del
  skill `bug-detection`).
- **No duplicar** bugs ya listados en `logs/bugs-resume.md` (tablas de *Bugs activos*
  e *Historial*).
- Una regresión confirmada (❌) **es un bug nuevo**: se registra siguiendo
  [`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md)
  (ID `BUG-NNN` secuencial, prioridad `CRÍTICO`/`GRAVE`/`LEVE`).
- **Redactar en español**, técnico y preciso, apuntando a archivo y línea, explicando
  qué dependencia se rompe y por qué.

## Salida

Registrar siempre, según
[`../../methodology/logging.md`](../../methodology/logging.md) y
[`../../methodology/bug-tracking.md`](../../methodology/bug-tracking.md):

1. **Si hay regresión confirmada (❌):** alta del/los bug(s) en `logs/bugs-resume.md`
   (*Bugs activos*) y *append* en `logs/bug-detection.log` (formato detección:
   timestamp, ID, prioridad, módulo, archivo:línea, detalle, impacto).
2. **`logs/walkthrough/YYYY-MM-DD_HHMM_regression-tester_<scope>.md`** — informe de la
   sesión: cambio analizado, mapa de impacto/dependencias, casos de prueba con
   resultado esperado, y veredicto por flujo y global. Nunca se sobrescribe.
3. **`logs/regression-tester.log`** — *append* de 1 línea con el veredicto global
   (`[YYYY-MM-DD HH:MM:SS] [TEST] <scope> | <veredicto> | modelo: <modelo>`).
