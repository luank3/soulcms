<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema\Grammar;

use SoulCMS\Database\Schema\Column;
use SoulCMS\Database\Schema\TableDefinition;

/**
 * SQLite DDL generator.
 *
 * SQLite is dynamically typed, so the declared types act as affinity hints
 * rather than hard constraints - exactly like the SketchCMS tables that this
 * schema is compatible with.
 */
final class SQLiteSchemaGrammar extends SchemaGrammar
{
    public function compileCreate(TableDefinition $table): string
    {
        return implode("\n", $this->compileCreateStatements($table)) . ';';
    }

    /**
     * @return list<string>
     */
    public function compileCreateStatements(TableDefinition $table): array
    {
        $definitions = [];

        foreach ($table->columns() as $column) {
            $definitions[] = '  ' . $this->compileColumn($column);
        }

        $primaryKey = $table->getPrimaryKey();

        if (count($primaryKey) === 1) {
            $column = $table->getColumn($primaryKey[0]);

            // An "INTEGER PRIMARY KEY AUTOINCREMENT" column already carries the
            // primary key; declaring it twice is a syntax error in SQLite.
            if ($column === null || !$column->isAutoIncrement()) {
                $definitions[] = '  PRIMARY KEY (' . $this->quote($primaryKey[0]) . ')';
            }
        } elseif ($primaryKey !== []) {
            $definitions[] = '  PRIMARY KEY (' . implode(', ', array_map(fn (string $c): string => $this->quote($c), $primaryKey)) . ')';
        }

        foreach ($table->getUnique() as $unique) {
            $definitions[] = sprintf(
                '  UNIQUE (%s)',
                implode(', ', array_map(fn (string $c): string => $this->quote($c), $unique['columns']))
            );
        }

        $sql = sprintf(
            "CREATE TABLE %s (\n%s\n)",
            $this->quote($table->name),
            implode(",\n", $definitions)
        );

        $statements = [$sql];

        foreach ($table->getIndexes() as $index) {
            $statements[] = sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON %s (%s)',
                $this->quote($index['name'] ?? $this->indexName($table->name . '_' . implode('_', $index['columns']) . '_index')),
                $this->quote($table->name),
                implode(', ', array_map(fn (string $c): string => $this->quote($c), $index['columns']))
            );
        }

        foreach ($table->getForeignKeys() as $foreignKey) {
            $statement = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s',
                $this->quote($table->name),
                $this->quote($this->indexName($table->name . '_' . $foreignKey['column'] . '_foreign')),
                $this->quote($foreignKey['column']),
                $this->quote($foreignKey['references'])
            );

            if ($foreignKey['onDelete'] !== null) {
                $statement .= ' ON DELETE ' . $foreignKey['onDelete'];
            }

            $statements[] = $statement;
        }

        return $statements;
    }

    public function compileColumn(Column $column): string
    {
        $sql = $this->quote($column->name) . ' ' . $this->typeDefinition($column);

        if ($column->isAutoIncrement()) {
            $sql .= ' PRIMARY KEY AUTOINCREMENT';
        }

        $sql .= $this->compileColumnSuffix($column);

        return $sql;
    }

    private function typeDefinition(Column $column): string
    {
        return match ($column->baseType()) {
            Column::TYPE_ID => 'INTEGER',
            Column::TYPE_INT, Column::TYPE_INT_UNSIGNED => 'INTEGER',
            Column::TYPE_BIGINT, Column::TYPE_BIGINT_UNSIGNED => 'INTEGER',
            Column::TYPE_BOOL => 'INTEGER',
            Column::TYPE_STRING => 'VARCHAR(' . ($column->getLength() ?? 255) . ')',
            Column::TYPE_TEXT, Column::TYPE_LONGTEXT => 'TEXT',
            Column::TYPE_JSON => 'TEXT',
            Column::TYPE_DATETIME, Column::TYPE_TIMESTAMP => 'INTEGER',
            Column::TYPE_DECIMAL => 'NUMERIC',
            Column::TYPE_FLOAT => 'REAL',
            default => 'TEXT',
        };
    }

    public function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function indexName(string $name): string
    {
        return substr(strtolower((string) preg_replace('/[^A-Za-z0-9_]/', '_', $name)), 0, 60);
    }
}
