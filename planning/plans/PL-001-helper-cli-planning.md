---
code: PL-001
title: Helper CLI de planning (lint / reindex / next / init / review)
status: ✅ Completado
brief: —
rm: RM-022
origin: mejora-técnica
exception: sin brief (herramienta interna, tamaño M técnico); plan escrito junto con la implementación por ser el bootstrap de la metodología
size: M
replaces: —
planned_with: claude-opus-5-5 (esfuerzo alto)
execution_hint: estándar / esfuerzo medio
loop_ready: true
created: 2026-11-07
updated: 2026-11-07
---

# PL-001 — Helper CLI de planning (lint / reindex / next / init / review)

## 1. Resumen
Soporte mecánico y determinístico para `/roadmap`, `/brief` y `/plan`: un servicio sin DB que
lee el front-matter de `planning/**`, valida coherencia (códigos, estados, vínculos, fechas,
propagación, índices), regenera los `INDEX.md`, calcula el próximo código libre y emite las
sugerencias de próxima fase. Evita que los índices se desalineen en loops agénticos largos.

## 2. Fuentes y trazabilidad
- Brief: — (excepción, ver front-matter) · Roadmap: RM-022
| Necesidad | Se cubre en | Se verifica con |
|-----------|-------------|-----------------|
| Índices que no se desalinean | F3 | test "reindex es idempotente" |
| Detectar estructura rota (duplicados, vínculos, estados) | F2 | test "lint detecta…" |
| Sugerir la próxima fase | F2 | test "fixture coherente" (sugerencia `/plan run`) |
| Código libre sin colisiones | F3 | test "nextCode…" |

## 3. Contexto técnico actual
- Metodología y formato: `agentic/methodology/roadmap-brief-plan.md`, scaffold en
  `agentic/templates/planning/` (front-matter `code/title/status/...`, fases `### F<n> — … · estado: …`).
- Patrón de consola del core: `system/console/*.php` (guard CLI + autoload). Servicios en
  `system/app/Services/` (namespace `App\Services`). Tests sin DB en `tests/unit/`.
- No hay parser YAML en el core (sin dependencias) → parser mínimo propio.

## 4. Diseño
### 4.1 Modelo de datos
Sin DB. Ítem cargado: `{type, code, file, title, status (canónico|null), rawStatus, meta, phases?, blocking?}`.
### 4.2 Contratos
- `App\Services\Planning(projectRoot)`: `init()`, `load()`, `lint(?today)`, `reindex()`,
  `nextCode(type)`, estáticos `parseFrontMatter`, `normalizeStatus`, `parsePhases`,
  `countBlockingQuestions`.
- CLI `php system/console/planning.php <init|lint|reindex|sync|review|next|list> [rm|br|pl] [--json] [--strict] [--root=] [--today=]`.
  Exit: 0 ok · 1 errores (o warnings con `--strict`) · 2 uso/planning inexistente.
### 4.3 Flujos
- `lint`: errores (estructura) → warnings (desalineado/propagación/fechas) → sugerencias.
- `reindex`: conserva el texto de cabecera del INDEX, reemplaza la tabla desde `| Código`,
  actualiza "Próximo código libre". Vínculos del índice = unión de ambos lados.

## 5. Decisiones y alternativas descartadas
| Decisión | Alternativas | Motivo |
|----------|--------------|--------|
| Parser YAML mínimo | symfony/yaml | El core no usa dependencias de Composer. |
| Estados por etiqueta sin emoji | match exacto | Tolerar `En curso` vs `🟡 En curso` y variantes de emoji. |
| reindex no edita los archivos de ítem | "--fix" sobre archivos | El archivo es del agente/humano; solo el índice es derivado. |
| Helper en el adapter (PHP) | en `agentic/` | `agentic/` es agnóstico; el helper es del stack php-mvc. |

## 6. Fases

### F1 — Parser y carga · estado: ✅ hecha
- **Archivos:** `system/app/Services/Planning.php` (crear)
- **Pseudocódigo:** `parseFrontMatter` (bloque `---`, `clave: valor`, listas `[..]`, `—`→null,
  comentarios `  #`); `load()` recorre `planning/{roadmap,briefs,plans}/*.md` salvo INDEX/README,
  valida nombre `PREFIJO-NNN-slug.md`, code del front-matter, duplicados.
