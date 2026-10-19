<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

final class MySqlConnectionTest extends TestCase
{
    public function testTableExistsUsesPreparedStatementsSupportedByMariaDb(): void
    {
        if (!extension_loaded('pdo_mysql') || getenv('SOULCMS_MYSQL_TEST') !== '1') {
            self::markTestSkipped('Set SOULCMS_MYSQL_TEST=1 with pdo_mysql to run this integration test.');
        }

        $host = (string) getenv('DB_HOST');
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $database = (string) getenv('DB_DATABASE');
        $username = (string) getenv('DB_USERNAME');
        $password = (string) getenv('DB_PASSWORD');

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
            [PDO::ATTR_EMULATE_PREPARES => false],
        );
        $connection = new Connection($pdo, Driver::MySql);

        self::assertTrue($connection->tableExists('users'));
        self::assertFalse($connection->tableExists('soulcms_missing_table'));
    }
}
