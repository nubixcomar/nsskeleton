# app-agentic/ — Capa agéntica del PROYECTO (no del core)

Este árbol es **propiedad del sistema que construís sobre nsSkeleton**. El actualizador de
core **nunca lo toca** (está en `app_paths` de `core-manifest.json`). Acá viven las reglas,
agentes, skills, knowledge, plantillas y docs de módulos **específicos de tu sistema**.

```
app-agentic/
├── rules/
│   └── app-rules.md        Reglas del proyecto (negocio, overrides del core). Se cargan DESPUÉS de core-rules.
├── agents/                 Agentes propios del proyecto (mismo formato que agentic/agents).
├── skills/                 Skills propios del proyecto (mismo formato que agentic/skills).
├── knowledge/              Manuales/dominios del proyecto (mismo formato que agentic/knowledge).
├── templates/              Plantillas propias / overrides de las de core.
└── modules/                Un .md por módulo del sistema (qué hace, contrato, decisiones).
```

## Precedencia (definida en `agentic/rules/rules.md`)
1. Se cargan primero las reglas del **core** (`agentic/rules/core-rules.md`).
2. Luego las del **proyecto** (`app-rules.md`). **En conflicto, gana el proyecto (app > core).**
3. Un agente/skill de `app-agentic/` con el **mismo `name`** que uno de `agentic/` lo
   **sombrea** (override por nombre).

## Relación core ↔ app
- `agentic/` = core, agnóstico, se actualiza con cada versión de nsSkeleton.
- `app-agentic/` = tu sistema, estable, jamás pisado por un update de core.
- No edites archivos de `agentic/`: si necesitás cambiar algo de core, **overridealo acá**
  (regla en `app-rules.md`, agente/skill homónimo, plantilla propia). Así el update queda limpio.
