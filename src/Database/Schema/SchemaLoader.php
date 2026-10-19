<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema;

use SoulCMS\Foundation\Paths;

/**
 * Loads the portable schema definitions from disk.
 */
final class SchemaLoader
{
    /**
     * @return list<TableDefinition>
     */
    public static function load(?string $directory = null): array
    {
        $directory = $directory ?? Paths::database('schema');
        $tables = [];

        if (!is_dir($directory)) {
            return $tables;
        }

        $files = glob($directory . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            /** @var array<string,TableDefinition> $definition */
            $definition = require $file;

            foreach ($definition as $table) {
                if ($table instanceof TableDefinition) {
                    $tables[] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * @return array<string,TableDefinition>
     */
    public static function loadMap(?string $directory = null): array
    {
        $map = [];

        foreach (self::load($directory) as $table) {
            $map[$table->name] = $table;
        }

        return $map;
    }
}
