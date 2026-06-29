# app-agentic/agents/ — Agentes del proyecto

Agentes específicos de tu sistema. **Mismo formato** que los del core
([`../../agentic/agents/README.md`](../../agentic/agents/README.md)):

```markdown
---
name: mi-agente
aliases: [alias1]
category: <tu-categoria>
skill: mi-skill            # → ../skills/mi-skill/SKILL.md (de app-agentic)
model_hint: opus
---

Cuándo usar este agente. SIEMPRE cumple `agentic/methodology/`.
```

## Override por nombre
Si creás un agente con el **mismo `name`** que uno del core (`agentic/agents/`), este
**lo reemplaza** para tu proyecto. Usalo para ajustar el comportamiento de un agente core
sin editar el archivo del core (así el update queda limpio).
