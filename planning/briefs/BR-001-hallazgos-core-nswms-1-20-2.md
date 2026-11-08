---
code: BR-001
title: Hallazgos de nsWMS sobre el core 1.20.2 (seguridad y robustez)
status: 🧭 Planificado
rm: —
exception: sin RM por decisión del humano (2026-11-08): no es una funcionalidad nueva ni una mejora visible, es hardening/corrección del core
plans: [PL-002]
size: L
requested_by: proyecto derivado nsWMS (informe técnico) · priorizado por el humano
created: 2026-11-08
updated: 2026-11-08
---

# BR-001 — Hallazgos de nsWMS sobre el core 1.20.2 (seguridad y robustez)

> **Origen:** informe técnico del proyecto derivado nsWMS, recibido como artifact
> <https://claude.ai/artifact/2LkSg7ob2wpsJctW8P3DSW> (7/11/2026) porque nsWMS todavía no está
> en nsBus. Por decisión del humano, el informe **es** el brief: acá se transcribe (anexo A) y se
> completa con la verificación hecha contra el código del master (§15, anexo B).

## 1. El problema en una frase
El core 1.20.2 deja que cualquier usuario del backend ejecute comandos en el servidor y cambie
configuración sensible, y además trae fallas de frontera core/app, migraciones y transacciones que
hacen inseguro o engañoso construir proyectos derivados encima.

## 2. Contexto
nsWMS (WMS multitenant para nubixstore y otras plataformas) empezó a codear su primer módulo y,
al verificar cada supuesto de su plan contra el código del core, encontró 5 problemas y 5
observaciones. Es el primer proyecto derivado con usuarios de bajo privilegio y terceros
(tiendas, empleados), así que es el primero en el que la falta de permisos deja de ser teórica.
El triage del master (2026-11-08) confirmó los 5 hallazgos y amplió el crítico.

## 3. Quiénes lo sufren (actores)
| Actor / perfil | Qué hace hoy | Cómo lo afecta el problema | Frecuencia / volumen |
|----------------|--------------|----------------------------|----------------------|
| Usuario de bajo privilegio del backend (`viewer`, `editor`, roles custom) | Entra al panel para tareas acotadas | Puede crear y correr tareas cron con shell, cambiar la API key de IA, ver auditoría y estado del servidor, operar módulos generados | Todo derivado con más de un tipo de usuario |
| Dueño / admin del proyecto derivado | Confía en la matriz de roles del panel | La matriz promete restricciones que el código no aplica | Permanente |
| Usuario desactivado o borrado | — | Conserva la sesión y el acceso hasta que expira | Cada baja de usuario |
| Dev del proyecto derivado | Usa puntos de extensión, `make-module`, migraciones de app, tests propios | Suite en rojo permanente (drift), `rollback` que revierte el core, transacciones que rompen el aislamiento de los tests | Diario |
| Integración nubixstore | Usa `NubixstoreConnector` | Errores de la API tomados como éxito, PUT/DELETE sin autenticar, login por request | Cada llamada |
| Mantenedores del core | Publican releases | Reciben reportes por canales ad hoc; el bug afecta a todos los derivados | Por release |

## 4. Dolores y experiencia actual (as-is)
- Un `viewer` crea `POST /admin/cron` con `command=whoami` y lo ejecuta con `POST /admin/cron/{id}/run`: el servidor corre el comando (NSK-01).
- nsWMS tuvo que mover a sus usuarios de tienda a un guard propio (`wms_users`) para que nunca existan en `admin_users`.
- nsWMS acepta un test en rojo permanente (`CoreBoundaryTest › sin drift`) apenas agrega `routes.app.php` (NSK-02).
- nsWMS prohibió `migrate.php rollback` y `fresh` y revierte con `Migrator::rollback(N, Migrator::appDir())` (NSK-03).
- nsWMS reescribió el cliente de nubixstore sobre `AbstractStoreConnector` (NSK-04) y un helper `Tx::run()` propio (NSK-05).

