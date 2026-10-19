<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema;

use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Database\Exception\SchemaException;
use SoulCMS\Database\Schema\Grammar\MySqlSchemaGrammar;
use SoulCMS\Database\Schema\Grammar\PostgresSchemaGrammar;
use SoulCMS\Database\Schema\Grammar\SchemaGrammar;
use SoulCMS\Database\Schema\Grammar\SQLiteSchemaGrammar;

/**
 * Creates and inspects database structures.
 */
final class SchemaBuilder
{
    private SchemaGrammar $grammar;

    public function __construct(private Connection $connection)
    {
        $this->grammar = $this->makeGrammar($connection->driver());
    }

    private function makeGrammar(Driver $driver): SchemaGrammar
    {
        return match ($driver) {
            Driver::Sqlite => new SQLiteSchemaGrammar(),
            Driver::MySql, Driver::MariaDb => new MySqlSchemaGrammar(),
            Driver::Postgres => new PostgresSchemaGrammar(),
        };
    }

    public function grammar(): SchemaGrammar
    {
        return $this->grammar;
    }

    public function hasTable(string $table): bool
    {
        return $this->connection->tableExists($table);
    }

    /**
     * @return list<string>
     */
    public function getColumnListing(string $table): array
    {
        return $this->connection->listColumns($table);
    }

    public function create(TableDefinition $table): void
    {
        foreach ($this->grammar->compileCreateStatements($table) as $statement) {
            $this->connection->query($statement);
        }
    }

    public function drop(string $table): void
    {
        $this->connection->query($this->grammar->compileDrop($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->drop($table);
    }

    /**
     * Create the table only when it does not exist yet.
     */
    public function createIfNotExists(TableDefinition $table): bool
    {
        if ($this->hasTable($table->name)) {
            return false;
        }

        $this->create($table);

        return true;
    }

    /**
     * Create every table of a schema definition, skipping the ones that are
     * already present. Returns the list of created tables.
     *
     * @param list<TableDefinition> $tables
     * @return list<string>
     */
    public function createSchema(array $tables, bool $dropExisting = false): array
    {
        $created = [];

        foreach ($tables as $table) {
            if ($dropExisting && $this->hasTable($table->name)) {
                $this->drop($table->name);
            }

            if ($this->createIfNotExists($table)) {
                $created[] = $table->name;
            }
        }

        return $created;
    }

    /**
     * Verify that the given tables exist with the expected columns.
     *
     * @param list<TableDefinition> $tables
     * @return list<string> list of problems (empty when the schema matches)
     */
    public function verify(array $tables): array
    {
        $problems = [];

        foreach ($tables as $table) {
            if (!$this->hasTable($table->name)) {
                $problems[] = sprintf('Missing table "%s".', $table->name);

                continue;
            }

            $existing = array_map('strtolower', $this->getColumnListing($table->name));
            $expected = array_map('strtolower', array_keys($table->columns()));

            foreach ($expected as $column) {
                if (!in_array($column, $existing, true)) {
                    $problems[] = sprintf('Missing column "%s"."%s".', $table->name, $column);
                }
            }
        }

        return $problems;
    }

    public function assertTableExists(string $table): void
    {
        if (!$this->hasTable($table)) {
            throw new SchemaException(sprintf('Table "%s" does not exist.', $table));
        }
    }
}
