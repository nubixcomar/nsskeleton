# app/Views/overrides/ — Overrides de vistas (de la app)

Punto de extensión del core (patrón child-theme). Para personalizar una vista, layout o
parcial **sin editar la del core** (que el actualizador pisa), poné acá un archivo con el
**mismo path lógico** y `View` lo resuelve **antes** que el del core.

Ejemplos:
- Cambiar el layout del admin → `overrides/layouts/admin.php`
- Cambiar el dashboard → `overrides/admin/dashboard/index.php`
- Cambiar un parcial → `overrides/partials/footer.php`

La resolución es: `app/Views/overrides/{nombre}.php` → si no existe, `app/Views/{nombre}.php`.

> Estos archivos son **de la app**: el actualizador de core no los toca.
