<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema\Grammar;

use SoulCMS\Database\Schema\Column;
use SoulCMS\Database\Schema\TableDefinition;

/**
 * PostgreSQL DDL generator.
 */
final class PostgresSchemaGrammar extends SchemaGrammar
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

        if ($primaryKey !== []) {
            $definitions[] = '  PRIMARY KEY (' . implode(', ', array_map(fn (string $c): string => $this->quote($c), $primaryKey)) . ')';
        }

        foreach ($table->getUnique() as $unique) {
            $definitions[] = sprintf(
                '  CONSTRAINT %s UNIQUE (%s)',
                $this->quote($unique['name'] ?? $this->indexName($table->name . '_' . implode('_', $unique['columns']) . '_unique')),
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
                'CREATE INDEX %s ON %s (%s)',
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
            $sql = $this->quote($column->name) . ' SERIAL';
        }

        $sql .= $this->compileColumnSuffix($column);

        return $sql;
    }

    private function typeDefinition(Column $column): string
    {
        return match ($column->baseType()) {
            Column::TYPE_ID => 'SERIAL',
            Column::TYPE_INT, Column::TYPE_INT_UNSIGNED => 'INTEGER',
            Column::TYPE_BIGINT, Column::TYPE_BIGINT_UNSIGNED => 'BIGINT',
            Column::TYPE_BOOL => 'BOOLEAN',
            Column::TYPE_STRING => 'VARCHAR(' . ($column->getLength() ?? 255) . ')',
            Column::TYPE_TEXT, Column::TYPE_LONGTEXT => 'TEXT',
            Column::TYPE_JSON => 'JSONB',
            Column::TYPE_DATETIME => 'TIMESTAMP(0)',
            Column::TYPE_TIMESTAMP => 'TIMESTAMP(0)',
            Column::TYPE_DECIMAL => 'NUMERIC(' . $column->getPrecision() . ', ' . $column->getScale() . ')',
            Column::TYPE_FLOAT => 'DOUBLE PRECISION',
            default => 'VARCHAR(255)',
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
