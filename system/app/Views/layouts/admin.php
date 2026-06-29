<?php
/**
 * Layout del backend: sidebar de altura completa (scroll propio), drawer en mobile y
 * rail colapsable a solo-iconos en desktop. Alpine para el estado; CSS para el colapso.
 * Variables: $content, $user (admin actual), $title (opcional).
 */
use Core\View;
use Core\Url;
use Core\Session;
use Core\Icons;

$appName = \App\Services\AppSettings::name();
$appLogo = \App\Services\AppSettings::logo();
$ver = \App\Services\Version::all();
$title = ($title ?? 'Panel') . ' · ' . $appName;
$maintenanceBanner = \App\Services\FeatureFlags::enabled('maintenance_banner');

// Mensajes flash → toasts (abajo a la derecha). Se leen acá; las vistas ya no los muestran inline.
$flash = [];
if (!empty($success ?? null)) {
    $flash[] = ['type' => 'success', 'msg' => (string) $success];
}
if (!empty($error ?? null)) {
    $flash[] = ['type' => 'error', 'msg' => (string) $error];
}

// --- Menú agrupado (config/menu.php) ---
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
// Breadcrumb (auto desde la ruta + menú). Sobreescribible con $breadcrumb (array completo)
// o con $breadcrumbExtra (string: nombre del registro, ej. "Editar > Juan Pérez").
$crumbs = (isset($breadcrumb) && is_array($breadcrumb))
    ? $breadcrumb
    : \App\Services\Breadcrumb::trail($reqPath, isset($breadcrumbExtra) ? (string) $breadcrumbExtra : null);
$can = static fn (string $perm): bool => \App\Services\Rbac::can($perm);
$linkPath = static fn (string $path): string => parse_url(Url::to($path), PHP_URL_PATH) ?: $path;
$isActive = static function (string $path) use ($reqPath, $linkPath): bool {
    $lp = $linkPath($path);
    return $path === '/admin' ? ($reqPath === $lp) : str_starts_with($reqPath, $lp);
};

$menuCfg = is_file(BASE_PATH . '/config/menu.php') ? (require BASE_PATH . '/config/menu.php') : ['top' => [], 'groups' => []];

$menuTop = array_values(array_filter($menuCfg['top'] ?? [], static fn (array $n): bool => $can($n[2])));

$menuGroups = [];
$placedPaths = [];
foreach ($menuCfg['groups'] ?? [] as $group) {
    $items = array_values(array_filter($group['items'] ?? [], static fn (array $n): bool => $can($n[2])));
    foreach ($group['items'] ?? [] as $it) {
        $placedPaths[] = $it[0];
    }
    if ($items !== []) {
        $menuGroups[] = ['title' => $group['title'], 'icon' => $group['icon'] ?? 'grid', 'items' => $items];
    }
}

// Módulos generados que no estén ya ubicados → grupo "Módulos".
$modules = is_file(BASE_PATH . '/config/modules_menu.php') ? (require BASE_PATH . '/config/modules_menu.php') : [];
$extraModules = [];
foreach ($modules as $m) {
    if (!in_array($m['path'] ?? '', $placedPaths, true) && $can('modules.manage')) {
        $extraModules[] = [$m['path'], $m['label'], 'modules.manage', 'grid'];
    }
}
if ($extraModules !== []) {
    $menuGroups[] = ['title' => 'Módulos', 'icon' => 'grid', 'items' => $extraModules];
}

// Render de un ítem del menú (en la lista expandida).
$renderItem = static function (array $item) use ($isActive): string {
    [$href, $label, , $icon] = [$item[0], $item[1], $item[2], $item[3] ?? 'grid'];
    $active = $isActive($href);
    $cls = $active
        ? 'bg-indigo-600 text-white shadow-sm'
        : 'text-slate-300 hover:bg-slate-800 hover:text-white';
    return '<a href="' . View::e(Url::to($href)) . '" @click="open=false" title="' . View::e($label) . '"'
        . ' class="sb-item flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ' . $cls . '">'
        . '<span class="shrink-0">' . Icons::render($icon) . '</span>'
        . '<span class="sb-label truncate">' . View::e($label) . '</span></a>';
};