## 5. Necesidades concretas
- Cuando doy acceso al panel a alguien con un rol limitado, quiero que solo pueda hacer lo que su rol permite, para no darle el servidor sin querer.
- Cuando desactivo o borro un usuario, quiero que pierda el acceso en el próximo request.
- Cuando agrego archivos en los puntos de extensión o genero módulos, quiero que la suite siga verde mientras no toque archivos del core.
- Cuando hago `rollback`, quiero revertir lo último que apliqué (sea core o app), no una migración del core al azar.
- Cuando el core actualiza, quiero que solo me marque conflicto en archivos que el core realmente cambió.

## 6. Resultado deseado (to-be)
Cada ruta del admin exige un permiso explícito (o está declarada como "solo sesión" a propósito),
un controlador nuevo nunca queda abierto por omisión, un usuario inactivo queda afuera, y los
derivados pueden usar todos los puntos de extensión con la suite en verde y comandos de
migración que hacen lo que dicen. Los fixes de nubixstore y transacciones llegan en la versión siguiente.

## 7. Criterios de éxito
- [ ] CS1 — Con un usuario `viewer`, toda ruta `/admin/*` cuyo permiso no tenga el rol responde 403 (cron, IA, estado, búsqueda, módulos, etc.).
- [ ] CS2 — Un controlador admin nuevo sin permiso declarado no queda accesible para `viewer` (default seguro), sin romper a `admin`/`superadmin`.
- [ ] CS3 — Un test recorre todas las rutas `/admin/*` y falla si aparece una ruta sin permiso declarado (o fuera de la lista "solo sesión").
- [ ] CS4 — Un usuario con `active = 0` o borrado pierde el acceso en el request siguiente.
- [ ] CS5 — `Auth::user()` no expone `totp_secret`.
- [ ] CS6 — En un derivado, agregar archivos en los puntos de extensión, módulos generados o tests de app no produce drift; modificar o borrar un archivo del core sí.
- [ ] CS7 — Generar un módulo no modifica archivos del core.
- [ ] CS8 — Un update de core no marca conflicto en archivos que el core no cambió.
- [ ] CS9 — `migrate.php rollback 1` revierte la última migración aplicada (core o app); `fresh` deja core + app reaplicados.
- [ ] CS10 — nsWMS valida los fixes contra su proyecto (ofrecido en el informe).

## 8. Alcance
- **Incluye (1.20.3):** NSK-01 completo (incluida la ampliación del triage: `AiController::saveSettings`, módulos de `make-module`, dashboard/auditoría, búsqueda), observaciones "Guard no revalida `active`" y "`Auth::user()` devuelve `totp_secret`", NSK-02 (más `modules_menu.php` y el ruido de conflictos del updater), NSK-03.
- **No incluye (explícito):** NSK-04 (conector nubixstore) y NSK-05 (transacciones reentrantes) → versión siguiente, brief propio o este mismo con otro plan. Observaciones menores restantes (`maybeDecrypt`, layout faltante, migraciones multi-sentencia) → documentación en la versión siguiente. Flag `cron.allow_shell` → evaluado y diferido (§14). Mover la salida de `make-module` de `system/app/` a `app/` → diferido.

## 9. Reglas de negocio
- `superadmin` siempre puede todo (`*`).
- La matriz de roles editable (`rbac_roles` en settings) sigue siendo la fuente de permisos; no se agregan permisos nuevos al catálogo si se puede evitar (los overrides guardados no los tendrían).
- Ningún proyecto derivado existente debe quedar sin acceso de `admin`/`superadmin` a sus módulos después de actualizar.
- Perfil propio, notificaciones propias y 2FA propio son "solo sesión" (cada usuario sobre sí mismo).

