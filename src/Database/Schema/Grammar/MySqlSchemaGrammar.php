<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema\Grammar;

use SoulCMS\Database\Schema\Column;
use SoulCMS\Database\Schema\TableDefinition;

/**
 * MySQL / MariaDB DDL generator.
 */
final class MySqlSchemaGrammar extends SchemaGrammar
{
    public function compileCreate(TableDefinition $table): string
    {
        $statements = $this->compileCreateStatements($table);

        return implode("\n", $statements) . ';';
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
                '  UNIQUE KEY %s (%s)',
                $this->quote($unique['name'] ?? $this->indexName($table->name . '_' . implode('_', $unique['columns']) . '_unique')),
                implode(', ', array_map(fn (string $c): string => $this->quote($c), $unique['columns']))
            );
        }

        foreach ($table->getIndexes() as $index) {
            $definitions[] = sprintf(
                '  KEY %s (%s)',
                $this->quote($index['name'] ?? $this->indexName($table->name . '_' . implode('_', $index['columns']) . '_index')),
                implode(', ', array_map(fn (string $c): string => $this->quote($c), $index['columns']))
            );
        }

        $sql = sprintf(
            "CREATE TABLE %s (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s",
            $this->quote($table->name),
            implode(",\n", $definitions),
            $this->charset,
            $this->collation
        );

        $statements = [$sql];

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
            $sql .= ' AUTO_INCREMENT';
        }

        $sql .= $this->compileColumnSuffix($column);

        if ($column->getCollation() !== null) {
            $sql .= ' COLLATE ' . $column->getCollation();
        }

        return $sql;
    }

    private function typeDefinition(Column $column): string
    {
        return match ($column->baseType()) {
            Column::TYPE_ID => 'INT UNSIGNED',
            Column::TYPE_INT => 'INT',
            Column::TYPE_INT_UNSIGNED => 'INT UNSIGNED',
            Column::TYPE_BIGINT => 'BIGINT',
            Column::TYPE_BIGINT_UNSIGNED => 'BIGINT UNSIGNED',
            Column::TYPE_BOOL => 'TINYINT(1)',
            Column::TYPE_STRING => 'VARCHAR(' . ($column->getLength() ?? 255) . ')',
            Column::TYPE_TEXT => 'TEXT',
            Column::TYPE_LONGTEXT => 'LONGTEXT',
            Column::TYPE_JSON => 'JSON',
            Column::TYPE_DATETIME => 'DATETIME',
            Column::TYPE_TIMESTAMP => 'TIMESTAMP',
            Column::TYPE_DECIMAL => 'DECIMAL(' . $column->getPrecision() . ', ' . $column->getScale() . ')',
            Column::TYPE_FLOAT => 'DOUBLE',
            default => 'VARCHAR(255)',
        };
    }

    public function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function indexName(string $name): string
    {
        return substr(strtolower((string) preg_replace('/[^A-Za-z0-9_]/', '_', $name)), 0, 60);
    }
}