// Render de un ítem dentro del flyout (rail colapsado).
$renderFlyoutItem = static function (array $item) use ($isActive): string {
    [$href, $label, , $icon] = [$item[0], $item[1], $item[2], $item[3] ?? 'grid'];
    $active = $isActive($href);
    $cls = $active ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white';
    return '<a href="' . View::e(Url::to($href)) . '" @click="open=false"'
        . ' class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm ' . $cls . '">'
        . '<span class="shrink-0">' . Icons::render($icon, 'h-4 w-4') . '</span>'
        . '<span class="truncate">' . View::e($label) . '</span></a>';
};
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title) ?></title>
    <?= \Core\Assets::head(withChart: true) ?>
    <link rel="stylesheet" href="<?= View::e(Url::to('/assets/css/admin.css')) ?>">
    <script>(function(){try{if(localStorage.getItem('sb_collapsed')==='true')document.documentElement.classList.add('sb-collapsed');}catch(e){}})();</script>
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased" x-data="adminShell()">
<div class="flex h-screen overflow-hidden">

    <!-- ===== Sidebar ===== -->
    <aside class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 text-slate-300 lg:static lg:z-auto"
           :class="(open || desktop) ? 'translate-x-0' : '-translate-x-full'">

        <!-- Marca (linkea al dashboard) -->
        <a href="<?= View::e(Url::to('/admin')) ?>" @click="open = false" title="Ir al dashboard"
           class="app-brand flex h-16 shrink-0 items-center gap-2.5 border-b border-slate-800 px-4 hover:bg-slate-800/50">
            <?php if (!empty($appLogo)): ?>
                <img src="<?= View::e(Url::to('/' . $appLogo)) ?>" alt="logo" class="h-8 w-8 shrink-0 rounded-lg bg-white object-contain">
            <?php else: ?>
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">ns</div>
            <?php endif; ?>
            <span class="sb-brand-name truncate font-semibold text-white"><?= View::e($appName) ?></span>
        </a>

        <!-- Navegación (scroll propio) -->
        <nav class="app-nav flex-1 space-y-1 overflow-y-auto px-3 py-4">
            <?php foreach ($menuTop as $item): ?>
                <?= $renderItem($item) ?>
            <?php endforeach; ?>

            <?php foreach ($menuGroups as $group): ?>
                <div class="sb-group"
                     x-data="{ fly: false, top: 0, t: null,
                               open(el) { clearTimeout(this.t); this.top = el.getBoundingClientRect().top; this.fly = true; },
                               keep() { clearTimeout(this.t); this.fly = true; },
                               defer() { clearTimeout(this.t); this.t = setTimeout(() => { this.fly = false; }, 220); } }">

                    <!-- Expandido: encabezado + lista (sin flyout) -->
                    <div class="sb-groupfull pt-4">
                        <p class="sb-grouphdr px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500"><?= View::e($group['title']) ?></p>
                        <div class="space-y-1">
                            <?php foreach ($group['items'] as $item): ?>
                                <?= $renderItem($item) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Colapsado: icono del grupo (rail). Solo este dispara el flyout. -->
                    <div class="sb-grouprail mt-1 flex items-center justify-center rounded-lg px-2 py-2 text-slate-300 hover:bg-slate-800 hover:text-white"
                         :class="fly && 'bg-slate-800 text-white'"
                         @mouseenter="open($el)" @mouseleave="defer()">
                        <span class="shrink-0"><?= Icons::render($group['icon'] ?? 'grid') ?></span>
                    </div>

                    <!-- Flyout: visible solo colapsado + desktop; se mantiene al pasar el mouse por él -->
                    <div x-show="fly && collapsed && desktop" x-cloak
                         @mouseenter="keep()" @mouseleave="defer()"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-x-1"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         class="sb-flyout fixed left-[4.75rem] z-[60] w-56 rounded-lg bg-slate-800 py-2 shadow-xl ring-1 ring-black/20"
                         :style="'top:' + top + 'px'">
                        <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400"><?= View::e($group['title']) ?></p>
                        <div class="space-y-0.5 px-1">
                            <?php foreach ($group['items'] as $item): ?>
                                <?= $renderFlyoutItem($item) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <!-- Colapsar (solo desktop) -->
        <div class="hidden shrink-0 border-t border-slate-800 p-2 lg:block">
            <button type="button" @click="toggleCollapse()" :title="collapsed ? 'Expandir menú' : 'Colapsar menú'"
                    class="sb-item flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="shrink-0">
                    <svg class="h-5 w-5 transition-transform duration-200" :class="collapsed && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"/></svg>
                </span>
                <span class="sb-label">Colapsar menú</span>
            </button>
        </div>
    </aside>

    <!-- Backdrop mobile -->
    <div x-show="open" x-cloak @click="open=false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    <!-- ===== Columna de contenido ===== -->
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

        <header class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:px-6">
            <button @click="open = !open" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Abrir menú">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <form method="get" action="<?= View::e(Url::to('/admin/search')) ?>" class="hidden items-center sm:flex">
                <input type="search" name="q" placeholder="Buscar en todo…"
                       class="w-48 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 lg:w-72">
            </form>

            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                <?php
                $notifUnread = \App\Services\Notifier::unreadCount((int) \Core\Auth::id());
                $notifRecent = \App\Services\Notifier::forUser((int) \Core\Auth::id(), true, 5);
                ?>
                <div class="relative" x-data="{ openN: false }">
                    <button @click="openN = !openN" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Notificaciones">
                        🔔
                        <?php if ($notifUnread > 0): ?>
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"><?= $notifUnread > 9 ? '9+' : $notifUnread ?></span>
                        <?php endif; ?>
                    </button>
                    <div x-show="openN" @click.outside="openN = false" x-cloak class="absolute right-0 z-30 mt-2 w-72 rounded-lg bg-white py-2 shadow-lg ring-1 ring-slate-200">
                        <p class="px-4 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Notificaciones</p>
                        <?php foreach ($notifRecent as $n): ?>
                            <a href="<?= View::e(Url::to($n['url'] ?: '/admin/notifications')) ?>" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"><?= View::e($n['title']) ?></a>
                        <?php endforeach; ?>
                        <?php if (empty($notifRecent)): ?>
                            <p class="px-4 py-2 text-sm text-slate-400">Sin pendientes.</p>
                        <?php endif; ?>
                        <a href="<?= View::e(Url::to('/admin/notifications')) ?>" class="mt-1 block border-t border-slate-100 px-4 py-2 text-xs text-indigo-600 hover:bg-slate-50">Ver todas →</a>
                    </div>
                </div>

                <button type="button" title="Tema claro/oscuro" aria-label="Cambiar tema"
                        @click="document.documentElement.classList.toggle('dark'); try{localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light')}catch(e){}"
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100">🌓</button>

                <a href="<?= View::e(Url::to('/admin/profile')) ?>" class="hidden text-sm font-medium text-slate-600 hover:text-indigo-600 sm:block"><?= View::e($user['name'] ?? 'Admin') ?></a>
                <form method="post" action="<?= View::e(Url::to('/admin/logout')) ?>">
                    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                    <button class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Salir</button>
                </form>
            </div>
        </header>

        <!-- Único contenedor con scroll vertical → el sidebar nunca se ve afectado -->
        <main class="flex-1 overflow-y-auto">
            <div class="p-4 lg:p-6">
                <?php if (count($crumbs) > 1): ?>
                    <nav class="mb-4 flex flex-wrap items-center gap-1.5 text-sm text-slate-500" aria-label="Breadcrumb">
                        <?php foreach ($crumbs as $i => $cr): ?>
                            <?php if ($i > 0): ?>
                                <svg class="h-3.5 w-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <?php endif; ?>
                            <?php if (!empty($cr['url'])): ?>
                                <a href="<?= View::e(Url::to($cr['url'])) ?>" class="hover:text-indigo-600"><?= View::e($cr['label']) ?></a>
                            <?php else: ?>
                                <span class="<?= $i === count($crumbs) - 1 ? 'font-medium text-slate-700' : '' ?>"><?= View::e($cr['label']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <?php if ($maintenanceBanner): ?>
                    <div class="mb-4 rounded-lg bg-amber-100 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-amber-300">
                        ⚠️ Modo mantenimiento activo (feature flag <code>maintenance_banner</code>).
                    </div>
                <?php endif; ?>
                <?= $content ?? '' ?>
            </div>
            <footer class="border-t border-slate-200 px-6 py-4 text-center text-xs text-slate-400">
                <?= View::e($appName) ?> v<?= View::e($ver['app']) ?>
                <span class="mx-1 text-slate-300">·</span>
                core <?= View::e($ver['core_name']) ?> v<?= View::e($ver['core']) ?>
            </footer>
        </main>
    </div>
</div>

<!-- ===== Toasts (abajo a la derecha) ===== -->
<div x-data="toasts(<?= View::e(json_encode($flash, JSON_UNESCAPED_UNICODE)) ?>)"
     class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
    <template x-for="t in items" :key="t.id">
        <div x-show="t.show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-2 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto relative overflow-hidden rounded-lg bg-white shadow-lg ring-1"
             :class="{ 'ring-emerald-200': t.type==='success', 'ring-red-200': t.type==='error', 'ring-indigo-200': t.type==='info' }">
            <div class="flex items-start gap-3 p-3.5 pr-9">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm"
                      :class="{ 'bg-emerald-100 text-emerald-700': t.type==='success', 'bg-red-100 text-red-700': t.type==='error', 'bg-indigo-100 text-indigo-700': t.type==='info' }"
                      x-text="t.type==='success' ? '✓' : (t.type==='error' ? '!' : 'i')"></span>
                <p class="min-w-0 flex-1 text-sm text-slate-700" x-text="t.msg"></p>
                <button @click="dismiss(t.id)" class="absolute right-2 top-2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar">✕</button>
            </div>
            <div class="toast-bar absolute bottom-0 left-0 h-1"
                 :class="{ 'bg-emerald-500': t.type==='success', 'bg-red-500': t.type==='error', 'bg-indigo-500': t.type==='info' }"
                 :style="'animation-duration:' + t.dur + 'ms'"></div>
        </div>
    </template>
</div>

<script>
function wysiwyg() {
    return {
        sync() { this.$refs.input.value = this.$refs.ed.innerHTML; },
        cmd(c) { this.$refs.ed.focus(); try { document.execCommand(c, false, null); } catch (e) {} this.sync(); },
        block(tag) { this.$refs.ed.focus(); try { document.execCommand('formatBlock', false, tag); } catch (e) {} this.sync(); },
        link() {
            const url = prompt('URL del enlace:', 'https://');
            if (url) { this.$refs.ed.focus(); try { document.execCommand('createLink', false, url); } catch (e) {} this.sync(); }
        }
    };
}

function toasts(initial) {
    return {
        items: [],
        init() {
            (initial || []).forEach((f) => this.push(f.type, f.msg));
        },
        push(type, msg, dur = 4500) {
            const id = Date.now() + Math.random();
            this.items.push({ id, type, msg, dur, show: true });
            setTimeout(() => this.dismiss(id), dur);
        },
        dismiss(id) {
            const it = this.items.find((i) => i.id === id);
            if (!it) return;
            it.show = false;
            setTimeout(() => { this.items = this.items.filter((i) => i.id !== id); }, 250);
        }
    };
}

function adminShell() {
    return {
        open: false,
        collapsed: false,
        desktop: window.matchMedia('(min-width: 1024px)').matches,
        init() {
            try { this.collapsed = localStorage.getItem('sb_collapsed') === 'true'; } catch (e) {}
            document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
            const mq = window.matchMedia('(min-width: 1024px)');
            mq.addEventListener('change', (e) => { this.desktop = e.matches; if (e.matches) this.open = false; });
        },
        toggleCollapse() {
            this.collapsed = !this.collapsed;
            try { localStorage.setItem('sb_collapsed', this.collapsed); } catch (e) {}
            document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
        }
    };
}
</script>
</body>
</html>
