<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Mail\Repositories\MailRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

final class MailSystemTest extends TestCase
{
    public function testSystemNotificationClearRouteIsPostOnly(): void
    {
        $router = new \SoulCMS\Routing\Router();
        (new \SoulCMS\App\Modules\Mail\Module())->routes($router);
        $route = $router->getCollection()->get('mail.system.clear');

        self::assertNotNull($route);
        self::assertSame('/mail/system/clear', $route->getPattern());
        self::assertSame(['POST'], $route->getMethods());
    }

    public function testMarkSystemNotificationsReadWithoutChangingPrivateMessages(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $connection->query("INSERT INTO mail VALUES (1, 'alice_systembot', 'SystemBOT', 'notice', 1, 1, 'system', 'no'), (2, 'bob_systembot', 'SystemBOT', 'other notice', 2, 2, 'system', 'no'), (3, 'alice_bob', 'bob', 'private', 3, 3, 'private', 'no')");
        $mail = new MailRepository($connection);

        $mail->markSystemRead('alice', 'SystemBOT');

        self::assertSame('yes', $connection->scalar('SELECT `view` FROM `mail` WHERE `id` = 1'));
        self::assertSame('no', $connection->scalar('SELECT `view` FROM `mail` WHERE `id` = 2'));
        self::assertSame('no', $connection->scalar('SELECT `view` FROM `mail` WHERE `id` = 3'));
    }

    public function testClearSystemNotificationsWithoutDeletingPrivateMessages(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $connection->query("INSERT INTO mail VALUES (1, 'alice_systembot', 'SystemBOT', 'notice', 1, 1, 'system', 'no'), (2, 'bob_systembot', 'SystemBOT', 'other notice', 2, 2, 'system', 'no'), (3, 'alice_bob', 'bob', 'private', 3, 3, 'private', 'no')");
        $mail = new MailRepository($connection);

        $mail->clearSystem('alice', 'SystemBOT');

        self::assertSame(0, (int) $connection->scalar('SELECT COUNT(*) FROM `mail` WHERE `type` = ? AND `sender_receiver` = ?', ['system', 'alice_systembot']));
        self::assertSame(2, (int) $connection->scalar('SELECT COUNT(*) FROM `mail`'));
    }
}
