# Walkthrough — Fase B6: /sprint y /release (cierra v1.2)

**Fecha y hora:** 2026-06-23 03:30 | **Agente:** loop/pm (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S3 (v1.2) | **Versión:** 1.1 → 1.2

---

## Resumen ejecutivo
Se completaron los comandos de gestión `/sprint` y `/release` en la capa agéntica, con
sus skills, agentes PM, plantillas y un CHANGELOG. Verificado con un test de estructura
agéntica. Cierra la v1.2.

## Cambios realizados
- **Comandos**: `agentic/commands/sprint.md`, `release.md`.
- **Skills**: `sprint-manager`, `release-manager`.
- **Agentes**: nueva categoría `agents/pm/` (sprint-manager, release-manager) + README.
- **Plantillas**: `sprint.template.md`, `release-notes.template.md`.
- **Changelog**: `docs/CHANGELOG.md` (con la entrada 1.0.0) — lo mantiene `/release`.
- **Carpeta** `logs/sprints/` (planes de sprint).
- **INDEX.md** (sección pm + generadores), `commands/README.md` actualizados.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **93/93 PASS** (+6 unit `AgenticStructureTest`:
  AGENTS.md, comandos clave con front-matter, skills de gestión, agentes pm, plantillas,
  metodología).
- Conteo capa agéntica: **10 comandos · 19 skills · 18 agentes · 7 plantillas**.

## Decisiones de diseño
- `/release` no publica con tests en rojo ni commitea/pushea sin permiso (alineado con
  la preferencia del usuario sobre operaciones sensibles).
- Test de estructura agéntica: verifica que la capa agéntica (markdown) no se rompa,
  cumpliendo la cadencia de "tests por fase" también en fases agénticas.

## Estado
**v1.2 (Aceleradores) COMPLETA**: B1 generador, B2 rollback, B3 búsqueda+paginación,
B4 settings, B5 auditoría, B6 sprint/release. Siguiente: **Track C (v1.3)** — empieza
por la capa API REST (C1).

## Referencias
- `agentic/commands/{sprint,release}.md`, `agentic/skills/{sprint,release}-manager/`,
  `tests/unit/AgenticStructureTest.php`.
