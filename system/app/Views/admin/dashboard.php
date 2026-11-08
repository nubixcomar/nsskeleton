<?php
/**
 * Dashboard — renderiza los bloques del preset activo (config/dashboard.php).
 * Plantilla reutilizable: cambiá el preset en Configuración o agregá bloques en partials/dashboard/.
 * @var array $user @var array $blocks @var array $stats @var array $charts @var array $alerts
 * @var array $health @var array $kpis @var array $novedades @var array $recentAudit
 * @var array $recentJobs @var array $modules @var string $presetLabel
 * @var bool $canHealth @var bool $canSearch
 * Los datos que el usuario no puede ver llegan en null (PL-002/F3): cada parcial lo respeta.
 */
use Core\View;
use Core\Url;

$blockData = compact('user', 'stats', 'charts', 'alerts', 'health', 'kpis', 'novedades', 'recentAudit', 'recentJobs', 'modules');
?>

<!-- Encabezado -->
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
        <p class="text-sm text-slate-500">Hola, <?= View::e($user['name'] ?? 'Admin') ?> 👋 — resumen del sistema y demo de las funcionalidades del core.</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="hidden rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 sm:inline" title="Preset de dashboard (se cambia en Configuración)">Vista: <?= View::e($presetLabel ?? 'Completo') ?></span>
        <?php if (!empty($canHealth)): ?>
            <a href="<?= View::e(Url::to('/admin/health')) ?>" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Estado</a>
        <?php endif; ?>
        <?php if (!empty($canSearch)): ?>
            <a href="<?= View::e(Url::to('/admin/search')) ?>" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">🔍 Buscar</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($blocks === []): ?>
    <div class="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-600">No tenés paneles disponibles en el dashboard. Usá el menú para ir a las secciones a las que tenés acceso.</p>
        <a href="<?= View::e(Url::to('/admin/profile')) ?>" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">Ir a Mi perfil</a>
    </div>
<?php endif; ?>

<?php
foreach ($blocks as $b) {
    if (preg_match('/^[a-z_]+$/', (string) $b) === 1 && View::exists('partials/dashboard/' . $b)) {
        echo View::partial('partials/dashboard/' . $b, $blockData);
    }
}
?>
