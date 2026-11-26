<?php
declare(strict_types=1);

namespace EcoPulse\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton Database Manager wrapping PDO with transaction support and prepared statement helpers
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failure: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $sql, array $options = []): \PDOStatement
    {
        return $this->pdo->prepare($sql, $options);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Execute a SELECT query and fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SELECT query and fetch single row (or null)
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Execute an INSERT query and return last insert ID
     */
    public function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $params = [];
        foreach ($data as $col => $val) {
            $params[':' . $col] = $val;
        }

        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Execute an UPDATE query and return affected rows count
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $assignments = [];
        $params = [];

        foreach ($data as $col => $val) {
            $paramKey = ':set_' . $col;
            $assignments[] = sprintf('`%s` = %s', $col, $paramKey);
            $params[$paramKey] = $val;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $assignments),
            $where
        );

        $mergedParams = array_merge($params, $whereParams);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($mergedParams);

        return $stmt->rowCount();
    }

    /**
     * Execute a DELETE query
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Execute raw query with parameters
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
