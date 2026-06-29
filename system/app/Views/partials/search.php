<?php
/**
 * Caja de búsqueda (GET). Variables: $action (URL base), $search (valor actual).
 */
use Core\View;

$action = $action ?? '';
$search = $search ?? '';
?>
<form method="get" action="<?= View::e($action) ?>" class="flex items-center gap-2">
    <input type="search" name="search" value="<?= View::e($search) ?>" placeholder="Buscar…"
           class="w-56 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    <button class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Buscar</button>
    <?php if ($search !== ''): ?>
        <a href="<?= View::e($action) ?>" class="text-sm text-slate-400 hover:text-slate-600">Limpiar</a>
    <?php endif; ?>
</form>
