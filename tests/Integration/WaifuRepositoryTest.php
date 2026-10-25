<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

final class WaifuRepositoryTest extends TestCase
{
    public function testOwnerVisibilityCanBeToggledUsingExistingCloseColumn(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY, user_id INTEGER, header TEXT, rleft TEXT, rright TEXT, close INTEGER)');
        $connection->query("INSERT INTO users_waifu VALUES (1, 7, '/images/header.png', '/images/rleft.png', '/images/rright.png', 0)");
        $users = new UserRepository($connection);

        self::assertTrue($users->setWaifuClosed(7, true));
        self::assertSame(1, (int) $connection->scalar('SELECT `close` FROM `users_waifu` WHERE `user_id` = 7'));
        self::assertTrue($users->setWaifuClosed(7, false));
        self::assertSame(0, (int) $connection->scalar('SELECT `close` FROM `users_waifu` WHERE `user_id` = 7'));
        self::assertFalse($users->setWaifuClosed(8, true));
    }
}
