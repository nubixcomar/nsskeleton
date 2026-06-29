# Walkthrough — Fase F2: RBAC editable + permisos por usuario

**Fecha y hora:** 2026-06-23 17:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S8 (v1.7) | **Versión:** 1.7

---

## Resumen ejecutivo
La matriz de roles/permisos ahora es editable desde el panel y se pueden definir overrides
por usuario (permitir/denegar) que pesan sobre el rol.

## Cambios realizados
- **`config/permissions.php`**: catálogo de permisos (clave => etiqueta).
- **`Rbac`**: `catalog()`, override de rol persistido en settings (`rbac_roles`, JSON),
  `setRolePermissions()`, overrides por usuario en tabla `user_permissions`
  (`userOverrides`/`setUserPermission`), y `can()` con **precedencia override-usuario
  (deny/allow) > rol**. `superadmin` siempre tiene todo.
- **Migración** `user_permissions` (user_id, permission, effect, único).
- **`RoleController`**: matriz editable (checkboxes por rol×permiso) + guardado.
- **`UserPermissionController`**: editor tri-estado por usuario (heredar/permitir/denegar)
  + link "Permisos" en el listado de usuarios.

## Verificación
- `php -l` OK.
- **Suite**: **188/188 PASS** (+3 feature `RbacOverrides`: override de rol cambia permisos;
  deny de usuario bloquea aunque el rol lo permita; allow otorga aunque el rol no lo dé).
- **E2E (MySQL 3307)**: la matriz muestra checkboxes por rol; guardar viewer con
  `files.manage` → `Rbac::permissionsFor('viewer')` lo incluye; la página de permisos por
  usuario renderiza los selects tri-estado; link "Permisos" presente. (Override de prueba
  limpiado.)

## Pendientes / follow-ups
- **F3** Notificaciones in-app (campanita) + bandeja — siguiente.

## Referencias
- `system/config/permissions.php`, `system/app/Services/Rbac.php`,
  `system/app/Controllers/Admin/{RoleController,UserPermissionController}.php`,
  `tests/feature/RbacOverridesTest.php`.
