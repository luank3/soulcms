<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema\Grammar;

use SoulCMS\Database\Schema\Column;
use SoulCMS\Database\Schema\TableDefinition;

/**
 * Base class for the driver specific DDL generators.
 */
abstract class SchemaGrammar
{
    public function __construct(protected string $charset = 'utf8mb4', protected string $collation = 'utf8mb4_general_ci')
    {
    }

    abstract public function compileCreate(TableDefinition $table): string;

    abstract public function compileColumn(Column $column): string;

    abstract public function quote(string $identifier): string;

    /**
     * @return list<string>
     */
    abstract public function compileCreateStatements(TableDefinition $table): array;

    public function compileDrop(string $table): string
    {
        return sprintf('DROP TABLE IF EXISTS %s', $this->quote($table));
    }

    /**
     * Render a default value for the given column type.
     */
    protected function compileDefault(Column $column): string
    {
        if (!$column->hasDefaultValue()) {
            return '';
        }

        $value = $column->getDefault();

        if ($value === null) {
            return ' DEFAULT NULL';
        }

        if (is_bool($value)) {
            return ' DEFAULT ' . ($value ? '1' : '0');
        }

        if (is_int($value) || is_float($value)) {
            return ' DEFAULT ' . $value;
        }

        return " DEFAULT '" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * The suffix applied after the type declaration.
     */
    protected function compileColumnSuffix(Column $column): string
    {
        $suffix = '';

        if (!$column->isNullable()) {
            $suffix .= ' NOT NULL';
        }

        $suffix .= $this->compileDefault($column);

        return $suffix;
    }
}