## 10. Escenarios y casos borde
| # | Escenario | Comportamiento esperado |
|---|-----------|-------------------------|
| 1 | `viewer` entra a `/admin/cron/create` | 403 |
| 2 | `viewer` abre `/admin` | Dashboard con KPIs y gráficos; sin auditoría, salud, jobs ni alertas (cada widget exige su permiso) |
| 3 | `editor` usa la búsqueda global | Funciona (tiene `modules.manage`) |
| 4 | `viewer` ve la topbar | El buscador no se muestra |
| 5 | Módulo generado antes de 1.20.3 (controlador sin declaración) | Exige el permiso default (`modules.manage`): `admin`/`editor` siguen entrando, `viewer` no |
| 6 | Controlador de app con `requirePermission('x')` propio y rol custom con `x` sin `modules.manage` | Configurable: default `admin_default_permission` puede setearse a `null` (comportamiento previo) — documentado en notas de actualización |
| 7 | Admin desactiva a un usuario logueado | En el próximo request: logout + login con aviso |
| 8 | Derivado crea `system/config/routes.app.php` | `--check` OK |
| 9 | Derivado edita `system/app/Core/Router.php` | `--check` DRIFT, lista el archivo |
| 10 | Derivado viejo con `modules_menu.php` en su lock | El update no rompe su menú (archivo conservado si lo editó) |
| 11 | `rollback 1` con la última migración de app | Revierte la de app |
| 12 | Dos migraciones aplicadas en el mismo segundo | Se revierte primero la aplicada después (orden de aplicación: core antes que app) |

## 11. Restricciones
- PHP puro, sin dependencias; compatibilidad hacia atrás con la API pública (`requirePermission`, `Migrator::rollback($n, $dir)`, `CoreUpdater::plan`).
- Ninguna migración de esquema.
- Tests de la suite sin DB donde se pueda; los que toquen DB con `itDb`.

## 12. Dependencias e integraciones
- nsWMS (validación de los fixes; hoy fuera de nsBus → respuesta por el humano o artifact).
- Actualizador de core (los derivados reciben 1.20.3 con `core-update.php`).

## 13. Supuestos y riesgos
| Supuesto / riesgo | Impacto si es falso | Cómo validarlo |
|-------------------|---------------------|----------------|
| Los derivados existentes usan roles default (admin/editor tienen `modules.manage`) | Un rol custom pierde acceso a módulos generados | Notas de actualización + config `admin_default_permission` |
| `logs.view` es el permiso adecuado para `/admin/health` | Algún rol que hoy ve Estado deja de verlo | Revisar en el E2E; el menú se alinea |
| Nadie depende de `Migrator::rollback($n)` sin dir = "solo core" | Script de terceros cambia de comportamiento | grep en core (solo `migrate.php`); documentar |

## 14. Alternativas exploradas (creative-explorer)
| Alternativa | Pros | Contras | ¿Se descarta? |
|-------------|------|---------|---------------|
| Deny estricto si el controlador no declara permiso (propuesta del informe) | Máxima seguridad | Rompe todos los módulos generados de derivados existentes (403 para todos salvo superadmin) | Sí → default `modules.manage` configurable |
| Middleware por ruta (permiso en `routes.php`) | Visible en un solo lugar | Requiere cambiar el Router y todas las rutas; los derivados con rutas propias quedarían abiertos | Sí |
| Flag `cron.allow_shell` apagado por defecto | Reduce superficie | Rompe tareas de sistema que son shell (`php system/backup/run.php`); con permisos arreglados, shell queda solo para `cron.manage` | Diferido (follow-up) |
| `--check` sabe de cada punto de extensión (opción A del informe) | Preciso en el master | Lista que hay que mantener; no cubre tests ni módulos generados | Sí → opción B (en derivados se valida solo lo del lock) |
| Contestar "workaround" y no tocar el core | Cero esfuerzo | Riesgo crítico abierto para todos los derivados | Sí |

## 15. Cuestionamientos (devils-advocate) — triage del master 2026-11-08
Verificación línea por línea contra el código (walkthrough `logs/walkthrough/2026-11-08_0900_security-audit_triage-hallazgos-nswms.md`):
- **NSK-01 confirmado y ampliado:** además de lo reportado, `AiController::saveSettings` está abierto (un `viewer` cambia proveedor y API key, no solo usa la IA) y la plantilla de `make-module` genera controladores sin permiso (el menú los oculta, las rutas no). No hay control por ruta ni middleware que lo compense.
- **NSK-02 confirmado, riesgo de lock ya mitigado:** `core-manifest.php` sin `--check` se niega en derivados (`nsproject.json`), y el updater solo opera sobre lo del lock. Hallazgo nuevo: `system/config/modules_menu.php` está en el lock pero lo escribe `make-module` → drift/conflicto aun con la opción B. Hallazgo nuevo: el updater marca conflicto en todo archivo editado por la app aunque el core no lo haya cambiado (ruido).
- **NSK-03 confirmado:** `migrate.php` llama `Migrator::rollback($n)` sin dir → solo core; `fresh` cuenta core+app y revierte solo core.
- **NSK-04 confirmado (4 puntos).** Descartada una sospecha propia: `expires_in` sí es timestamp UNIX (referencia 2.6.0).
- **NSK-05 confirmado.**
- **Observaciones:** "Guard no revalida `active`" sube de prioridad (combinada con NSK-01, un usuario dado de baja seguía pudiendo ejecutar cron); `totp_secret` en `Auth::user()` es real pero cifrado (riesgo bajo, fix de una línea); el resto es documentación.

