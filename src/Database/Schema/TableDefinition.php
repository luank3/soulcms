<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema;

/**
 * Portable table definition.
 */
final class TableDefinition
{
    /** @var array<string,Column> */
    private array $columns = [];

    /** @var list<string> */
    private array $primaryKey = [];

    /** @var list<array{columns:list<string>,name:?string}> */
    private array $unique = [];

    /** @var list<array{columns:list<string>,name:?string}> */
    private array $indexes = [];

    /** @var list<array{column:string,references:string,onDelete:?string}> */
    private array $foreignKeys = [];

    /**
     * @param list<Column> $columns
     */
    public function __construct(
        public readonly string $name,
        array $columns = [],
    ) {
        foreach ($columns as $column) {
            $this->columns[$column->name] = $column;
        }
    }

    public function column(Column $column): self
    {
        $this->columns[$column->name] = $column;

        return $this;
    }

    /**
     * @return array<string,Column>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    public function getColumn(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }

    public function primaryKey(string ...$columns): self
    {
        $this->primaryKey = $columns;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPrimaryKey(): array
    {
        if ($this->primaryKey !== []) {
            return $this->primaryKey;
        }

        $keys = [];

        foreach ($this->columns as $column) {
            if ($column->isPrimary()) {
                $keys[] = $column->name;
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $columns
     */
    public function unique(array $columns, ?string $name = null): self
    {
        $this->unique[] = ['columns' => $columns, 'name' => $name];

        return $this;
    }

    /**
     * @param list<string> $columns
     */
    public function index(array $columns, ?string $name = null): self
    {
        $this->indexes[] = ['columns' => $columns, 'name' => $name];

        return $this;
    }

    public function foreignKey(string $column, string $references, ?string $onDelete = null): self
    {
        $this->foreignKeys[] = ['column' => $column, 'references' => $references, 'onDelete' => $onDelete];

        return $this;
    }

    /**
     * @return list<array{columns:list<string>,name:?string}>
     */
    public function getUnique(): array
    {
        $unique = $this->unique;

        foreach ($this->columns as $column) {
            if ($column->isUnique()) {
                $unique[] = ['columns' => [$column->name], 'name' => null];
            }
        }

        return $unique;
    }

    /**
     * @return list<array{columns:list<string>,name:?string}>
     */
    public function getIndexes(): array
    {
        $indexes = $this->indexes;

        foreach ($this->columns as $column) {
            if ($column->hasIndex()) {
                $indexes[] = ['columns' => [$column->name], 'name' => null];
            }
        }

        return $indexes;
    }

    /**
     * @return list<array{column:string,references:string,onDelete:?string}>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }
}
