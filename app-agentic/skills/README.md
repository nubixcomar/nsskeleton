# app-agentic/skills/ — Skills del proyecto

Skills específicos de tu sistema. **Mismo formato** que los del core
([`../../agentic/skills/README.md`](../../agentic/skills/README.md)): cada uno en su carpeta
con un `SKILL.md` (Rol · Entrada · Tarea · Reglas · Salida).

```
skills/
└── mi-skill/
    └── SKILL.md
```

Front-matter:
```markdown
---
name: mi-skill
summary: Qué hace en una línea.
generic: false        # del proyecto, no portable
---
```

## Override por nombre
Un skill con el **mismo `name`** que uno del core lo **sombrea** para tu proyecto.
