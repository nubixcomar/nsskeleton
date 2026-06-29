# Walkthrough — Rediseño del sidebar del backend

**Fecha y hora:** 2026-06-28 11:45 | **Agente:** dev-web (Claude Code) | **Modelo:** claude-opus-4-8
**Tipo:** UI/UX (no agrega versión) | A pedido del usuario.

---

## Problemas reportados
1. El menú se desbordaba por debajo del footer blanco y se dejaban de ver opciones.
2. Mala UX general; se pedía: muy responsive + modo colapsable a solo-iconos en desktop
   para ensanchar el área de trabajo (tablas/gráficos).

## Solución
- **Estructura de layout nueva**: `flex h-screen overflow-hidden`; el **único** contenedor con
  scroll vertical es `<main>`. El sidebar es una columna de **altura completa con su propio
  scroll** (`nav` con `flex-1 overflow-y-auto`) → **nunca** se desborda bajo el footer. El
  footer va dentro de `<main>` (scrollea con el contenido), no afecta al sidebar.
- **Iconos**: `Core\Icons` (heroicons SVG inline, sin dependencias) + un icono por ítem en
  `config/menu.php` (`[path, label, permiso, icono]`).
- **Responsive (mobile)**: drawer off-canvas con backdrop, hamburguesa, cierra al tocar un
  link o el fondo (Alpine `open`).
- **Colapsable (desktop)**: rail de solo-iconos. Botón "Colapsar menú" al pie; el estado se
  **persiste en localStorage** (`sb_collapsed`) con script anti-flash en `<head>`. El colapso
  se maneja por **CSS** (`assets/css/admin.css`, clase `html.sb-collapsed`, solo `@media lg+`)
  → en mobile el drawer siempre muestra labels.
- **Detalles UI**: ítem activo en indigo, hover slate-800, grupos con encabezado (y divisores
  finos en modo colapsado), tooltips nativos (`title`) cuando está colapsado, scrollbar fina,
  footer con nombre + versión.
- **Tailwind recompilado**: las clases nuevas requerían rebuild de `app.css` (modo `local`
  compila solo lo usado) → `bash tools/build-css.sh` (28KB → 32KB).

## Verificación
- `php -l` OK · **Suite 228/228**.
- **Smoke (servidor + sesión)**: 0 warnings PHP; `admin.css`/`app.css` → 200; **17/17 links**
  del menú presentes; 18 ítems con icono; `app-sidebar`/`adminShell`/`toggleCollapse`/footer/
  botón "Colapsar menú" presentes; grupos Catálogo/Utilitarios/Usuarios/Configuración/Sistema.
- Paquete regenerado: `nsSkeleton-1.9.0.zip` (475 archivos).

## Archivos
- `system/app/Views/layouts/admin.php` (reescrito), `system/app/Core/Icons.php` (nuevo),
  `system/config/menu.php` (iconos), `system/public/assets/css/admin.css` (nuevo),
  `system/public/assets/css/app.css` (recompilado).
