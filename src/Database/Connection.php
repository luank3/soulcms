<?php

declare(strict_types=1);

namespace SoulCMS\Database;

use PDO;
use PDOStatement;
use SoulCMS\Database\Exception\DatabaseException;
use SoulCMS\Database\Query\QueryBuilder;
use Throwable;

/**
 * A thin, driver-aware wrapper around PDO.
 *
 * Responsibilities
 * ----------------
 *  - Normalise the differences between SQLite, MySQL/MariaDB and PostgreSQL
 *    (last insert id, fetch semantics, error handling).
 *  - Provide a small, allocation-light query API for the repositories.
 */
final class Connection
{
    private ?PDOStatement $statement = null;

    private int $transactionLevel = 0;

    private bool $queriesExecuted = false;

    public function __construct(
        private PDO $pdo,
        private Driver $driver,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        if (in_array($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ['mysql'], true)) {
            // Strict mode keeps the CMS honest across MySQL and MariaDB.
            $this->pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): Driver
    {
        return $this->driver;
    }

    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $this->queriesExecuted = true;

        try {
            $statement = $this->pdo->prepare($sql);

            foreach ($bindings as $key => $value) {
                $parameter = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
                $statement->bindValue($parameter, $value, $this->pdoType($value));
            }

            $statement->execute();

            return $statement;
        } catch (Throwable $exception) {
            throw DatabaseException::fromQuery($exception, $sql, $bindings);
        }
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return list<array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->query($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->query($sql, $bindings)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int,mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->query($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Insert a row and return the generated identifier.
     *
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $columns)),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);

        return (int) $this->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }

        $set = [];

        foreach (array_keys($data) as $column) {
            $set[] = $this->quoteIdentifier($column) . ' = :set_' . $column;
        }

        [$clause, $bindings] = $this->buildWhere($where);

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteIdentifier($table),
            implode(', ', $set),
            $clause
        );

        $merged = [];

        foreach ($data as $column => $value) {
            $merged['set_' . $column] = $value;
        }

        foreach ($bindings as $key => $value) {
            $merged[$key] = $value;
        }

        return $this->query($sql, $merged)->rowCount();
    }

    /**
     * @param array<string,mixed> $where
     */
    public function delete(string $table, array $where): int
    {
        [$clause, $bindings] = $this->buildWhere($where);

        $sql = sprintf('DELETE FROM %s%s', $this->quoteIdentifier($table), $clause);

        return $this->query($sql, $bindings)->rowCount();
    }

    public function lastInsertId(): string
    {
        if ($this->driver === Driver::Postgres) {
            $id = $this->pdo->lastInsertId($this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION) !== null ? 'id' : null);
        } else {
            $id = $this->pdo->lastInsertId();
        }

        return (string) $id;
    }

    /**
     * Insert a row and return it, using RETURNING where the driver supports it.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function insertReturning(string $table, array $data, string $primaryKey = 'id'): ?array
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $columns)),
            implode(', ', $placeholders)
        );

        if ($this->driver->supportsReturning()) {
            $sql .= ' RETURNING *';

            return $this->selectOne($sql, $data);
        }

        $this->query($sql, $data);
        $id = (int) $this->lastInsertId();

        return $this->selectOne(
            sprintf('SELECT * FROM %s WHERE %s = ?', $this->quoteIdentifier($table), $this->quoteIdentifier($primaryKey)),
            [$id]
        );
    }

    /**
     * @template T
     * @param callable(Connection):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollBack();

            throw $exception;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT trans' . ($this->transactionLevel + 1));
        }

        ++$this->transactionLevel;
    }

    public function commit(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo->commit();
        } elseif ($this->transactionLevel > 1) {
            $this->pdo->exec('RELEASE SAVEPOINT trans' . $this->transactionLevel);
        }

        --$this->transactionLevel;
    }

    public function rollBack(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo->rollBack();
        } elseif ($this->transactionLevel > 1) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionLevel);
        }

        --$this->transactionLevel;
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    public function tableExists(string $table): bool
    {
        return $this->selectOne(
            $this->driver->isSqlite()
                ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?"
                : ($this->driver === Driver::Postgres
                    ? 'SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = ANY(current_schemas(false)) AND tablename = ?'
                    : 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'),
            [$table]
        ) !== null;
    }

    /**
     * @return list<string>
     */
    public function listTables(): array
    {
        $rows = match ($this->driver) {
            Driver::Sqlite => $this->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
            Driver::Postgres => $this->select("SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname = ANY(current_schemas(false)) ORDER BY tablename"),
            default => $this->select('SHOW TABLES'),
        };

        return array_map(static fn (array $row): string => (string) reset($row), $rows);
    }

    /**
     * @return list<string>
     */
    public function listColumns(string $table): array
    {
        $rows = match ($this->driver) {
            Driver::Sqlite => $this->select(sprintf('PRAGMA table_info(%s)', $this->quoteIdentifier($table))),
            Driver::Postgres => $this->select(
                'SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = ANY(current_schemas(false)) ORDER BY ordinal_position',
                [$table]
            ),
            default => $this->select(
                'SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = DATABASE() ORDER BY ordinal_position',
                [$table]
            ),
        };

        return array_map(
            static fn (array $row): string => (string) ($row['column_name'] ?? $row['name'] ?? reset($row)),
            $rows
        );
    }

    public function queryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public function quoteIdentifier(string $identifier): string
    {
        $quote = $this->driver === Driver::Postgres ? '"' : '`';

        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    /**
     * @param array<string,mixed> $where
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $where): array
    {
        if ($where === []) {
            return ['', []];
        }

        $conditions = [];
        $bindings = [];

        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $operator = strtoupper((string) ($value[0] ?? '='));
                $operand = $value[1] ?? null;

                if ($operator === 'IN' || $operator === 'NOT IN') {
                    $values = array_values((array) $operand);
                    $placeholders = [];

                    foreach ($values as $index => $item) {
                        $key = 'in_' . $column . '_' . $index;
                        $placeholders[] = ':' . $key;
                        $bindings[$key] = $item;
                    }

                    $conditions[] = $this->quoteIdentifier($column) . ' ' . $operator . ' (' . implode(', ', $placeholders) . ')';

                    continue;
                }

                if ($operator === 'NULL' || $operator === 'IS NULL') {
                    $conditions[] = $this->quoteIdentifier($column) . ' IS NULL';

                    continue;
                }

                if ($operator === 'NOT NULL' || $operator === 'IS NOT NULL') {
                    $conditions[] = $this->quoteIdentifier($column) . ' IS NOT NULL';

                    continue;
                }

                $key = 'where_' . $column;
                $conditions[] = $this->quoteIdentifier($column) . ' ' . $operator . ' :' . $key;
                $bindings[$key] = $operand;

                continue;
            }

            $key = 'where_' . $column;
            $conditions[] = $this->quoteIdentifier($column) . ' = :' . $key;
            $bindings[$key] = $value;
        }

        return [' WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    private function pdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
