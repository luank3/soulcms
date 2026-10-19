<?php

declare(strict_types=1);

namespace SoulCMS\Database;

use SoulCMS\Database\Schema\SchemaBuilder;
use SoulCMS\Database\Schema\SchemaLoader;
use SoulCMS\Database\Schema\TableDefinition;

/**
 * Creates (or verifies) the CMS database structure on any supported driver.
 */
final class Installer
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Create every table of the schema. Existing tables are left untouched
     * unless $drop is true.
     *
     * @return list<string> names of the created tables
     */
    public function install(bool $drop = false): array
    {
        $builder = new SchemaBuilder($this->connection);

        return $builder->createSchema(SchemaLoader::load(), $drop);
    }

    /**
     * Compare the live database with the expected schema.
     *
     * @return list<string>
     */
    public function verify(): array
    {
        $builder = new SchemaBuilder($this->connection);

        return $builder->verify(SchemaLoader::load());
    }

    /**
     * @return list<TableDefinition>
     */
    public function tables(): array
    {
        return SchemaLoader::load();
    }
}
