<?php

declare(strict_types=1);

namespace SoulCMS\Database;

/**
 * Supported database drivers.
 */
enum Driver: string
{
    case Sqlite = 'sqlite';
    case MySql = 'mysql';
    case MariaDb = 'mariadb';
    case Postgres = 'pgsql';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            'sqlite', 'sqlite3' => self::Sqlite,
            'mysql', 'mysqli' => self::MySql,
            'mariadb' => self::MariaDb,
            'pgsql', 'postgres', 'postgresql' => self::Postgres,
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported database driver "%s". Supported: %s.',
                $name,
                implode(', ', self::names())
            )),
        };
    }

    /**
     * PDO driver identifier.
     */
    public function pdoDriver(): string
    {
        return match ($this) {
            self::Sqlite => 'sqlite',
            self::MySql, self::MariaDb => 'mysql',
            self::Postgres => 'pgsql',
        };
    }

    /**
     * The DSN scheme used to build the connection string.
     */
    public function dsnScheme(): string
    {
        return $this->pdoDriver();
    }

    public function supportsReturning(): bool
    {
        return $this === self::Postgres || $this === self::Sqlite;
    }

    public function isSqlite(): bool
    {
        return $this === self::Sqlite;
    }
}
