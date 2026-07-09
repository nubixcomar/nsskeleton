# `app/` — Código de TU proyecto (fuera del core)

> Frontera core/app (hallazgo 1.6 de la auditoría de nsCheckout). **Acá va el código
> propio de tu proyecto.** Esta carpeta **no** está trackeada por `core-lock`, así que el
> actualizador de core (`/actualizar-core`) nunca la pisa y tus cambios no aparecen como
> "drift" del core.

## Qué va acá vs. en `system/app/`

| Carpeta | Namespace | ¿Core-lock? | Contenido |
|---|---|---|---|
| `system/app/` | `App\`, `Core\` | ✅ sí (lo pisa el updater) | Código del **core** nsSkeleton (no editar) |
| `app/` (raíz) | `App\` | ❌ no | **Tu** código: controllers, models, services, vistas del proyecto |

Ambas comparten el namespace `App\`. El autoloader (`system/app/Core/autoload.php`) busca
**primero en el core** y **luego en tu `app/`**, así podés agregar clases nuevas sin chocar.

## Convención

- Controller: `app/Controllers/CheckoutController.php` → `namespace App\Controllers; class CheckoutController`.
  Referenciable por string en las rutas: `$router->get('/', 'CheckoutController@index');`
  (en `system/config/routes.app.php`).
- Service: `app/Services/Foo.php` → `namespace App\Services; class Foo`.
- Model: `app/Models/Bar.php` → `namespace App\Models; class Bar`.
- Vistas propias: `app/Views/…` (o usá el child-theme de `View` para overridear vistas del core).

## Overridear una ruta del core

Registrá la misma ruta en `system/config/routes.app.php` (se cargan al final): gana la tuya
(override last-wins del `Core\Router`). Ej.: pisar la home `/` con tu controller.

## Nota

Las clases del core con el **mismo nombre** ganan (se buscan primero). Para cambiar el
comportamiento del core, no shadowees el archivo: extendé la clase o usá los puntos de
extensión (Config overrides, rutas de app, child-theme de vistas).
