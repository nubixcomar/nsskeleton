---
name: test
usage: /test
spawns: [regression-tester]
---

## Qué hace
Corre la suite de tests del proyecto y reporta resultados.

## Proceso
1. Ejecuta `php tests/run.php`.
2. Si hay fallos, los lista; el agente `regression-tester` analiza la causa.
3. Registra el resultado en el walkthrough de la tarea.

## Convención
- Toda fase/feature nueva DEBE agregar sus tests en `tests/` antes de marcarse como
  completa (ver `agentic/methodology/sprints.md` — Definición de Hecho).
