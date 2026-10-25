<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

final class UserOnlineStatusTest extends TestCase
{
    public function testStatusUsesTheSameFiveMinuteWindowAsTheOnlineRoster(): void
    {
        $now = time();
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, `on` INTEGER)');
        $connection->query('INSERT INTO users VALUES (1, "recent", ?), (2, "stale", ?), (3, "never", 0)', [
            $now - 299,
            $now - 301,
        ]);
        $users = new UserRepository($connection);

        self::assertTrue($users->isOnline(1, 300, $now));
        self::assertFalse($users->isOnline(2, 300, $now));
        self::assertFalse($users->isOnline(3, 300, $now));
    }

    public function testOnlineRosterUsesInjectedTimeAndSameWindow(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, avatar TEXT, level INTEGER, `on` INTEGER)');
        $now = time();
        $connection->query('INSERT INTO users VALUES (1, "recent", "15", 2, ?), (2, "old", "15", 2, ?)', [
            $now - 120,
            $now - 900,
        ]);
        $users = new UserRepository($connection);

        self::assertSame(['recent'], array_column($users->onlineUsers(20, 300, $now), 'nick'));
    }
}
