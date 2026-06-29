---
name: sprint
usage: /sprint <open|status|close> [Sn] [objetivo]
spawns: [sprint-manager]
---

## Qué hace
Gestiona los sprints de desarrollo (ver [`../methodology/sprints.md`](../methodology/sprints.md)).

## Subcomandos
- `/sprint open <Sn> "<objetivo>"` — abre un sprint: crea `logs/sprints/<Sn>.md` (plan)
  a partir de [`../templates/sprint.template.md`](../templates/sprint.template.md) y
  marca en `docs/roadmap.md` los ítems elegidos con su `Sprint`.
- `/sprint status` — muestra el sprint activo: ítems, estados y pendientes (lee el
  roadmap y el plan del sprint).
- `/sprint close <Sn>` — cierra el sprint: verifica la Definición de Hecho de cada
  ítem, actualiza estados en `docs/roadmap.md`, agrega la retro al plan y resume en
  `logs/features-resume.md`.

## Proceso
1. Invoca el agente `sprint-manager`.
2. Mantiene el plan del sprint y el estado del roadmap sincronizados.
3. Cumple la metodología (logging + Definición de Hecho).

## Ejemplo
`/sprint open S4 "API REST + RBAC"`
