<?php

declare(strict_types=1);

/**
 * Generador de módulos CRUD.
 *   php system/console/make-module.php <ModelName> <tabla> "campo:tipo campo:tipo ..."
 * Ejemplo:
 *   php system/console/make-module.php Producto productos "nombre:string precio:decimal stock:int activo:bool"
 *
 * Tipos: string | text | int | decimal | bool | date | datetime
 * Relación: campo:fk:tabla  (ej: cliente_id:fk:clientes → select poblado + FK)
 * Genera: migración + modelo + controlador CRUD + vistas (index/form) + rutas + menú.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';

use App\Services\ModuleScaffold as S;

$args = array_slice($argv, 1);
if (count($args) < 3) {
    fwrite(STDERR, "Uso: php system/console/make-module.php <ModelName> <tabla> \"campo:tipo ...\"\n");
    exit(1);
}

$model = S::studly($args[0]);
$table = S::snake($args[1]);
$fields = S::parseFields($args[2]);
$relations = S::parseRelations($args[2]);

if ($model === '' || $table === '' || $fields === []) {
    fwrite(STDERR, "Datos inválidos (modelo, tabla y al menos un campo son obligatorios).\n");
    exit(1);
}

$created = [];
$write = static function (string $path, string $content) use (&$created): void {
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, $content);
    $created[] = str_replace(BASE_PATH . '/', 'system/', str_replace('\\', '/', $path));
};

// ---- 1) Migración ----------------------------------------------------------
$cols = '';
foreach ($fields as $name => $type) {
    $cols .= "    `{$name}` " . S::sqlType($type) . " NULL,\n";
}
// bool ya trae NOT NULL DEFAULT 0; corregimos esos:
$cols = str_replace('TINYINT(1) NOT NULL DEFAULT 0 NULL', 'TINYINT(1) NOT NULL DEFAULT 0', $cols);

// Relaciones FK: índice + constraint (ON DELETE SET NULL) por cada campo fk.
$fkConstraints = '';
foreach ($relations as $field => $refTable) {
    $fkConstraints .= "    KEY `idx_{$table}_{$field}` (`{$field}`),\n";
    $fkConstraints .= "    CONSTRAINT `fk_{$table}_{$field}` FOREIGN KEY (`{$field}`) REFERENCES `{$refTable}` (id) ON DELETE SET NULL,\n";
}

$stamp = date('Ymd_His');
$migration = "-- Migración generada: tabla `{$table}`.\n"
    . "CREATE TABLE IF NOT EXISTS `{$table}` (\n"
    . "    id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
    . $cols
    . "    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
    . "    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
    . "    deleted_at DATETIME NULL DEFAULT NULL,\n"
    . $fkConstraints
    . "    PRIMARY KEY (id)\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n"
    . "-- @DOWN\n"
    . "DROP TABLE IF EXISTS `{$table}`;\n";
// Migración del módulo → directorio de la APP (no lo pisa el actualizador de core).
$write(BASE_PATH . "/database/migrations/app/{$stamp}_create_{$table}.sql", $migration);

// ---- 2) Modelo -------------------------------------------------------------
$fillable = implode(', ', array_map(static fn (string $f): string => "'{$f}'", array_keys($fields)));
$modelCode = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\nuse Core\\Model;\n\n"
    . "final class {$model} extends Model\n{\n"
    . "    protected static string \$table = '{$table}';\n"
    . "    protected static array \$fillable = [{$fillable}];\n}\n";
$write(BASE_PATH . "/app/Models/{$model}.php", $modelCode);

// ---- 3) Controlador --------------------------------------------------------
$fieldsPhp = "[\n";
foreach ($fields as $name => $type) {
    $fieldsPhp .= "            '{$name}' => '{$type}',\n";
}
$fieldsPhp .= '        ]';

$searchableCols = array_keys(array_filter($fields, static fn (string $t): bool => in_array($t, ['string', 'text'], true)));
$searchablePhp = '[' . implode(', ', array_map(static fn (string $c): string => "'{$c}'", $searchableCols)) . ']';

// Campo "etiqueta" del registro (para el breadcrumb en editar): primer string/text, o id.
$labelField = $searchableCols[0] ?? 'id';

$relationsPhp = '[' . implode(', ', array_map(
    static fn (string $f, string $t): string => "'{$f}' => '{$t}'",
    array_keys($relations),
    array_values($relations)
)) . ']';

$rules = S::parseRules($args[2]);
$rulesPhp = '[' . implode(', ', array_map(
    static fn (string $f): string => "'{$f}' => [" . implode(', ', array_map(static fn (string $r): string => "'{$r}'", $rules[$f])) . ']',
    array_keys($rules)
)) . ']';

// Columnas para exportación (id + campos, etiqueta fk sin _id).
$exportColsPhp = "['id' => '#'";
foreach ($fields as $name => $type) {
    $lbl = $type === 'fk' ? S::label((string) preg_replace('/_id$/', '', $name)) : S::label($name);
    $exportColsPhp .= ", '{$name}' => '{$lbl}'";
}
$exportColsPhp .= ']';

$controller = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Controllers\\Admin;

use App\\Models\\{$model};
use App\\Services\\Exporter;
use App\\Services\\Paginator;
use App\\Services\\Validator;
use Core\\Auth;
use Core\\Database;
use Core\\Request;
use Core\\Response;
use Core\\Session;
use Core\\Url;

/**
 * CRUD generado para el módulo "{$table}".
 */
