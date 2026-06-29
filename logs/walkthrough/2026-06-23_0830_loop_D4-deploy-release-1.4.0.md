# Walkthrough — Fase D4: deploy + Release 1.4.0 (cierre del roadmap)

**Fecha y hora:** 2026-06-23 08:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S5 (v1.4) | **Versión:** 1.3 → **1.4.0**

---

## Resumen ejecutivo
Se implementó el deploy por FTP/git (dry-run por defecto, ejecución real opt-in) y, con
ello, se completó **todo el roadmap v1.1→v1.4**. Se marcó el release **1.4.0**.

## Cambios de D4
- **`App\Services\Deployer`**: `filesToDeploy` (exclusiones de secretos/runtime/deps/
  binarios), `ftpDeploy` (sube vía FTP), `gitCommands`, `config` (desde `.env`).
- **CLI** `system/console/deploy.php`: `ftp`/`git`, **dry-run por defecto**, `--run` para
  ejecutar (sin `--run` no hay acción saliente).
- **Capa agéntica**: skill `deployer` + comando `/deploy`.

## Release 1.4.0
- `VERSION` → 1.4.0; `docs/CHANGELOG.md` con el detalle de v1.1→v1.4; README actualizado.
- Paquete regenerado: `landing/downloads/nsSkeleton-1.4.0.zip` (350 archivos); landing a 1.4.0.

## Verificación
- `php -l`: **165 archivos** sin errores.
- **Suite**: `php tests/run.php` → **137/137 PASS**.
- **Smoke**: `/` 200, `/health` 200, `/admin/login` 200, `/admin` 302 (guard).
- Deploy dry-run: git muestra comandos; FTP lista 350 archivos (sin acción real sin `--run`).

## Estado final — ROADMAP COMPLETO (22/22 fases)
- **v1.1 Endurecimiento** ✅ · **v1.2 Aceleradores** ✅ · **v1.3 Capacidades extendidas** ✅
  · **v1.4 Onboarding y ejemplos** ✅.
- Suite creció de 0 → **137 tests** versionados (cada fase aportó los suyos).

## Cierre del loop
Todas las fases del roadmap están completas → el loop dinámico se detiene (no se
reprograma). Pendiente del humano: inicializar git y, si desea, ejecutar el deploy real.

## Referencias
- `system/app/Services/Deployer.php`, `system/console/deploy.php`,
  `docs/CHANGELOG.md`, `tests/unit/DeployerTest.php`.
