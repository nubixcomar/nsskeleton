# system/ — Esqueleto de sistema (capa de sistema)

Aplicación base ya programada que cualquier sistema necesita. Stack por defecto:
**PHP 8.2+ (MVC propio) + MySQL + Tailwind/Alpine.js**.

> 📖 **Para programar sobre el esqueleto, empezá por el manual del core:**
> [`agentic/knowledge/core-manual.md`](../agentic/knowledge/core-manual.md) — referencia
> de TODOS los módulos (API + ejemplos) y una receta para construir un módulo nuevo.

> ✅ **Núcleo MVC + módulos base implementados.** El núcleo (autoloader, Env, App,
> Router, Request/Response, Session, Database, Model, Controller, View, Auth, Crypto,
> Security…) está en `app/Core/` (ver `app/Core/README.md`). Sobre él corren los
> servicios de `app/Services/` (ver `app/Services/README.md`). Rutas de prueba `/` y
> `/health`.

## Estructura

```
system/
├── public/         Front controller (index.php) + assets públicos
├── app/
│   ├── Core/        Núcleo MVC (router, request/response, db, auth, crypto, view…)
│   ├── Controllers/
│   ├── Models/
│   ├── Views/       vistas + Views/overrides/ (la app sobrescribe el core acá)
│   ├── Services/    auth, cron, jobs, mail, backup, ia, ecommerce, archivos, ui…
│   ├── Jobs/        handlers de la cola (implementan App\Jobs\Job)
│   └── Alerts/      proveedores de alertas (implementan App\Alerts\AlertProvider)
├── config/         Configuración (lee .env) + config/overrides/ de la app
├── console/        CLI: install, key, deploy, make-module, queue, core-manifest
├── cron/           run.php (tick del cronmaster, agendar 1×/min en el SO)
├── database/
│   ├── migrations/  SQL versionado (marcador -- @DOWN para rollback)
│   └── seeds/
└── storage/        uploads/ cache/ backups/ logs/
```

## Módulos del sistema base (implementados)

Login admin + perfiles · RBAC + 2FA (TOTP) + tokens de API · backend responsive
(Tailwind + Alpine) · cronmaster + cola de jobs · emails (SMTP propio + cola) ·
backup/restore (DB + archivos) · gráficos (Chart.js) · file manager + shares ·
exportación CSV/Excel/PDF · conector de IA (OpenAI/Deepseek/Anthropic) · webhooks ·
integraciones ecommerce · auditoría · feature flags · healthcheck.

> El detalle de cada uno (qué hace, API, ejemplos, gotchas) está en el
> [manual del core](../agentic/knowledge/core-manual.md). Estos módulos toman solo lo
> **genérico** de nubixstore/Impulso (no lógica de negocio específica).