final class {$model}Controller extends AdminController
{
    /** Relaciones FK: campo => tabla referenciada. */
    private const RELATIONS = {$relationsPhp};

    /** Reglas de validación: campo => [reglas]. */
    private const RULES = {$rulesPhp};

    public function index(Request \$request): Response
    {
        \$pagination = Paginator::paginate('{$table}', [
            'page'       => (int) \$request->query('page', 1),
            'search'     => (string) \$request->query('search', ''),
            'searchable' => {$searchablePhp},
            'filter'     => 'deleted_at IS NULL',
            'order'      => 'id DESC',
        ]);

        return \$this->view('admin/{$table}/index', [
            'user'       => Auth::user(),
            'rows'       => \$pagination['rows'],
            'pagination' => \$pagination,
            'options'    => \$this->options(),
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return \$this->view('admin/{$table}/form', [
            'user'    => Auth::user(),
            'editing' => null,
            'options' => \$this->options(),
            'errors'  => Session::getFlash('errors') ?? [],
            'old'     => Session::getFlash('old') ?? [],
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function store(Request \$request): Response
    {
        \$this->verifyCsrf(\$request);
        \$data = \$this->data(\$request);
        \$errors = Validator::make(\$data, self::RULES, '{$table}');
        if (\$errors !== []) {
            Session::flash('errors', \$errors);
            Session::flash('old', \$data);
            return \$this->redirect(Url::to('/admin/{$table}/create'));
        }
        {$model}::create(\$data);
        Session::flash('success', 'Registro creado.');
        return \$this->redirect(Url::to('/admin/{$table}'));
    }

    public function edit(Request \$request, string \$id): Response
    {
        \$row = {$model}::find((int) \$id);
        if (\$row === null) {
            return \$this->abort(404, 'No encontrado.');
        }
        return \$this->view('admin/{$table}/form', [
            'user'    => Auth::user(),
            'editing' => \$row,
            'breadcrumbExtra' => (string) (\$row['{$labelField}'] ?? ('#' . \$row['id'])),
            'options' => \$this->options(),
            'errors'  => Session::getFlash('errors') ?? [],
            'old'     => Session::getFlash('old') ?? [],
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function update(Request \$request, string \$id): Response
    {
        \$this->verifyCsrf(\$request);
        if ({$model}::find((int) \$id) === null) {
            return \$this->abort(404, 'No encontrado.');
        }
        \$data = \$this->data(\$request);
        \$errors = Validator::make(\$data, self::RULES, '{$table}', (int) \$id);
        if (\$errors !== []) {
            Session::flash('errors', \$errors);
            Session::flash('old', \$data);
            return \$this->redirect(Url::to('/admin/{$table}/' . \$id . '/edit'));
        }
        {$model}::update((int) \$id, \$data);
        Session::flash('success', 'Registro actualizado.');
        return \$this->redirect(Url::to('/admin/{$table}'));
    }

    public function destroy(Request \$request, string \$id): Response
    {
        \$this->verifyCsrf(\$request);
        // Borrado lógico (soft-delete): va a la papelera.
        Database::run("UPDATE `{$table}` SET deleted_at = NOW() WHERE id = ?", [(int) \$id]);
        Session::flash('success', 'Registro enviado a la papelera.');
        return \$this->redirect(Url::to('/admin/{$table}'));
    }

    public function trash(Request \$request): Response
    {
        \$pagination = Paginator::paginate('{$table}', [
            'page'       => (int) \$request->query('page', 1),
            'search'     => (string) \$request->query('search', ''),
            'searchable' => {$searchablePhp},
            'filter'     => 'deleted_at IS NOT NULL',
            'order'      => 'deleted_at DESC',
        ]);

        return \$this->view('admin/{$table}/trash', [
            'user'       => Auth::user(),
            'rows'       => \$pagination['rows'],
            'pagination' => \$pagination,
            'options'    => \$this->options(),
            'success'    => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function restore(Request \$request, string \$id): Response
    {
        \$this->verifyCsrf(\$request);
        Database::run("UPDATE `{$table}` SET deleted_at = NULL WHERE id = ?", [(int) \$id]);
        Session::flash('success', 'Registro restaurado.');
        return \$this->redirect(Url::to('/admin/{$table}/trash'));
    }

    public function forceDestroy(Request \$request, string \$id): Response
    {
        \$this->verifyCsrf(\$request);
        {$model}::delete((int) \$id);
        Session::flash('success', 'Registro eliminado definitivamente.');
        return \$this->redirect(Url::to('/admin/{$table}/trash'));
    }

    public function export(Request \$request): Response
    {
        \$rows = Database::select("SELECT * FROM `{$table}` WHERE deleted_at IS NULL ORDER BY id DESC");
        \$options = \$this->options();
        foreach (\$rows as &\$row) {
            foreach (self::RELATIONS as \$field => \$_) {
                if (isset(\$row[\$field])) {
                    \$row[\$field] = \$options[\$field][\$row[\$field]] ?? \$row[\$field];
                }
            }
        }
        unset(\$row);

        \$columns = {$exportColsPhp};
        \$format = (string) \$request->query('format', 'csv');

        return match (\$format) {
            'excel' => Response::download(Exporter::excelHtml(\$rows, \$columns, '{$model}'), Exporter::filename('{$table}', 'xls'), 'application/vnd.ms-excel; charset=UTF-8'),
            'pdf'   => Response::html(Exporter::printableHtml(\$rows, \$columns, '{$model}')),
            default => Response::download(Exporter::csv(\$rows, \$columns), Exporter::filename('{$table}', 'csv'), 'text/csv; charset=UTF-8'),
        };
    }

    /** Opciones (id => etiqueta) de cada relación FK, para los selects. @return array<string,array<int,string>> */
    private function options(): array
    {
        \$opts = [];
        foreach (self::RELATIONS as \$field => \$refTable) {
            \$opts[\$field] = \\App\\Services\\RelationOptions::forTable(\$refTable);
        }
        return \$opts;
    }

    /** @return array<string,mixed> */
    private function data(Request \$request): array
    {
        \$fields = {$fieldsPhp};
        \$data = [];
        foreach (\$fields as \$name => \$type) {
            \$value = \$request->input(\$name);
            if (\$type === 'bool') {
                \$data[\$name] = \$value ? 1 : 0;
            } elseif (\$type === 'fk') {
                \$data[\$name] = (\$value === '' || \$value === null) ? null : (int) \$value;
            } else {
                \$data[\$name] = is_string(\$value) ? trim(\$value) : \$value;
            }
        }
        return \$data;
    }
}

PHP;
$write(BASE_PATH . "/app/Controllers/Admin/{$model}Controller.php", $controller);

// ---- 4) Vistas -------------------------------------------------------------
$headers = '';
$cells = '';
foreach ($fields as $name => $type) {
    $colLabel = $type === 'fk' ? S::label((string) preg_replace('/_id$/', '', $name)) : S::label($name);
    $headers .= "                <th class=\"px-4 py-3 font-medium\">{$colLabel}</th>\n";
    if ($type === 'bool') {
        $cells .= "                    <td class=\"px-4 py-3 text-slate-600\"><?= !empty(\$row['{$name}']) ? 'Sí' : 'No' ?></td>\n";
    } elseif ($type === 'fk') {
        $cells .= "                    <td class=\"px-4 py-3 text-slate-600\"><?= View::e(\$options['{$name}'][\$row['{$name}']] ?? (\$row['{$name}'] ?? '')) ?></td>\n";
    } else {
        $cells .= "                    <td class=\"px-4 py-3 text-slate-600\"><?= View::e(\$row['{$name}'] ?? '') ?></td>\n";
    }
}

$indexView = <<<PHP
<?php
/** @var array \$user @var array \$rows @var string|null \$success @var string|null \$error */
use Core\\View;
use Core\\Url;
use Core\\Session;
?>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">{$model}</h1>
    <div class="flex items-center gap-2">
        <a href="<?= View::e(Url::to('/admin/{$table}/export?format=csv')) ?>" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">CSV</a>
        <a href="<?= View::e(Url::to('/admin/{$table}/export?format=excel')) ?>" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Excel</a>
        <a href="<?= View::e(Url::to('/admin/{$table}/export?format=pdf')) ?>" target="_blank" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">PDF</a>
        <a href="<?= View::e(Url::to('/admin/{$table}/trash')) ?>" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">🗑 Papelera</a>
        <a href="<?= View::e(Url::to('/admin/{$table}/create')) ?>"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Nuevo</a>
    </div>
</div>

<div class="mb-4">
    <?= View::partial('partials/search', ['action' => Url::to('/admin/{$table}'), 'search' => \$pagination['search'] ?? '']) ?>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">#</th>
{$headers}                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach (\$rows as \$row): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-400"><?= (int) \$row['id'] ?></td>
{$cells}                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= View::e(Url::to('/admin/{$table}/' . \$row['id'] . '/edit')) ?>" class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50">Editar</a>
                            <form method="post" action="<?= View::e(Url::to('/admin/{$table}/' . \$row['id'] . '/delete')) ?>" onsubmit="return confirm('¿Eliminar?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty(\$rows)): ?>
                <tr><td colspan="99" class="px-4 py-6 text-center text-slate-400">Sin registros.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= View::partial('partials/pagination', ['pagination' => \$pagination ?? [], 'baseUrl' => Url::to('/admin/{$table}')]) ?>

PHP;
$write(BASE_PATH . "/app/Views/admin/{$table}/index.php", $indexView);

// trash view (papelera)
$trashView = <<<PHP
<?php
/** @var array \$user @var array \$rows @var array \$options @var string|null \$success */
use Core\\View;
use Core\\Url;
use Core\\Session;
?>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">{$model} — Papelera</h1>
    <a href="<?= View::e(Url::to('/admin/{$table}')) ?>" class="text-sm text-indigo-600 hover:underline">← Volver al listado</a>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">#</th>
{$headers}                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach (\$rows as \$row): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-400"><?= (int) \$row['id'] ?></td>
{$cells}                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <form method="post" action="<?= View::e(Url::to('/admin/{$table}/' . \$row['id'] . '/restore')) ?>">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-emerald-600 hover:bg-emerald-50">Restaurar</button>
                            </form>
                            <form method="post" action="<?= View::e(Url::to('/admin/{$table}/' . \$row['id'] . '/force-delete')) ?>" onsubmit="return confirm('¿Eliminar definitivamente? No se puede deshacer.');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar definitivo</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty(\$rows)): ?>
                <tr><td colspan="99" class="px-4 py-6 text-center text-slate-400">La papelera está vacía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= View::partial('partials/pagination', ['pagination' => \$pagination ?? [], 'baseUrl' => Url::to('/admin/{$table}/trash')]) ?>

PHP;
$write(BASE_PATH . "/app/Views/admin/{$table}/trash.php", $trashView);

// form view
$formInputs = '';
$errBlock = static fn (string $n): string =>
    "        <?php if (!empty(\$errors['{$n}'])): ?><p class=\"mt-1 text-xs text-red-600\"><?= View::e(\$errors['{$n}']) ?></p><?php endif; ?>\n";

foreach ($fields as $name => $type) {
    $label = $type === 'fk' ? S::label((string) preg_replace('/_id$/', '', $name)) : S::label($name);
    $input = S::inputType($type);
    $val = "\$old['{$name}'] ?? \$editing['{$name}'] ?? ''";
    $err = $errBlock($name);
    if ($input === 'select') {
        $formInputs .= "    <div>\n        <label class=\"mb-1 block text-sm font-medium text-slate-700\">{$label}</label>\n"
            . "        <select name=\"{$name}\" class=\"w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500\">\n"
            . "            <option value=\"\">— Seleccionar —</option>\n"
            . "            <?php foreach ((\$options['{$name}'] ?? []) as \$optId => \$optLabel): ?>\n"
            . "                <option value=\"<?= (int) \$optId ?>\" <?= (string) ({$val}) === (string) \$optId ? 'selected' : '' ?>><?= View::e(\$optLabel) ?></option>\n"
            . "            <?php endforeach; ?>\n"
            . "        </select>\n{$err}    </div>\n";
    } elseif ($input === 'textarea') {
        $formInputs .= "    <div>\n        <label class=\"mb-1 block text-sm font-medium text-slate-700\">{$label}</label>\n"
            . "        <?= View::partial('partials/wysiwyg', ['name' => '{$name}', 'value' => (string) ({$val})]) ?>\n{$err}    </div>\n";
    } elseif ($input === 'checkbox') {
        $formInputs .= "    <label class=\"flex items-center gap-2 text-sm text-slate-700\">\n"
            . "        <input type=\"checkbox\" name=\"{$name}\" value=\"1\" <?= !empty({$val}) ? 'checked' : '' ?> class=\"rounded border-slate-300 text-indigo-600 focus:ring-indigo-500\">\n        {$label}\n    </label>\n";
    } else {
        $step = $type === 'decimal' ? ' step="0.01"' : '';
        $formInputs .= "    <div>\n        <label class=\"mb-1 block text-sm font-medium text-slate-700\">{$label}</label>\n"
            . "        <input type=\"{$input}\"{$step} name=\"{$name}\" value=\"<?= View::e({$val}) ?>\" class=\"w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500\">\n{$err}    </div>\n";
    }
}

$formView = <<<PHP
<?php
/** @var array \$user @var array|null \$editing @var array \$options @var array \$errors @var array \$old @var string|null \$error */
use Core\\View;
use Core\\Url;
use Core\\Session;

\$errors = \$errors ?? [];
\$old = \$old ?? [];
\$isEdit = \$editing !== null;
\$action = \$isEdit ? Url::to('/admin/{$table}/' . \$editing['id']) : Url::to('/admin/{$table}');
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= \$isEdit ? 'Editar' : 'Nuevo' ?> {$model}</h1>
    <a href="<?= View::e(Url::to('/admin/{$table}')) ?>" class="text-sm text-indigo-600 hover:underline">← Volver</a>
</div>

<form method="post" action="<?= View::e(\$action) ?>" class="max-w-lg space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
{$formInputs}
    <div class="pt-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700"><?= \$isEdit ? 'Guardar' : 'Crear' ?></button>
    </div>
</form>

PHP;
$write(BASE_PATH . "/app/Views/admin/{$table}/form.php", $formView);

// ---- 5) Rutas --------------------------------------------------------------
$routes = <<<PHP
<?php

declare(strict_types=1);

use Core\\Router;

/** Rutas generadas para el módulo "{$table}". */
return static function (Router \$router): void {
    \$router->get('/admin/{$table}', 'Admin\\{$model}Controller@index');
    \$router->get('/admin/{$table}/export', 'Admin\\{$model}Controller@export');
    \$router->get('/admin/{$table}/trash', 'Admin\\{$model}Controller@trash');
    \$router->get('/admin/{$table}/create', 'Admin\\{$model}Controller@create');
    \$router->post('/admin/{$table}', 'Admin\\{$model}Controller@store');
    \$router->get('/admin/{$table}/{id}/edit', 'Admin\\{$model}Controller@edit');
    \$router->post('/admin/{$table}/{id}', 'Admin\\{$model}Controller@update');
    \$router->post('/admin/{$table}/{id}/delete', 'Admin\\{$model}Controller@destroy');
    \$router->post('/admin/{$table}/{id}/restore', 'Admin\\{$model}Controller@restore');
    \$router->post('/admin/{$table}/{id}/force-delete', 'Admin\\{$model}Controller@forceDestroy');
};

PHP;
$write(BASE_PATH . "/config/routes/{$table}.php", $routes);

// ---- 6) Menú ---------------------------------------------------------------
$menuFile = BASE_PATH . '/config/modules_menu.php';
$menu = is_file($menuFile) ? (require $menuFile) : [];
$menu = array_values(array_filter($menu, static fn (array $m): bool => ($m['path'] ?? '') !== "/admin/{$table}"));
$menu[] = ['path' => "/admin/{$table}", 'label' => $model];
file_put_contents($menuFile, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($menu, true) . ";\n");

// ---- Resumen ---------------------------------------------------------------
echo "Módulo '{$model}' (tabla {$table}) generado:\n";
foreach ($created as $f) {
    echo "  + {$f}\n";
}
echo "  ~ system/config/modules_menu.php (actualizado)\n\n";
echo "Siguiente: php system/database/migrate.php  (para crear la tabla)\n";
