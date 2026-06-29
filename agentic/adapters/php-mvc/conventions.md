# Convenciones del stack: php-mvc

Bindings concretos del stack por defecto (PHP MVC propio + MySQL).

> 📖 **¿Qué módulos ya existen y cómo se usan?** Antes de codear, leé el manual del
> core: [`../../knowledge/core-manual.md`](../../knowledge/core-manual.md). Documenta la
> API y ejemplos de TODO `system/app/` (núcleo, servicios, jobs, alertas) e incluye una
> receta para construir un módulo nuevo. Este archivo cubre **dónde van las cosas**; el
> manual cubre **qué hay y cómo invocarlo**.

## Paths
| Concepto genérico        | Ubicación en este stack                 |
|--------------------------|-----------------------------------------|
| Front controller         | `system/public/index.php`               |
| Controladores            | `system/app/Controllers/`               |
| Modelos                  | `system/app/Models/`                    |
| Vistas                   | `system/app/Views/`                     |
| Servicios                | `system/app/Services/`                  |
| Núcleo (Router, DB...)   | `system/app/Core/`                      |
| Configuración            | `system/config/`                        |
| Migraciones SQL          | `system/database/migrations/`           |
| Seeds                    | `system/database/seeds/`                |
| Uploads / archivos       | `system/storage/uploads/`               |
| Backups                  | `system/storage/backups/`               |
| Caché                    | `system/storage/cache/`                 |

## Clases núcleo
| Rol                | Clase            |
|--------------------|------------------|
| Router             | `Core\Router`    |
| Request/Response   | `Core\Request`, `Core\Response` |
| Conexión DB        | `Core\Database`  |
| Autenticación      | `Core\Auth`      |
| Renderizado vistas | `Core\View`      |
| Controlador base   | `Core\Controller`|
| Modelo base        | `Core\Model`     |

> API completa de estas y del resto (`Crypto`, `Security`, `Session`, `Url`, `Assets`,
> `Icons`…) en el [manual del core](../../knowledge/core-manual.md) §1.

## Convenciones
- **Tablas:** `snake_case` plural (ej. `admin_users`, `cron_tasks`).
- **Migraciones:** archivo `YYYYMMDD_HHMM_descripcion.sql`, idempotentes donde sea posible.
- **Rutas:** definidas en `system/config/routes.php`.
- **Logging app:** `system/storage/logs/`. (Distinto de los logs agénticos en `logs/`.)
- **PHP CLI (XAMPP):** `C:/xampp/php/php.exe`.

## Puntos de extensión core/app (no editar el core; overridealo)
Para personalizar el core **sin editar sus archivos** (así el actualizador no pisa tu cambio):

| Querés cambiar… | Hacé esto (app) | Mecanismo (core) |
|-----------------|-----------------|------------------|
| Una config (`config/x.php`) | Crear `system/config/overrides/x.php` con las claves a pisar | `Core\Config::load('x')` (merge, app gana) |
| Agregar rutas | `system/config/routes.app.php` (o un archivo en `config/routes/`) | `config/routes.php` las carga después del core |
| Una vista/layout/parcial | `system/app/Views/overrides/{mismo-path}.php` | `Core\View` resuelve override → core (child-theme) |
| Migraciones del proyecto | `system/database/migrations/app/*.sql` | `Migrator::migrate()` corre core y luego app; el update usa `migrateCore()` |
| Una regla / agente / skill | `app-agentic/…` homónimo | precedencia app > core (override por nombre) |

> Hoy leen vía `Core\Config` (overrideables): `app`, `features`. Hacer overrideable otra config
> es cambiar su lectura a `Config::load(...)` en el servicio que la consume (1 línea).

## Conector de ecommerce (skills `ecommerce-integration` / `nubixstore-api`)
| Concepto genérico              | Binding en este stack                                 |
|--------------------------------|-------------------------------------------------------|
| Contrato del conector          | `App\Services\Ecommerce\StoreConnector`               |
| Base HTTP/normalización        | `App\Services\Ecommerce\AbstractStoreConnector`       |
| Fábrica de conectores          | `App\Services\Ecommerce\StoreConnectorFactory`        |
| Driver nubixstore (referencia) | `App\Services\Ecommerce\NubixstoreConnector`          |
| Otros drivers                  | `Shopify`/`TiendaNube`/`WooCommerce`/`Magento`Connector |
| Registro de plataformas        | `system/config/ecommerce.php`                         |
| Credenciales (cifradas)        | `settings` grupo `ecommerce` (Settings::setSecret)    |

## Cómo loguea un skill genérico en este stack
- Detección de bugs → `logs/bug-detection.log` + `logs/bugs-resume.md`.
- Walkthrough → `logs/walkthrough/`.
