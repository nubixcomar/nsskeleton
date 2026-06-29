<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Helpers de generación de módulos CRUD. Sin efectos de filesystem (testeables).
 */
final class ModuleScaffold
{
    private const SQL_TYPES = [
        'string'   => 'VARCHAR(190)',
        'text'     => 'TEXT',
        'int'      => 'INT',
        'decimal'  => 'DECIMAL(12,2)',
        'bool'     => 'TINYINT(1) NOT NULL DEFAULT 0',
        'date'     => 'DATE',
        'datetime' => 'DATETIME',
        'fk'       => 'INT UNSIGNED',
    ];

    private const INPUT_TYPES = [
        'string'   => 'text',
        'text'     => 'textarea',
        'int'      => 'number',
        'decimal'  => 'number',
        'bool'     => 'checkbox',
        'date'     => 'date',
        'datetime' => 'datetime-local',
        'fk'       => 'select',
    ];

    public static function studly(string $name): string
    {
        $parts = preg_split('/[\s_\-]+/', trim($name)) ?: [];
        return implode('', array_map(static fn (string $p): string => ucfirst(strtolower($p)), $parts));
    }

    public static function snake(string $name): string
    {
        $s = preg_replace('/(?<!^)[A-Z]/', '_$0', trim($name)) ?? $name;
        $s = preg_replace('/[\s\-]+/', '_', $s) ?? $s;
        return strtolower($s);
    }

    public static function label(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }

    public static function sqlType(string $type): string
    {
        return self::SQL_TYPES[$type] ?? self::SQL_TYPES['string'];
    }

    public static function inputType(string $type): string
    {
        return self::INPUT_TYPES[$type] ?? 'text';
    }

    public static function isValidType(string $type): bool
    {
        return isset(self::SQL_TYPES[$type]);
    }

    /**
     * Parsea "nombre:string precio:decimal activo:bool" → ['nombre'=>'string', ...].
     * @return array<string,string>
     */
    public static function parseFields(string $spec): array
    {
        $fields = [];
        foreach (preg_split('/\s+/', trim($spec)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            $parts = explode(':', $token, 3);
            $name = self::snake($parts[0]);
            $type = strtolower(trim($parts[1] ?? 'string'));
            if ($name === '') {
                continue;
            }
            $fields[$name] = self::isValidType($type) ? $type : 'string';
        }
        return $fields;
    }

    /**
     * Parsea relaciones FK desde "cliente_id:fk:clientes" → ['cliente_id' => 'clientes'].
     * @return array<string,string> campo => tabla referenciada
     */
    public static function parseRelations(string $spec): array
    {
        $relations = [];
        foreach (preg_split('/\s+/', trim($spec)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            $parts = explode(':', $token, 3);
            $name = self::snake($parts[0]);
            $type = strtolower(trim($parts[1] ?? ''));
            $ref  = trim($parts[2] ?? '');
            if ($name !== '' && $type === 'fk' && $ref !== '') {
                $relations[$name] = self::snake($ref);
            }
        }
        return $relations;
    }

    /**
     * Reglas de validación por campo. Explícitas desde "email:string:required,email,unique"
     * (la 3ª parte, salvo fk) + derivadas del tipo (int→integer, decimal→numeric).
     * @return array<string,array<int,string>> campo => [reglas]
     */
    public static function parseRules(string $spec): array
    {
        $valid = ['required', 'email', 'numeric', 'integer', 'unique'];
        $rules = [];
        foreach (preg_split('/\s+/', trim($spec)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            $parts = explode(':', $token, 3);
            $name = self::snake($parts[0]);
            $type = strtolower(trim($parts[1] ?? 'string'));
            if ($name === '') {
                continue;
            }

            $list = [];
            // derivadas del tipo
            if ($type === 'int') {
                $list[] = 'integer';
            } elseif ($type === 'decimal') {
                $list[] = 'numeric';
            }
            // explícitas (no aplican a fk, cuya 3ª parte es la tabla)
            if ($type !== 'fk' && isset($parts[2]) && trim($parts[2]) !== '') {
                foreach (explode(',', strtolower($parts[2])) as $r) {
                    $r = trim($r);
                    if (in_array($r, $valid, true)) {
                        $list[] = $r;
                    }
                }
            }

            $list = array_values(array_unique($list));
            if ($list !== []) {
                $rules[$name] = $list;
            }
        }
        return $rules;
    }
}
