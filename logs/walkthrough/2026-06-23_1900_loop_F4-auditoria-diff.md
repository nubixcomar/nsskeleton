# Walkthrough — Fase F4: auditoría con diff (cierra Track F / v1.7.0)

**Fecha y hora:** 2026-06-23 19:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S8 (v1.7) | **Versión:** 1.6 → **1.7.0**

---

## Resumen ejecutivo
La auditoría ahora puede registrar el detalle de los cambios (antes/después) y el visor los
muestra campo por campo. Cierra el Track F.

## Cambios realizados
- **`Audit::diff`** (pura): compara dos arrays y devuelve sólo los campos modificados
  (ignora `password`/`created_at`/`updated_at`).
- **`Audit::logChange`**: guarda el diff como JSON en la nueva columna `changes`.
- **Migración**: `changes TEXT` en `audit_log`.
- **`AdminUserController::update`**: registra el diff (sin exponer el hash de contraseña).
- **Visor de auditoría**: renderiza el diff con el valor viejo tachado → el nuevo.

## Verificación
- `php -l` OK.
- **Suite**: **194/194 PASS** (+4 unit `AuditDiff`: detecta cambios, ignora password/
  timestamps, sin cambios → vacío, campos nuevos/ausentes).
- **E2E (MySQL 3307)**: edité un admin de prueba (rol viewer→editor + nombre) → la auditoría
  muestra el diff (viejo tachado, nuevo resaltado). Admin de prueba limpiado.

## Track F — COMPLETO → Release 1.7.0
- F1 2FA ✅ · F2 RBAC editable ✅ · F3 notificaciones ✅ · F4 auditoría diff ✅.
- `VERSION`=1.7.0, `docs/CHANGELOG.md` actualizado.

## Pendientes / follow-ups
- **Track G (v1.8)**: G1 API+ → siguiente.

## Referencias
- `system/app/Services/Audit.php`, `system/app/Controllers/Admin/AdminUserController.php`,
  `system/app/Views/admin/audit/index.php`, `tests/unit/AuditDiffTest.php`.
