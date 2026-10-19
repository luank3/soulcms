<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Database\Driver;
use SoulCMS\Database\Query\Grammar;
use SoulCMS\Database\Schema\Column;
use SoulCMS\Database\Schema\Grammar\MySqlSchemaGrammar;
use SoulCMS\Database\Schema\Grammar\PostgresSchemaGrammar;
use SoulCMS\Database\Schema\Grammar\SQLiteSchemaGrammar;
use SoulCMS\Database\Schema\TableDefinition;

final class SchemaTest extends TestCase
{
    public function testMySqlDdlMatchesTheSketchCmsShape(): void
    {
        $sql = (new MySqlSchemaGrammar())->compileCreate($this->usersTable());

        self::assertStringContainsString('CREATE TABLE `users`', $sql);
        self::assertStringContainsString('`id` INT UNSIGNED AUTO_INCREMENT NOT NULL', $sql);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        self::assertStringContainsString('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $sql);
        self::assertStringContainsString('`level` INT NOT NULL DEFAULT 0', $sql);
    }

    public function testPostgresDdlUsesSerialAndQuotedIdentifiers(): void
    {
        $sql = (new PostgresSchemaGrammar())->compileCreate($this->usersTable());

        self::assertStringContainsString('CREATE TABLE "users"', $sql);
        self::assertStringContainsString('"id" SERIAL', $sql);
        self::assertStringContainsString('PRIMARY KEY ("id")', $sql);
        self::assertStringNotContainsString('ENGINE=', $sql);
    }

    public function testSqliteDdlUsesIntegerPrimaryKeyAutoincrement(): void
    {
        $sql = (new SQLiteSchemaGrammar())->compileCreate($this->usersTable());

        self::assertStringContainsString('CREATE TABLE "users"', $sql);
        self::assertStringContainsString('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        // The primary key must not be declared twice.
        self::assertSame(1, substr_count($sql, 'PRIMARY KEY'));
    }

    public function testCompositePrimaryKeyIsSupported(): void
    {
        $table = (new TableDefinition('articles_post_tags'))
            ->column(Column::make('post_id', Column::TYPE_INT_UNSIGNED))
            ->column(Column::make('tag_id', Column::TYPE_INT_UNSIGNED))
            ->primaryKey('post_id', 'tag_id');

        $sql = (new MySqlSchemaGrammar())->compileCreate($table);

        self::assertStringContainsString('PRIMARY KEY (`post_id`, `tag_id`)', $sql);
    }

    public function testUniqueAndIndexConstraintsAreRendered(): void
    {
        $table = (new TableDefinition('articles_tags'))
            ->column(Column::id())
            ->column(Column::make('slug', Column::TYPE_STRING))
            ->unique(['slug'], 'articles_tags_slug_unique')
            ->index(['count']);

        $mysql = (new MySqlSchemaGrammar())->compileCreate($table);
        $postgres = (new PostgresSchemaGrammar())->compileCreate($table);

        self::assertStringContainsString('UNIQUE KEY `articles_tags_slug_unique` (`slug`)', $mysql);
        self::assertStringContainsString('CONSTRAINT "articles_tags_slug_unique" UNIQUE ("slug")', $postgres);
        self::assertStringContainsString('CREATE INDEX', $postgres);
    }

    public function testLimitSyntaxIsDriverSpecific(): void
    {
        $connection = $this->connectionFor(Driver::MySql);
        $mysql = new Grammar($connection);
        self::assertSame(' LIMIT 10', $mysql->compileLimit(10, 0));
        self::assertSame(' LIMIT 20, 10', $mysql->compileLimit(10, 20));

        $postgres = new Grammar($this->connectionFor(Driver::Postgres));
        self::assertSame(' LIMIT 10', $postgres->compileLimit(10, 0));
        self::assertSame(' LIMIT 10 OFFSET 20', $postgres->compileLimit(10, 20));

        $sqlite = new Grammar($this->connectionFor(Driver::Sqlite));
        self::assertSame(' LIMIT 10 OFFSET 20', $sqlite->compileLimit(10, 20));
    }

    private function usersTable(): TableDefinition
    {
        return (new TableDefinition('users'))
            ->column(Column::id())
            ->column(Column::make('nick', Column::TYPE_STRING)->length(255))
            ->column(Column::make('level', Column::TYPE_INT)->default(0));
    }

    private function connectionFor(Driver $driver): \SoulCMS\Database\Connection
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return new \SoulCMS\Database\Connection($pdo, $driver);
    }
}
