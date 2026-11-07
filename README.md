# nsSkeleton

**Framework agéntico + esqueleto de sistema** para iniciar proyectos de software de forma acelerada, incluso sin conocimientos técnicos profundos.

`ns` = nubixstore · `Skeleton` = base/esqueleto sobre el que se construye cualquier sistema.

---

## ¿Qué es nsSkeleton?

nsSkeleton son **dos productos en uno**, deliberadamente separados:

1. **Capa agéntica** (`agentic/`) — *cómo se desarrolla*.
   Prompts, agentes, skills, comandos, templates, metodología (sprints, testing,
   bug-tracking, logging). Está escrita en **Markdown genérico y es 100% agnóstica
   a la IA**: no contiene carpetas propietarias (`.claude/`, etc.).

2. **Capa de sistema** (`system/`) — *qué viene preinstalado*.
   Un esqueleto de aplicación ya programado (login de administrador, gestión de
   perfiles, cron/tareas, emails, backups, gráficos, file manager, conector de IA)
   sobre el **stack por defecto: PHP puro (MVC propio) + MySQL + Tailwind/Alpine.js**.

Entre las dos está el **instalador** (`installer/`): un conjunto de preguntas que la
IA le hace al humano para definir stack, IA elegida y datos del proyecto, y luego
**materializa** la capa agéntica al formato de la IA seleccionada.

---

## Principio rector: agnóstico a la IA

```
AGENTS.md  ← archivo de arranque universal (lo lee la IA por defecto)
   │  deriva a ↓
agentic/   ← fuente de verdad (Markdown neutral, agnóstico)
```

El repositorio base **nunca** trae carpetas atadas a una IA concreta: **no hay
`.claude/` ni equivalentes**. Cualquier IA arranca leyendo
[`AGENTS.md`](AGENTS.md), que la deriva a `agentic/rules/rules.md` y a la metodología.
Si querés wirear una IA puntual a su propio archivo de arranque, es opt-in y es
decisión tuya.

---

## Estructura

```
nsSkeleton/
├── AGENTS.md       Archivo de arranque universal (la IA empieza acá)
├── landing/        Landing page de presentación + build de paquetes (ZIP)
├── agentic/        Capa agéntica agnóstica del CORE (fuente de verdad)
│   ├── rules/        Reglas en cascada (master → stack → features)
│   ├── agents/       Agentes por categoría (qa, audit, dev, docs, data, frontend, integrations)
│   ├── skills/       Especificación detallada por skill ({nombre}/SKILL.md)
│   ├── commands/     Comandos de orquestación (/roadmap, /brief, /plan, /bug, /fix, /sprint...)
│   ├── templates/    Plantillas: brief, roadmap, bug-report, walkthrough...
│   ├── methodology/  Roadmap→Brief→Plan, sprints, bug-tracking y logging obligatorio
│   ├── knowledge/    Manuales de referencia (APIs de ecommerce, etc.)
│   └── adapters/     Bindings por stack (php-mvc por defecto)
├── app-agentic/    Capa agéntica DEL PROYECTO (nunca la pisa un update de core)
├── installer/      Q&A + materializadores por IA (targets/)
├── system/         Esqueleto de sistema, CORE (PHP MVC + MySQL)
├── app/            Código del proyecto (namespace App\): Controllers/Models/Services/Views
├── tests/          Suite propia (unit/ + feature/) — `php tests/run.php`
├── planning/       Del proyecto: roadmap/ (RM-), briefs/ (BR-), plans/ (PL-) — se crea con /roadmap init
├── docs/           brief.md · roadmap.md · architecture.md · stack.md · CHANGELOG.md
├── logs/           Logs incrementales por agente + walkthroughs
├── core-manifest.json  Frontera declarativa core/app (qué es del core)
├── core-lock.json      Snapshot sha256 del core (lo usa /actualizar-core)
├── .env.example    Plantilla de credenciales (DB, FTP, repos, deploy, IA)
└── README.md
```

**Frontera core / proyecto:** el core es dueño **solo** de lo que lista `core-manifest.json`;
todo lo demás (`app/`, `app-agentic/`, overrides, migraciones de app) es del proyecto y
sobrevive intacto a un update de core. Ver [`docs/CORE-UPDATE.md`](docs/CORE-UPDATE.md).

---

## Cómo se usa (flujo previsto)

1. Cloná/descargá nsSkeleton como base de tu nuevo proyecto — **no copies la carpeta a mano**,
   seguí el Paso 0 de [`docs/INSTALL.md`](docs/INSTALL.md).
2. La IA ejecuta el **instalador**: te pregunta stack, IA a usar y datos del proyecto.
3. El instalador completa `docs/` y `.env`; la capa agéntica queda lista en `agentic/`
   y la IA la consume arrancando por `AGENTS.md` (sin generar carpetas propietarias).
