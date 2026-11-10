<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper: prepared statements only, typed binding, nested-safe transactions.
 */
class Database
{
    /** @var list<callable(): mixed> */
    private array $afterCommit = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function connect(string $host, int $port, string $name, string $user, string $password): self
    {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );
        $pdo->exec("SET time_zone = '+00:00'");

        return new self($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Runs $callback inside a transaction. Nested calls join the outer transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback();
        }

        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->afterCommit = [];
            $this->rollBackIfActive();
            throw $e;
        }

        $callbacks = $this->afterCommit;
        $this->afterCommit = [];
        foreach ($callbacks as $pending) {
            $pending();
        }

        return $result;
    }

    /**
     * Defers side effects (e.g. sending email) until the current transaction commits.
     * Runs immediately when no transaction is open; dropped on rollback.
     */
    public function afterCommit(callable $callback): void
    {
        if ($this->pdo->inTransaction()) {
            $this->afterCommit[] = $callback;

            return;
        }
        $callback();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<Row>
     */
    public function all(string $sql, array $params = []): array
    {
        /** @var list<Row> $rows */
        $rows = $this->run($sql, $params)->fetchAll();

        return $rows;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return Row|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<int|string, mixed> $params */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<int|string, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @param array<int|string, mixed> $params */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);

        return (int) $this->pdo->lastInsertId();
    }

    private function rollBackIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** @param array<int|string, mixed> $params */
    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($name, is_float($value) ? (string) $value : $value, $type);
        }
        $statement->execute();

        return $statement;
    }
}
