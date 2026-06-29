---
name: bug
usage: /bug [módulo]
spawns: [bug-detection]
---

## Qué hace
Detecta y documenta bugs en el módulo indicado (o en todo el proyecto si se omite).
**No modifica código.**

## Proceso
1. Invoca el agente `bug-detection`.
2. Aplica los "patrones prohibidos" de [`../methodology/bug-tracking.md`](../methodology/bug-tracking.md).
3. Registra hallazgos en `logs/bugs-resume.md` + `logs/bug-detection.log`.
4. Genera walkthrough en `logs/walkthrough/`.

## Restricciones
- Solo detecta y documenta. Para arreglar, usar `/fix`.
- Respeta la skip list de `logs/bugs-resume.md`.
