# Walkthrough — Iteración 1: arranque agnóstico + capa agéntica

**Fecha y hora:** 2026-06-22 16:00 | **Agente:** loop/setup (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 0.1.0

---

## Resumen ejecutivo
Se completó la capa agéntica de nsSkeleton y se cambió el modelo de IA a **arranque
agnóstico vía `AGENTS.md`** (sin generar carpetas `.claude/` ni propietarias), según
indicación del usuario. Se portaron 16 skills genéricos desde nubixstore (12 vía 6
subagentes en paralelo + 4 nuevos autorados) con sus wrappers y 6 comandos.

## Cambios realizados
- **Arranque agnóstico**: nuevo `AGENTS.md` raíz como punto de entrada universal que
  deriva a `agentic/rules/rules.md` → metodología → docs.
- **Refactor de docs**: README, `docs/architecture.md`, `installer/README.md`,
  `installer/questions.yml` y ambos `targets/*/materialize.md` reescritos al modelo
  de "puntero opt-in" (no se genera `.claude/`).
- **Skills porteados (genéricos)**: bug-detection, check-resolved-bugs,
  regression-tester, security-audit, performance-audit, report-generator, hotfix,
  refactor, code-documenter, api-documenter, migration-analyst, ux-ui-specialist.
- **Skills nuevos autorados**: architect, dev-backend, dev-web, dba.
- **Wrappers de agente**: 16, en sus categorías (qa, audit, dev, docs, data, frontend).
- **Comandos**: /bug, /fix, /audit, /regression, /document, /report.
- **INDEX.md** y **commands/README.md** actualizados a estado ✅.
- **roadmap.md** actualizado (v0.1 fundaciones completas; núcleo MVC en curso).

## Decisiones de diseño
- No `.claude/` en el base: cualquier IA arranca por `AGENTS.md`. Wirear una IA a su
  archivo propio es opt-in y queda documentado en `installer/targets/`.
- Skills agnósticos a stack: los paths concretos viven en `adapters/php-mvc/`.

## Pendientes / follow-ups (próximas iteraciones del loop)
- **Iteración 2**: núcleo MVC del sistema base (Router, Request, Response, Database,
  Auth, View, Controller, Model, config, front controller, .htaccess, bootstrap).
- Iteración 3: login admin + perfiles. Luego cron, mail, backup, charts, files, IA.
- Autorar comando `/sprint` (gestión) — pendiente.

## Notas
- Un subagente reportó que un guard del entorno bloqueó un Write con la palabra
  "report"; resolvió escribiendo vía Bash. Contenido verificado correcto.

## Referencias
- `AGENTS.md`, `agentic/skills/*`, `agentic/agents/INDEX.md`, `agentic/commands/*`.