- **Criterios:** [x] listas, nulos y comentarios parseados · [x] duplicados detectados
- **Verificación:** `php tests/run.php` (grupo Planning)

### F2 — Lint y sugerencias · estado: ✅ hecha
- **Depende de:** F1
- **Pseudocódigo:** estados válidos por tipo; vínculos existen y con prefijo correcto; vínculos en
  un solo sentido; fechas YYYY-MM-DD, inicio ≤ entrega, entrega vencida; plan activo sin fases,
  completado con fases abiertas, L/XL sin brief ni exception; propagación PL→RM/BR; brief Listo con
  bloqueantes; índice vs archivos; sugerencias de próxima fase.
- **Criterios:** [x] errores, warnings y sugerencias cubiertos por tests
- **Verificación:** `php tests/run.php`

### F3 — Reindex, next e init · estado: ✅ hecha
- **Depende de:** F1
- **Criterios:** [x] tabla regenerada con links · [x] idempotente · [x] next = max(archivos, índice)+1 · [x] init no pisa
- **Verificación:** `php tests/run.php`

### F4 — CLI · estado: ✅ hecha
- **Archivos:** `system/console/planning.php` (crear)
- **Criterios:** [x] subcomandos + `--json` + códigos de salida
- **Verificación:** `php system/console/planning.php sync`

### F5 — Tests · estado: ✅ hecha
- **Archivos:** `tests/unit/PlanningTest.php` (crear) — 11 tests, incluido "el planning/ del repo no tiene errores".
- **Verificación:** `php tests/run.php`

### F6 — Integración en docs agénticos · estado: ✅ hecha
- **Archivos:** `agentic/adapters/php-mvc/conventions.md`, `agentic/commands/{roadmap,brief,plan}.md`,
  `agentic/skills/roadmap-manager/SKILL.md`, `agentic/knowledge/core-manual.md` (referencia al helper).
- **Criterios:** [x] los comandos indican usar el helper del adapter si existe (sin acoplar `agentic/` a PHP)
- **Verificación:** chequeo de links relativos.

## 7. Plan de pruebas
`tests/unit/PlanningTest.php`: parser, estados, fases, preguntas bloqueantes, init, next, fixture
coherente sin warnings + idempotencia, errores estructurales, propagación/vencimientos/vínculos,
índice desalineado, y lint del `planning/` real del repo.

## 8. Migraciones y rollback
Sin datos. Rollback = borrar el servicio, la CLI y el test.

## 9. Impacto y aislamiento
Archivos nuevos; no modifica código existente. Los comandos funcionan igual sin el helper (lo usan si existe).

## 10. Riesgos técnicos
| Riesgo | Probabilidad | Mitigación |
|--------|--------------|------------|
| Front-matter fuera del subconjunto soportado | media | Formato fijado por las plantillas; lint lo reporta. |
| Emojis con/sin selector de variación | media | Comparación por etiqueta sin emoji. |

## 11. Fuera de alcance
UI de admin; edición automática de archivos de ítem.

## 12. Recomendación de ejecución
- Modelo estándar / esfuerzo medio. Apto para loop: sí. Sin fases paralelizables relevantes.

## 13. Revisión del plan
| Fecha | Revisor (agente/modelo) | Hallazgos | Resolución |
|-------|-------------------------|-----------|------------|
| 2026-11-07 | — | Sin revisión independiente (bootstrap) | Pendiente opcional: `/plan review PL-001` en otra sesión |

## 14. Registro de ejecución
| Fase | Fecha | Agente | Modelo | Resultado | Walkthrough |
|------|-------|--------|--------|-----------|-------------|
| F1–F6 | 2026-11-07 | core-dev | claude-opus-5-5 | ✅ tests verdes | `logs/walkthrough/2026-11-07_*_core-dev_planning-cli.md` |

## 15. Desvíos
- Plan escrito durante la implementación (no antes) por ser el bootstrap — ver `exception`.

## 16. Cierre
- [x] Todas las fases ✅.
- [x] Necesidades trazadas verificadas por tests.
- [x] Tests verdes (grupo Planning).
- [x] RM-022 actualizado.
- [x] DoD de `sprints.md` (walkthrough + log).
