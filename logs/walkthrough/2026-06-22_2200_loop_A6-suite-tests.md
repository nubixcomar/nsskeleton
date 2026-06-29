# Walkthrough — Fase A6: suite de tests + roadmap v1.1–v1.4

**Fecha y hora:** 2026-06-22 22:00 | **Agente:** loop/tester (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S2 | **Versión:** 1.0.0 → camino a 1.1

---

## Resumen ejecutivo
Se cargó el roadmap post-1.0.0 (tracks A→B→C→D, 22 fases) y se construyó la fundación
de testing: un runner propio sin dependencias y los primeros tests versionados,
portando todas las verificaciones que veníamos haciendo ad-hoc. 37/37 tests en verde.

## Cambios realizados
- **Roadmap** (`docs/roadmap.md`): nuevas versiones v1.1 (endurecimiento), v1.2
  (aceleradores), v1.3 (extendidas), v1.4 (onboarding) con 22 fases. A6 (tests) primero.
- **Runner**: `tests/run.php` + `tests/bootstrap.php` (helpers `it`, `group`,
  `assertTrue/False/Equals/Null/NotNull/Contains/Count`; soporta `skip`).
- **Tests**:
  - `unit/CronExpressionTest` (11), `unit/FileManagerTest` (14, incl. anti-traversal),
    `unit/ChartsTest` (5), `unit/ConnectorsTest` (6: SMTP/IA/HTTP error-paths).
  - `feature/DatabaseTest` (2: conexión + dump; se SKIPpea sin MySQL).
- **Comando** `/test` (capa agéntica) + `tests/README.md`.

## Verificación
- `php tests/run.php` → **PASS: 37 · FAIL: 0 · SKIP: 0** · exit code 0.
- Los 2 feature tests corrieron contra MySQL 3307 (no skip).

## Decisiones de diseño
- Runner propio (sin PHPUnit) para no agregar dependencias — coherente con el stack.
- `unit/` no requiere DB; `feature/` se auto-omite si no hay MySQL (portable).
- Definición de Hecho actualizada de facto: cada fase agrega sus tests antes de cerrarse.

## Próximas fases (en el roadmap, listas para el loop)
- **A1** cifrado de credenciales · **A2** hardening login · **A3** reset password ·
  **A4** headers + error pages · **A5** assets locales · luego tracks B, C, D.

## Cómo continuar
Las 21 fases restantes están en `docs/roadmap.md`. Lanzá `/loop` para ejecutarlas
autónomamente (cada una: build + tests versionados + verificación + walkthrough).

## Referencias
- `tests/`, `docs/roadmap.md`, `agentic/commands/test.md`.
