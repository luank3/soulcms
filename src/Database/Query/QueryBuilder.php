<?php

declare(strict_types=1);

namespace SoulCMS\Database\Query;

use SoulCMS\Database\Connection;

/**
 * A small, driver agnostic fluent query builder.
 *
 * It covers the subset of SQL the CMS needs (select/insert/update/delete with
 * joins, filters, ordering and pagination) while producing portable SQL for
 * every supported driver.
 */
final class QueryBuilder
{
    /** @var list<string> */
    private const OPERATORS = [
        '=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'ILIKE', 'IS', 'IS NOT',
    ];

    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<string> */
    private array $joins = [];

    /** @var list<string> */
    private array $wheres = [];

    /** @var list<string> */
    private array $bindings = [];

    /** @var list<string> */
    private array $orders = [];

    /** @var list<string> */
    private array $groups = [];

    /** @var list<string> */
    private array $havings = [];

    private ?string $table = null;

    private ?int $limit = null;

    private ?int $offset = null;

    private bool $distinct = false;

    public function __construct(private Connection $connection)
    {
    }

    public function grammar(): Grammar
    {
        return new Grammar($this->connection);
    }

    public function from(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @param Raw|string|list<Raw|string> ...$columns
     */
    public function select(Raw|string|array ...$columns): self
    {
        $selected = [];

        foreach ($columns as $column) {
            foreach (is_array($column) ? $column : [$column] as $item) {
                $selected[] = $item;
            }
        }

        $this->columns = $selected === [] ? ['*'] : $selected;

        return $this;
    }

    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = sprintf(
            'LEFT JOIN %s ON %s %s %s',
            $this->grammar()->wrap($table),
            $this->grammar()->wrap($first),
            $operator,
            $this->grammar()->wrap($second)
        );

        return $this;
    }

    public function innerJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = sprintf(
            'INNER JOIN %s ON %s %s %s',
            $this->grammar()->wrap($table),
            $this->grammar()->wrap($first),
            $operator,
            $this->grammar()->wrap($second)
        );

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = sprintf(
            'JOIN %s ON %s %s %s',
            $this->grammar()->wrap($table),
            $this->grammar()->wrap($first),
            $operator,
            $this->grammar()->wrap($second)
        );

        return $this;
    }

    /**
     * @param string|list<string> $column
     */
    public function where(string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $item) {
                $this->where((string) $key, '=', $item);
            }

            return $this;
        }

        // Shorthand: where('column', 'value') is treated as an equality check.
        if ($operator !== null && $value === null && !in_array(strtoupper((string) $operator), self::OPERATORS, true)) {
            $value = $operator;
            $operator = '=';
        }

        if ($operator === null) {
            $operator = '=';
        }

        $placeholder = 'w' . (count($this->wheres) + 1);

        if (is_array($value)) {
            $this->whereIn($column, $value, $boolean, $operator === '!=' || $operator === '<>' ? 'NOT IN' : 'IN');

            return $this;
        }

        if ($value === null && $operator === '=') {
            $this->wheres[] = sprintf('%s IS NULL', $this->grammar()->wrap($column));

            return $this;
        }

        $this->wheres[] = sprintf(
            '%s %s :%s',
            $this->grammar()->wrap($column),
            $operator,
            $placeholder
        );

        $this->bindings[$placeholder] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', string $operator = 'IN'): self
    {
        if ($values === []) {
            $this->wheres[] = '1 = 0';

            return $this;
        }

        $placeholders = [];

        foreach (array_values($values) as $index => $value) {
            $key = 'in' . (count($this->bindings) + $index);
            $placeholders[] = ':' . $key;
            $this->bindings[$key] = $value;
        }

        $this->wheres[] = sprintf(
            '%s %s (%s)',
            $this->grammar()->wrap($column),
            $operator,
            implode(', ', $placeholders)
        );

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'AND', 'NOT IN');
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = sprintf('%s IS NULL', $this->grammar()->wrap($column));

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = sprintf('%s IS NOT NULL', $this->grammar()->wrap($column));

        return $this;
    }

    /**
     * Add a raw WHERE fragment.
     *
     * The fragment has to be an explicit {@see Raw} object so that the
     * unquoted SQL is always visible in the source.
     */
    public function whereRaw(Raw $expression, array $bindings = []): self
    {
        $this->wheres[] = $expression->sql;

        foreach ($bindings as $key => $value) {
            $this->bindings[is_int($key) ? 'raw' . (count($this->bindings) + $key) : $key] = $value;
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $this->orders[] = sprintf('%s %s', $this->grammar()->wrap($column), $direction);

        return $this;
    }

    public function groupBy(string $column): self
    {
        $this->groups[] = $this->grammar()->wrap($column);

        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $placeholder = 'h' . (count($this->havings) + 1);
        $this->havings[] = sprintf('%s %s :%s', $this->grammar()->wrap($column), $operator, $placeholder);
        $this->bindings[$placeholder] = $value;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    public function forPage(int $page, int $perPage): self
    {
        $page = max(1, $page);

        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int
    {
        $grammar = $this->grammar();
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $grammar->wrap((string) $this->table),
            implode(', ', array_map(fn (string $c): string => $grammar->wrap($c), $columns)),
            implode(', ', $placeholders)
        );

        $this->connection->query($sql, $data);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(array $data): int
    {
        $grammar = $this->grammar();
        $set = [];

        foreach (array_keys($data) as $column) {
            $set[] = $grammar->wrap($column) . ' = :set_' . $column;
        }

        $bindings = [];

        foreach ($data as $column => $value) {
            $bindings['set_' . $column] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $grammar->wrap((string) $this->table),
            implode(', ', $set),
            $this->compileWheres()
        );

        return $this->connection->query($sql, array_merge($bindings, $this->bindings))->rowCount();
    }

    public function delete(): int
    {
        $grammar = $this->grammar();

        $sql = sprintf(
            'DELETE FROM %s%s',
            $grammar->wrap((string) $this->table),
            $this->compileWheres()
        );

        return $this->connection->query($sql, $this->bindings)->rowCount();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->bindings);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function first(): ?array
    {
        $rows = $this->limit(1)->get();

        return $rows[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();

        return $row === null ? null : reset($row);
    }

    public function count(string $column = '*'): int
    {
        $row = $this->clone()->select($column)->first();

        return $row === null ? 0 : (int) reset($row);
    }

    public function exists(): bool
    {
        return $this->clone()->select('1')->limit(1)->get() !== [];
    }

    public function clone(): self
    {
        $clone = clone $this;

        return $clone;
    }

    public function toSql(): string
    {
        $grammar = $this->grammar();

        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . $grammar->compileColumns($this->columns);
        $sql .= ' FROM ' . $grammar->wrap((string) $this->table);

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->compileWheres();

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->havings !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        $sql .= $grammar->compileLimit($this->limit, $this->offset);

        return $sql;
    }

    /**
     * @return array<string,mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function compileWheres(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $this->wheres);
    }
}
