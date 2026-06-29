<?php
/**
 * Controles de paginación. Variables:
 *   $pagination (salida de Paginator), $baseUrl (path sin query).
 */
use Core\View;

$p = $pagination ?? [];
$baseUrl = $baseUrl ?? '';
if (empty($p) || ($p['total'] ?? 0) === 0) {
    return;
}

$link = static function (int $page) use ($baseUrl, $p): string {
    $params = ['page' => $page];
    if (($p['search'] ?? '') !== '') {
        $params['search'] = $p['search'];
    }
    return $baseUrl . '?' . http_build_query($params);
};
?>
<div class="mt-4 flex items-center justify-between text-sm text-slate-500">
    <span>Mostrando <?= (int) $p['from'] ?>–<?= (int) $p['to'] ?> de <?= (int) $p['total'] ?></span>
    <div class="flex items-center gap-1">
        <?php if ($p['hasPrev']): ?>
            <a href="<?= View::e($link($p['page'] - 1)) ?>" class="rounded-md border border-slate-200 px-3 py-1 hover:bg-slate-50">← Anterior</a>
        <?php else: ?>
            <span class="rounded-md border border-slate-100 px-3 py-1 text-slate-300">← Anterior</span>
        <?php endif; ?>

        <span class="px-2">Página <?= (int) $p['page'] ?> / <?= (int) $p['pages'] ?></span>

        <?php if ($p['hasNext']): ?>
            <a href="<?= View::e($link($p['page'] + 1)) ?>" class="rounded-md border border-slate-200 px-3 py-1 hover:bg-slate-50">Siguiente →</a>
        <?php else: ?>
            <span class="rounded-md border border-slate-100 px-3 py-1 text-slate-300">Siguiente →</span>
        <?php endif; ?>
    </div>
</div>
