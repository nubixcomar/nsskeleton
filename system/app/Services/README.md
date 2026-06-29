# Services — Servicios del sistema base

Lógica reutilizable, desacoplada de los controladores. Casi todos son clases con
métodos **estáticos** (`App\Services\Foo::bar(...)`).

> 📖 **Referencia completa con API y ejemplos de cada servicio:**
> [`agentic/knowledge/core-manual.md`](../../../agentic/knowledge/core-manual.md).
> Esa es la fuente de verdad; esta tabla es solo un mapa rápido.

## Mapa por categoría

| Categoría | Servicios |
|-----------|-----------|
| **Seguridad & auth** | `Rbac`, `Totp`, `LoginThrottle`, `ApiToken`, `PasswordReset`, `RateLimiter` (núcleo: `Core\Auth`, `Core\Crypto`, `Core\Security`) |
| **Datos & config** | `Settings`, `AppSettings`, `Migrator`, `Paginator`, `RelationOptions`, `FeatureFlags`, `UserTypes`, `Validator` |
| **Cron & jobs** | `CronExpression`, `CronRunner`, `ScheduleBuilder`, `JobQueue`, `Jobs` (handlers en `app/Jobs/`) |
| **Mail & notificaciones** | `Mailer`, `SmtpMailer`, `EmailQueue`, `Notifier`, `AlertService` (providers en `app/Alerts/`) |
| **Backup, deploy & sistema** | `Backup`, `Deployer`, `Installer`, `ModuleScaffold`, `Version`, `Health`, `Audit`, `DemoSeeder` |
| **IA, API & HTTP** | `AiConnector`, `PromptLibrary`, `Http`, `Webhook`, `OpenApiGenerator` |
| **Ecommerce** | `Ecommerce/` (contrato `StoreConnector` + factory + drivers nubixstore/shopify/woocommerce/tiendanube/magento) |
| **Archivos & UI** | `FileManager`, `FileShare`, `Exporter`, `Charts`, `Breadcrumb`, `Dashboard`, `GlobalSearch` |

## Convenciones

- Los servicios **no acceden a `$_GET`/`$_POST`**; reciben datos ya validados.
- El logging de auditoría (cron, email, backup, ai, notifs) es **best-effort**: no rompe
  la operación si la base no está disponible.
- Cuidado con los servicios que interpolan nombres de tabla/columna/order en el SQL
  (`Paginator`, `GlobalSearch`, `Notifier`, `Core\Model::where`): whitelistealos, nunca
  pases input de usuario crudo. Detalle de cada gotcha en el manual del core.
