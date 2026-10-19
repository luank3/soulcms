<?php

declare(strict_types=1);

namespace SoulCMS\Database\Repository;

use SoulCMS\Database\Connection;
use SoulCMS\Database\Query\QueryBuilder;

/**
 * Base class for the module repositories.
 *
 * Repositories own the SQL for a table. Keeping the queries out of the
 * controllers makes the data access easy to audit and to reuse.
 */
abstract class BaseRepository
{
    public function __construct(protected Connection $connection)
    {
    }

    /**
     * The name of the table the repository is responsible for.
     */
    abstract public function table(): string;

    public function query(): QueryBuilder
    {
        return $this->connection->queryBuilder()->from($this->table());
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id, string $primaryKey = 'id'): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `%s` = ? LIMIT 1', $this->table(), $primaryKey),
            [$id]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        return $this->connection->insert($this->table(), $data);
    }

    /**
     * Insert a row into another table of the same schema.
     *
     * @param array<string,mixed> $data
     */
    public function createRow(string $table, array $data): int
    {
        return $this->connection->insert($table, $data);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function updateRow(array $data, array $where): int
    {
        return $this->connection->update($this->table(), $data, $where);
    }

    /**
     * @param array<string,mixed> $where
     */
    public function deleteRow(array $where): int
    {
        return $this->connection->delete($this->table(), $where);
    }

    public function countAll(): int
    {
        return (int) $this->connection->scalar(sprintf('SELECT COUNT(*) FROM `%s`', $this->table()));
    }
}
