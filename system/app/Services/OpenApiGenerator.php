<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Genera un documento OpenAPI 3.0 a partir de los recursos de config/api.php.
 */
final class OpenApiGenerator
{
    private const TYPE_MAP = [
        'string'   => 'string',
        'text'     => 'string',
        'int'      => 'integer',
        'fk'       => 'integer',
        'decimal'  => 'number',
        'bool'     => 'boolean',
        'date'     => 'string',
        'datetime' => 'string',
    ];

    /** @return array<string,mixed> */
    public static function spec(): array
    {
        $cfg = require BASE_PATH . '/config/api.php';
        $resources = $cfg['resources'] ?? [];

        $paths = [];
        $schemas = [];

        foreach ($resources as $name => $res) {
            $schemaName = ucfirst($name);
            $schemas[$schemaName] = self::schema($res['fields'] ?? []);

            $ref = ['$ref' => '#/components/schemas/' . $schemaName];

            $paths['/' . $name] = [
                'get' => [
                    'summary'    => "Listar {$name}",
                    'parameters' => self::listParams(),
                    'security'   => [['bearerAuth' => []]],
                    'responses'  => ['200' => ['description' => 'OK']],
                ],
                'post' => [
                    'summary'     => "Crear {$name}",
                    'security'    => [['bearerAuth' => []]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => $ref]]],
                    'responses'   => ['201' => ['description' => 'Creado'], '403' => ['description' => 'Scope write requerido']],
                ],
            ];

            $paths['/' . $name . '/{id}'] = [
                'get' => [
                    'summary'    => "Obtener {$name}",
                    'parameters' => [self::idParam()],
                    'security'   => [['bearerAuth' => []]],
                    'responses'  => ['200' => ['description' => 'OK'], '404' => ['description' => 'No encontrado']],
                ],
                'put' => [
                    'summary'     => "Actualizar {$name}",
                    'parameters'  => [self::idParam()],
                    'security'    => [['bearerAuth' => []]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => $ref]]],
                    'responses'   => ['200' => ['description' => 'OK']],
                ],
                'delete' => [
                    'summary'    => "Eliminar {$name}",
                    'parameters' => [self::idParam()],
                    'security'   => [['bearerAuth' => []]],
                    'responses'  => ['200' => ['description' => 'Eliminado']],
                ],
            ];
        }

        return [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => AppSettings::name() . ' API',
                'version'     => self::version(),
                'description' => 'API REST generada automáticamente desde config/api.php.',
            ],
            'servers'    => [['url' => '/api']],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
                'schemas' => $schemas,
            ],
            'security' => [['bearerAuth' => []]],
            'paths'    => $paths,
        ];
    }

    /** @param array<string,string> $fields @return array<string,mixed> */
    private static function schema(array $fields): array
    {
        $props = [];
        foreach ($fields as $field => $type) {
            $props[$field] = ['type' => self::TYPE_MAP[$type] ?? 'string'];
        }
        return ['type' => 'object', 'properties' => $props];
    }

    /** @return array<int,array<string,mixed>> */
    private static function listParams(): array
    {
        $q = static fn (string $name, string $type, string $desc): array => [
            'name'   => $name,
            'in'     => 'query',
            'schema' => ['type' => $type],
            'description' => $desc,
        ];
        return [
            $q('page', 'integer', 'Número de página'),
            $q('per_page', 'integer', 'Tamaño de página'),
            $q('sort', 'string', 'Campo de orden'),
            $q('order', 'string', 'asc | desc'),
            $q('q', 'string', 'Búsqueda libre'),
        ];
    }

    /** @return array<string,mixed> */
    private static function idParam(): array
    {
        return ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
    }

    private static function version(): string
    {
        $v = trim(@file_get_contents(PROJECT_PATH . '/VERSION') ?: '');
        return $v !== '' ? $v : '1.0.0';
    }
}
