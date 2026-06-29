<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Paginator;
use Core\Database;
use Core\Request;
use Core\Response;

/**
 * CRUD REST genérico sobre los recursos registrados en config/api.php.
 *   GET    /api/{resource}            (paginado: ?page&per_page&sort&order&q&{campo}=valor)
 *   POST   /api/{resource}
 *   GET    /api/{resource}/{id}
 *   PUT    /api/{resource}/{id}
 *   DELETE /api/{resource}/{id}
 */
final class ResourceController extends ApiController
{
    public function index(Request $request, string $resource): Response
    {
        $this->authenticate($request, 'read');
        $res = $this->resolve($resource);
        $model = $res['model'];
        $fields = $res['fields'];
        $table = $model::tableName();

        $cfg = require BASE_PATH . '/config/api.php';
        $perPage = min((int) ($cfg['max_per_page'] ?? 100), max(1, (int) $request->query('per_page', (int) ($cfg['per_page'] ?? 20))));
        $page = max(1, (int) $request->query('page', 1));

        $conds = [];
        $params = [];

        // Filtros por campo whitelisted: LIKE para texto, igualdad para el resto.
        foreach ($fields as $field => $type) {
            if (!$request->has($field)) {
                continue;
            }
            $value = $request->query($field);
            if (in_array($type, ['string', 'text'], true)) {
                $conds[] = "`{$field}` LIKE ?";
                $params[] = '%' . $value . '%';
            } else {
                $conds[] = "`{$field}` = ?";
                $params[] = $value;
            }
        }

        // Búsqueda libre ?q sobre campos de texto.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $likes = [];
            foreach ($fields as $field => $type) {
                if (in_array($type, ['string', 'text'], true)) {
                    $likes[] = "`{$field}` LIKE ?";
                    $params[] = '%' . $q . '%';
                }
            }
            if ($likes !== []) {
                $conds[] = '(' . implode(' OR ', $likes) . ')';
            }
        }

        $where = $conds !== [] ? ' WHERE ' . implode(' AND ', $conds) : '';

        // Orden whitelisted.
        $sort = (string) $request->query('sort', 'id');
        if ($sort !== 'id' && !array_key_exists($sort, $fields)) {
            $sort = 'id';
        }
        $order = strtolower((string) $request->query('order', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM `{$table}`{$where}", $params)['c'] ?? 0);
        $meta = Paginator::meta($total, $page, $perPage);
        $offset = ($meta['page'] - 1) * $perPage;

        $rows = Database::select(
            "SELECT * FROM `{$table}`{$where} ORDER BY `{$sort}` {$order} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return Response::json(['data' => $rows, 'meta' => $meta]);
    }

    public function show(Request $request, string $resource, string $id): Response
    {
        $this->authenticate($request, 'read');
        $res = $this->resolve($resource);
        $model = $res['model'];
        $row = $model::find((int) $id);
        if ($row === null) {
            $this->fail(404, 'No encontrado.');
        }
        return Response::json(['data' => $row]);
    }

    public function store(Request $request, string $resource): Response
    {
        $this->authenticate($request, 'write');
        $res = $this->resolve($resource);
        $model = $res['model'];
        $id = $model::create($this->payload($request, $res['fields']));
        return Response::json(['data' => $model::find($id)], 201);
    }

    public function update(Request $request, string $resource, string $id): Response
    {
        $this->authenticate($request, 'write');
        $res = $this->resolve($resource);
        $model = $res['model'];
        if ($model::find((int) $id) === null) {
            $this->fail(404, 'No encontrado.');
        }
        $model::update((int) $id, $this->payload($request, $res['fields']));
        return Response::json(['data' => $model::find((int) $id)]);
    }

    public function destroy(Request $request, string $resource, string $id): Response
    {
        $this->authenticate($request, 'write');
        $res = $this->resolve($resource);
        $model = $res['model'];
        $model::delete((int) $id);
        return Response::json(['data' => ['deleted' => true]]);
    }

    /** @return array{model:class-string,fields:array<string,string>} */
    private function resolve(string $resource): array
    {
        $cfg = require BASE_PATH . '/config/api.php';
        $res = $cfg['resources'][$resource] ?? null;
        if ($res === null || !class_exists($res['model'] ?? '')) {
            $this->fail(404, "Recurso '{$resource}' no existe.");
        }
        return $res;
    }

    /**
     * Toma del body solo los campos declarados del recurso (whitelist) y castea bool.
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    private function payload(Request $request, array $fields): array
    {
        $data = [];
        foreach ($fields as $name => $type) {
            if (!$request->has($name)) {
                continue;
            }
            $value = $request->input($name);
            $data[$name] = $type === 'bool'
                ? ($value ? 1 : 0)
                : (is_string($value) ? trim($value) : $value);
        }
        return $data;
    }
}
