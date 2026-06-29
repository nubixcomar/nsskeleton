<?php

declare(strict_types=1);

namespace Core;

/**
 * Modelo base liviano sobre Database. Devuelve arrays asociativos.
 * Las subclases definen $table (y opcionalmente $primaryKey, $fillable).
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    /** Columnas permitidas para create/update. Vacío = todas. */
    protected static array $fillable = [];

    /** @return array<int,array<string,mixed>> */
    public static function all(string $orderBy = ''): array
    {
        $sql = 'SELECT * FROM ' . static::table();
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return Database::select($sql);
    }

    /** @return array<string,mixed>|null */
    public static function find(int|string $id): ?array
    {
        return Database::selectOne(
            'SELECT * FROM ' . static::table() . ' WHERE ' . static::$primaryKey . ' = ? LIMIT 1',
            [$id]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findBy(string $column, mixed $value): ?array
    {
        return Database::selectOne(
            'SELECT * FROM ' . static::table() . ' WHERE ' . $column . ' = ? LIMIT 1',
            [$value]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function where(string $column, mixed $value): array
    {
        return Database::select(
            'SELECT * FROM ' . static::table() . ' WHERE ' . $column . ' = ?',
            [$value]
        );
    }

    public static function create(array $data): int
    {
        $data = static::filter($data);
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::table(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return Database::insert($sql, $data);
    }

    public static function update(int|string $id, array $data): int
    {
        $data = static::filter($data);
        $assignments = array_map(static fn (string $c): string => "$c = :$c", array_keys($data));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :__pk',
            static::table(),
            implode(', ', $assignments),
            static::$primaryKey
        );

        $data['__pk'] = $id;
        return Database::affected($sql, $data);
    }

    public static function delete(int|string $id): int
    {
        return Database::affected(
            'DELETE FROM ' . static::table() . ' WHERE ' . static::$primaryKey . ' = ?',
            [$id]
        );
    }

    /** Nombre de la tabla del modelo (público, para consultas externas). */
    public static function tableName(): string
    {
        return static::$table;
    }

    protected static function table(): string
    {
        if (static::$table === '') {
            throw new \RuntimeException(static::class . ' no define $table.');
        }
        return static::$table;
    }

    protected static function filter(array $data): array
    {
        if (static::$fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip(static::$fillable));
    }
}
