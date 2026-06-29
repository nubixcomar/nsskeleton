# Resumen de features implementados

**Última actualización:** 2026-06-22 20:30 | Agente: loop | Modelo: claude-opus-4-8

Histórico acumulativo de features construidos (ver `agentic/methodology/sprints.md`).

## Capa agéntica
| # | Feature                                            | Versión | Fecha       | Estado | Agente | Modelo |
|---|----------------------------------------------------|---------|-------------|--------|--------|--------|
| 1 | Scaffold agéntico (rules, agents, skills, etc.)    | 1.0.0   | 2026-06-22  | ✅     | setup  | opus-4-8 |
| 2 | Metodología (logging, bug-tracking, sprints)       | 1.0.0   | 2026-06-22  | ✅     | setup  | opus-4-8 |
| 3 | Arranque agnóstico `AGENTS.md` (sin `.claude/`)    | 1.0.0   | 2026-06-22  | ✅     | loop   | opus-4-8 |
| 4 | 16 skills genéricos + 16 agentes + 6 comandos      | 1.0.0   | 2026-06-22  | ✅     | loop   | opus-4-8 |
| 5 | Instalador Q&A (`installer/questions.yml`)         | 1.0.0   | 2026-06-22  | ✅     | setup  | opus-4-8 |

## Sistema base
| # | Feature                                            | Versión | Fecha       | Estado | Verificación |
|---|----------------------------------------------------|---------|-------------|--------|--------------|
| 1 | Núcleo MVC (router, request/response, DB, view…)   | 1.0.0   | 2026-06-22  | ✅     | /, /health, 404 |
| 2 | Login admin + gestión de perfiles                  | 1.0.0   | 2026-06-22  | ✅     | render + guard |
| 3 | Cronmaster (programador de tareas)                 | 1.0.0   | 2026-06-22  | ✅     | parser 11/11 |
| 4 | Configuración y envío de emails (SMTP propio)      | 1.0.0   | 2026-06-22  | ✅     | error-path |
| 5 | Backup y restauración (DB + archivos)              | 1.0.0   | 2026-06-22  | ✅     | ZIP real |
| 6 | Librería de gráficos + dashboard (Chart.js)        | 1.0.0   | 2026-06-22  | ✅     | charts/partial |
| 7 | File manager (carpetas/subcarpetas + uploads)      | 1.0.0   | 2026-06-22  | ✅     | 13/13 tests |
| 8 | Conector de IA (OpenAI/Deepseek)                   | 1.0.0   | 2026-06-22  | ✅     | 7/7 tests |

## Release
**v1.0.0 — 2026-06-22.** Primer MVP de nsSkeleton.

## Nota de verificación
Todo el código pasa `php -l`. Los flujos que tocan MySQL fueron **verificados
end-to-end** contra MySQL (puerto 3307): migraciones + seed, login con CSRF/cookie,
dashboard con gráficos, listado/creación (CRUD) y dump real de base (8 tablas).
El envío real de email y la llamada real a la IA requieren credenciales válidas.
