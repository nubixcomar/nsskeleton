# Índice de agentes

Catálogo de agentes disponibles. Cada agente es un **wrapper fino** que invoca un
skill de `../skills/{nombre}/SKILL.md`. Estados: ✅ implementado · 🧩 planificado.

## qa/ — Testing y detección
| Agente                | Alias              | Propósito                                            | Estado |
|-----------------------|--------------------|------------------------------------------------------|--------|
| `bug-detection`       | `qa`, `bugs`       | Detecta y documenta bugs sin modificar código.       | ✅     |
| `check-resolved-bugs` | `check-resolved`   | Verifica que los bugs marcados resueltos lo estén.   | ✅     |
| `regression-tester`   | `regression`       | Verifica que un cambio no rompió lo existente.       | ✅     |

## audit/ — Auditorías
| Agente             | Alias             | Propósito                                          | Estado |
|--------------------|-------------------|----------------------------------------------------|--------|
| `security-audit`   | `security`        | Auditoría OWASP Top 10 + auth + inyecciones.       | ✅     |
| `performance-audit`| `perf`            | Detecta N+1, queries lentas, cuellos de botella.   | ✅     |
| `report-generator` | `report`          | Genera informes ejecutivos HTML/PDF.               | ✅     |

## dev/ — Desarrollo
| Agente       | Alias       | Propósito                                       | Estado |
|--------------|-------------|-------------------------------------------------|--------|
| `architect`  | `arch`      | Diseña arquitectura, patrones (MVC), decisiones.| ✅     |
| `dev-backend`| `backend`   | Implementa backend (controladores, modelos, DB).| ✅     |
| `dev-web`    | `web`       | Implementa frontend/web (vistas, JS, CSS).      | ✅     |
| `hotfix`     | `fix`       | Corrección quirúrgica de bugs.                  | ✅     |
| `refactor`   | `refactor`  | Refactorización conservadora (sin cambio func.).| ✅     |

## docs/ — Documentación
| Agente           | Alias        | Propósito                                  | Estado |
|------------------|--------------|--------------------------------------------|--------|
| `code-documenter`| `docs`       | Documenta código existente.                | ✅     |
| `api-documenter` | `api-docs`   | Documenta endpoints/APIs (OpenAPI).        | ✅     |

## data/ — Datos y BD
| Agente             | Alias        | Propósito                                  | Estado |
|--------------------|--------------|--------------------------------------------|--------|
| `migration-analyst`| `migration`  | Evalúa migraciones SQL antes de ejecutar.  | ✅     |
| `dba`              | `db`         | Diseña/optimiza esquema y queries.         | ✅     |

## frontend/ — UX/UI
| Agente            | Alias            | Propósito                               | Estado |
|-------------------|------------------|-----------------------------------------|--------|
| `ux-ui-specialist`| `ux`, `ui`       | Interfaces responsivas, accesibilidad.  | ✅     |

## integrations/ — Integraciones con APIs externas
| Agente               | Alias                  | Propósito                                                  | Estado |
|----------------------|------------------------|------------------------------------------------------------|--------|
| `ecommerce-integrator`| `ecommerce`, `tienda` | Integra con APIs de tiendas (Shopify/TN/Woo/Magento/ns).   | ✅     |
| `nubixstore-api`     | `nubixstore`, `ns-api` | Consume/integra la API de nubixstore; mantiene su manual.  | ✅     |

## pm/ — Gestión de proyecto
| Agente            | Alias       | Propósito                                  | Estado |
|-------------------|-------------|--------------------------------------------|--------|
| `sprint-manager`  | `sprint`        | Abre/monitorea/cierra sprints.                       | ✅     |
| `release-manager` | `release`       | Publica versiones (tests, changelog, pkg core+app).  | ✅     |
| `core-updater`    | `actualizar-core` | Actualiza el core sin pisar la app (dry-run/apply/rollback). | ✅     |

## Generadores
| Agente             | Alias        | Propósito                                  | Estado |
|--------------------|--------------|--------------------------------------------|--------|
| `module-generator` | `nuevo-modulo`| Scaffolda módulos CRUD completos.         | ✅     |
