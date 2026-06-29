<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * Validación simple de datos de formulario. Devuelve un mapa campo => mensaje
 * (vacío si todo es válido). Reglas: required, email, numeric, integer, unique.
 */
final class Validator
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,array<int,string>> $rules  campo => [reglas]
     * @return array<string,string> campo => primer error
     */
    public static function make(array $data, array $rules, ?string $table = null, ?int $ignoreId = null): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $str = is_string($value) ? trim($value) : (string) ($value ?? '');

            foreach ($fieldRules as $rule) {
                $error = self::check($rule, $str, $field, $table, $ignoreId);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // un error por campo
                }
            }
        }
        return $errors;
    }

    private static function check(string $rule, string $value, string $field, ?string $table, ?int $ignoreId): ?string
    {
        switch ($rule) {
            case 'required':
                return $value === '' ? 'Este campo es obligatorio.' : null;
            case 'email':
                return ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false)
                    ? 'Email inválido.' : null;
            case 'numeric':
                return ($value !== '' && !is_numeric($value)) ? 'Debe ser un número.' : null;
            case 'integer':
                return ($value !== '' && preg_match('/^-?\d+$/', $value) !== 1) ? 'Debe ser un entero.' : null;
            case 'unique':
                return ($value !== '' && $table !== null && self::existsOther($table, $field, $value, $ignoreId))
                    ? 'Ya existe un registro con este valor.' : null;
            default:
                return null;
        }
    }

    private static function existsOther(string $table, string $column, string $value, ?int $ignoreId): bool
    {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1 || preg_match('/^[a-z0-9_]+$/', $column) !== 1) {
            return false;
        }
        try {
            if ($ignoreId !== null) {
                $row = Database::selectOne("SELECT id FROM `{$table}` WHERE `{$column}` = ? AND id <> ? LIMIT 1", [$value, $ignoreId]);
            } else {
                $row = Database::selectOne("SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1", [$value]);
            }
        } catch (Throwable) {
            return false;
        }
        return $row !== null;
    }
}
