<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOStatement;

/**
 * Conexión PDO única (singleton) + helpers de consulta.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c = require BASE_PATH . '/config/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $c['driver'] ?? 'mysql',
            $c['host'],
            $c['port'],
            $c['name'],
            $c['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    /** Ejecuta una sentencia preparada y devuelve el statement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<int,array<string,mixed>> */
    public static function select(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::connection()->lastInsertId();
    }

    public static function affected(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    /** Inicia una transacción (no-op si ya hay una abierta). */
    public static function beginTransaction(): void
    {
        $pdo = self::connection();
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }

    public static function commit(): void
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    public static function rollback(): void
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    public static function inTransaction(): bool
    {
        return self::connection()->inTransaction();
    }

    /**
     * Ejecuta $fn dentro de una transacción: commit al terminar, rollback si lanza.
     * Útil para operaciones atómicas en la app y para aislar tests (ver tests/bootstrap.php).
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