## 16. Ideas para el plan
- Permiso declarado por controlador (`permission()`), constante "solo sesión", default configurable.
- Test de barrido de rutas por reflexión (sin `exit`): el `requirePermission` actual hace `exit`.
- `Guard::user()` valida `active_column` y oculta `totp_secret`; `check()` queda puro (lo usa `GuardTest` sin DB).
- `--check` en modo proyecto + `--root` para testear con un árbol temporal.

## 17. Preguntas abiertas
| # | Pregunta | ¿Bloqueante? | Respuesta | Fecha |
|---|----------|--------------|-----------|-------|
| 1 | ¿Va al roadmap? | no | No: no es funcionalidad nueva ni mejora visible (decisión del humano) | 2026-11-08 |
| 2 | ¿Alcance de 1.20.3? | no | NSK-01 (+ active, totp_secret), NSK-02, NSK-03; NSK-04/05 después (propuesta del triage, aceptada al pedir el plan) | 2026-11-08 |
| 3 | Default para controladores sin permiso declarado | no | `modules.manage`, configurable (§14) — a confirmar en `/plan approve` | 2026-11-08 |

## 18. Registro de entrevista
### Ronda 1 — 2026-11-08
- **P:** ¿Arranco con el RM y el plan de la 1.20.3?
  **R:** No hace falta el RM; dejar el artefacto de nsWMS como brief y de ahí armar el plan.

### Ronda 2 — 2026-11-08 (revisión de PL-002)
- **P:** Si el dashboard exige `dashboard.view`, un rol propio sin ese permiso queda en un 403 al entrar. ¿Redirigir, exigir el permiso o dejarlo abierto con widgets filtrados?
  **R:** Dashboard abierto, widgets filtrados.

## 19. Decisiones tomadas
| Fecha | Decisión | Motivo |
|-------|----------|--------|
| 2026-11-08 | Sin RM; el informe de nsWMS es el brief | Hardening, no feature visible (humano) |
| 2026-11-08 | 1.20.3 = NSK-01/02/03 + 2 observaciones; NSK-04/05 después | Prioridad por riesgo (triage) |
| 2026-11-08 | Dashboard abierto a todo usuario logueado, con cada widget filtrado por su permiso | Evita el 403 al entrar de roles sin `dashboard.view` sin exponer salud/auditoría/jobs (humano, en la revisión de PL-002) |

---

## Definition of Ready (para pasar a ✅ Listo)
- [x] Problema formulado y entendible sin contexto.
- [x] Actores y experiencia actual identificados.
- [x] Criterios de éxito observables.
- [x] Alcance incluye / no incluye explícito.
- [x] Reglas de negocio y casos borde escritos.
- [x] Pasada de `creative-explorer` hecha (§14).
- [x] Pasada de `devils-advocate` hecha (§15, triage contra el código).
- [x] Cero preguntas abiertas bloqueantes.

---

## Anexo A — Informe original de nsWMS (transcripción)
**Para:** mantenedores del core · **De:** nsWMS (Nubix) · **Fecha:** 2026-11-07 · **Versión:** 1.20.2, sin drift.
**Pedido:** release 1.20.3 con NSK-01 como prioridad; NSK-02 y NSK-03 en el mismo release; NSK-04 y NSK-05 pueden esperar.