4. Completás los archivos base: `docs/stack.md`, `docs/brief.md`, `docs/roadmap.md`, `.env`.
5. (Opcional) Instalás el **sistema base** ya programado (login admin, cron, mail, etc.).
6. Empezás a desarrollar con los agentes, que **siempre registran su trabajo** en `logs/`.

---

## Filosofía de trabajo: entender → decidir → codear

Para implementaciones medianas y grandes, nsSkeleton trae de fábrica el flujo
**Roadmap → Brief → Plan**, con tres índices codificados y con estado:

| Fase | Comando | Código | Qué resuelve |
|------|---------|--------|--------------|
| Roadmap | `/roadmap` (`/rm`) | `RM-` | Qué se construye y cuándo (fechas tentativas, re-planificación con historial). |
| Brief | `/brief` (`/br`) | `BR-` | Por qué y para quién: dolores, experiencia del cliente, necesidades. Agentes curiosos, creativos y escépticos preguntan hasta que no quedan dudas. |
| Plan | `/plan` (`/pl`) | `PL-` | Cómo: diseño + fases ejecutables casi en pseudocódigo. Se ejecuta con `/plan run`, incluso en loop. |

**Pensar caro, ejecutar barato:** el brief y el plan se hacen con el modelo más capaz y
esfuerzo máximo; la ejecución desde un plan aprobado, con un modelo estándar. Las urgencias
pueden saltear fases, pero queda registrado. Ver
[`agentic/methodology/roadmap-brief-plan.md`](agentic/methodology/roadmap-brief-plan.md).

---

## Reglas no negociables para todo proyecto iniciado desde nsSkeleton

Todo agente, **siempre**, al finalizar una tarea:

- **a)** Crea un walkthrough `YYYY-MM-DD_HHMM_<agente>_<feature>.md` en `logs/walkthrough/`.
- **b)** Agrega una línea de síntesis a su log incremental `logs/<agente>.log` (append-only).
- **c)** Guarda los informes/walkthrough de lo realizado (nunca se sobrescriben).

Ver detalle en [`agentic/methodology/logging.md`](agentic/methodology/logging.md).

---

## Estado del proyecto

✅ **Versión 1.20.2** — 452 tests verdes, verificados contra MySQL.

Sobre el MVP (núcleo MVC, login/perfiles, cron, emails, backups, gráficos, file manager, IA)
se fue sumando:

- **v1.1→v1.4** — tests + cifrado + hardening de login + reset password + headers + assets
  locales; generador de módulos CRUD + búsqueda/paginación + settings + auditoría; API REST +
  RBAC + cron jobs + cola de emails; instalador + seeders demo + módulo showcase + deploy.
- **v1.5→v1.9** — UX & DX; generador++ y datos; seguridad & cuentas; API & procesos; mejoras
  portadas de nsCentral.
- **v1.10** — conector genérico de ecommerce (nubixstore, Shopify, TiendaNube, WooCommerce,
  Magento) + agentes/skills de integración.
- **v1.11→v1.15** — **actualización de core** end-to-end: frontera declarativa core/app
  (`core-manifest.json` + lock), split agéntico (`app-agentic/`), puntos de extensión
  (config/rutas/vistas/migraciones override), el actualizador con plan-conflictos-rollback,
  publicación del paquete de core y el comando asistido `/actualizar-core`.
- **v1.16→v1.18** — camino dorado de producción: CSP configurable + presets, override real de
  rutas app→core, templates de deploy (nginx/Apache) y `deploy:doctor`; más el saldo de la
  auditoría de nsCheckout (`Http` con retries, verificación de webhooks entrantes, `db:doctor`,
  `Money`, `Hmac`, migración remota por token).
- **v1.19** — **Cron v2**: disparador web `/cron/run` protegido por secreto + allowlist de IP
  (para EasyCron/curl, sin depender del cron del SO), tareas tipo `url:` e historial de
  ejecuciones con filtros.
- **v1.20** — **actualizador de core** con merge 3-way automático de conflictos, índice
  `latest.json` (descarga verificada por sha256, `--check`/`--latest`) y pantalla
  `/admin/core-update`; **`/nuevo-proyecto`** para crear un derivado en un paso; manuales
  remotos; metodología **Roadmap → Brief → Plan** (`planning/`, comandos `/roadmap` `/brief` `/plan`).

Detalle completo en [`docs/CHANGELOG.md`](docs/CHANGELOG.md).

Puesta en marcha: ver [`docs/INSTALL.md`](docs/INSTALL.md). Avance: [`docs/roadmap.md`](docs/roadmap.md).
