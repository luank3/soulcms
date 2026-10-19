<?php

declare(strict_types=1);

namespace SoulCMS\Database\Query;

use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

/**
 * Query grammar: renders the SQL fragments whose syntax differs between
 * SQLite, MySQL/MariaDB and PostgreSQL.
 */
final class Grammar
{
    public function __construct(private Connection $connection)
    {
    }

    public function driver(): Driver
    {
        return $this->connection->driver();
    }

    public function quote(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }

    /**
     * Quote a possibly schema qualified identifier ("table.column").
     *
     * Only plain identifiers are accepted. A raw SQL fragment has to be
     * passed as a {@see Raw} object, so unquoted SQL can never be reached
     * from user input by accident.
     *
     * @throws \InvalidArgumentException when the identifier is not a plain one
     */
    public function wrap(Raw|string $identifier): string
    {
        if ($identifier instanceof Raw) {
            return $identifier->sql;
        }

        $identifier = trim($identifier);

        if ($identifier === '*') {
            return '*';
        }

        // "expression AS alias"
        if (preg_match('/^(.*?)\s+as\s+([a-zA-Z_][a-zA-Z0-9_]*)$/i', $identifier, $matches) === 1) {
            return $this->wrap($matches[1]) . ' AS ' . $this->quote($matches[2]);
        }

        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);

            return implode('.', array_map(
                fn (string $part): string => $part === '*' ? '*' : $this->quote($part),
                $parts
            ));
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException(sprintf(
                'Refusing to use "%s" as an identifier: pass a Raw object for raw SQL.',
                $identifier
            ));
        }

        return $this->quote($identifier);
    }

    /**
     * @param int|null $limit
     * @param int|null $offset
     */
    public function compileLimit(?int $limit, ?int $offset): string
    {
        if ($limit === null && $offset === null) {
            return '';
        }

        $limit = $limit ?? PHP_INT_MAX;
        $offset = max(0, $offset ?? 0);

        if ($this->driver() === Driver::MySql || $this->driver() === Driver::MariaDb) {
            if ($offset > 0) {
                return sprintf(' LIMIT %d, %d', $offset, $limit);
            }

            return sprintf(' LIMIT %d', $limit);
        }

        if ($offset > 0) {
            return sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        return sprintf(' LIMIT %d', $limit);
    }

    /**
     * @param list<Raw|string> $columns
     */
    public function compileColumns(array $columns): string
    {
        if ($columns === []) {
            return '*';
        }

        return implode(', ', array_map(fn (Raw|string $column): string => $this->wrap($column), $columns));
    }
}