### NSK-01 · Crítica · Controladores admin sin verificación de permisos
- `AdminController.php:19-26` valida solo `Auth::check()`. `CronController` sin `requirePermission`: `store` (L115), `update` (L156), `runNow` (L196), `destroy` (L213), `saveMaster`/`rotateSecret` (L85, L97); rutas `routes.php:128-141`. `CronRunner.php:108`: `exec($command . ' 2>&1', …)` para todo lo que no sea `url:`/`job:`.
- También sin permiso: `AiController`, `HealthController` (`routes.php:68`), `SearchController` (`routes.php:72`), `DashboardController` (auditoría reciente sin `dashboard.view`, L130-133). `viewer` solo tiene `dashboard.view` y nadie lo verifica.
- **Repro:** usuario `viewer` → `GET /admin/cron/create` carga → `POST /admin/cron` (`command=whoami`) crea → `POST /admin/cron/{id}/run` ejecuta; la salida queda en `/admin/cron/history`.
- **Impacto:** ejecución de comandos con los permisos del proceso web; en derivados con terceros, toma total del servidor.
- **Fix propuesto:** `requirePermission` por controlador (cron.manage, ai.manage, logs.view o health.view, dashboard.view + audit.view para el widget, filtro de búsqueda); defensa en profundidad: deny si el controlador no declara permiso; opcional `cron.allow_shell`.
- **Test sugerido:** barrer todas las rutas `/admin/*` con un `viewer` esperando 403.
- **Workaround nsWMS:** guard propio para usuarios de tienda (`wms_users`).

### NSK-02 · Alta · `--check` rechaza los puntos de extensión
- `core-manifest.json:53` promete que lo que la app crea en los puntos de extensión queda untracked; `core-manifest.php:122` clasifica como core todo lo que matchea `core_paths` y no `app_paths`; `--check` sale 1 ante cualquier diferencia (L145-152); `CoreBoundaryTest.php:58-66` exige 0.
- **Repro:** derivado nuevo + `system/config/routes.app.php` → `php tests/run.php` falla "sin drift".
- **Fix propuesto:** A) puntos de extensión en `app_paths` con excepción para sus README; B) en derivados, `--check` compara solo lo del lock.
- **Workaround nsWMS:** test en rojo aceptado y documentado.

### NSK-03 · Alta · `rollback` y `fresh` ignoran las migraciones de app
- `Migrator.php:135-138` (`$dir ?? self::dir()`), `migrate.php:62-67` (`rollback` sin dir), `migrate.php:69-74` (`fresh` cuenta todo y revierte solo core).
- **Repro:** migración de app con `-- @DOWN`, `migrate`, `rollback 1` → revierte `20260709_020000_add_trigger_duration_to_cron_runs.sql`.
- **Fix propuesto:** `rollback [N] [--app|--core]` o orden cronológico global; `fresh` revierte y reaplica ambos.
- **Workaround nsWMS:** `Migrator::rollback(N, Migrator::appDir())`.

### NSK-04 · Media · Conector NubixStore
Token solo en memoria (login por request); `put()`/`delete()` sin `ensureToken()`; `ok` solo por HTTP 2xx (la API responde 200 con 903/905); clase `final`. Fix: TokenStore, `ensureToken` en todos los verbos, éxito = 2xx y `status === 0`, quitar `final`.

### NSK-05 · Media · Transacciones no reentrantes
`commit()` confirma la externa; `transaction()` lanza si ya hay una abierta; rompe `itDb`. Fix: contador de profundidad + savepoints.

### Observaciones menores
`Crypto::maybeDecrypt()` devuelve `''` si no puede descifrar · `Guard::user()` no revalida `active` · `Auth::user()` devuelve `totp_secret` · `View::render()` con layout inexistente no avisa · `Migrator` multi-sentencia sin transacción.

## Anexo B — Veredicto del triage
| ID | Veredicto | Severidad | Destino |
|----|-----------|-----------|---------|
| NSK-01 | Confirmado y ampliado | Crítica | PL-002 |
| NSK-02 | Confirmado (+ `modules_menu.php`, + ruido de conflictos) | Alta | PL-002 |
| NSK-03 | Confirmado | Alta | PL-002 |
| NSK-04 | Confirmado | Media | Versión siguiente |
| NSK-05 | Confirmado | Media | Versión siguiente |
| Guard `active` / `totp_secret` | Confirmados | Media / Baja | PL-002 |
| `maybeDecrypt`, layout, multi-sentencia | Reales, menores | Baja | Documentación, versión siguiente |
