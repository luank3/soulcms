<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Listeners\NotifyPostAuthor;
use SoulCMS\App\Listeners\NotifyPostAuthorOfLike;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Events\PostLiked;
use SoulCMS\Foundation\Config;

final class PostLikeNotificationTest extends TestCase
{
    public function testLikeNotifiesThePostAuthorThroughSystemMail(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY AUTOINCREMENT, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $listener = new NotifyPostAuthorOfLike(
            $connection,
            new Config(['app' => ['content' => ['system_bot' => 'SystemBOT']]]),
        );

        $listener(new PostLiked(5, 'alice', 'bob', 'Interesting post'));

        $message = $connection->selectOne('SELECT * FROM `mail` WHERE `id` = 1');
        self::assertSame('bob_systembot', $message['sender_receiver']);
        self::assertSame('SystemBOT', $message['nick']);
        self::assertSame('system', $message['type']);
        self::assertSame('no', $message['view']);
        self::assertStringContainsString('@alice', $message['content']);
        self::assertStringContainsString('Interesting post', $message['content']);
    }

    public function testLikeNotificationUsesTheActiveLocale(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY AUTOINCREMENT, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $listener = new NotifyPostAuthorOfLike($connection, new Config());

        \SoulCMS\Support\Lang::setLocale('en');
        try {
            $listener(new PostLiked(5, 'alice', 'bob', 'Interesting post'));
            $message = $connection->selectOne('SELECT * FROM `mail` WHERE `id` = 1');
            self::assertSame('@alice liked your post "Interesting post".', $message['content']);
        } finally {
            \SoulCMS\Support\Lang::setLocale('vi');
        }
    }

    public function testModuleMetadataUsesTheActiveLocale(): void
    {
        $module = new \SoulCMS\App\Modules\Articles\Module();

        \SoulCMS\Support\Lang::setLocale('en');
        try {
            self::assertSame('Articles', $module->title());
            self::assertSame('Posts, chapters, comments, tags and attachments.', $module->description());
        } finally {
            \SoulCMS\Support\Lang::setLocale('vi');
        }
    }

    public function testLikeDoesNotNotifyItsOwnAuthor(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY AUTOINCREMENT, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $listener = new NotifyPostAuthorOfLike($connection, new Config());

        $listener(new PostLiked(5, 'Alice', 'alice', 'My post'));

        self::assertSame(0, (int) $connection->scalar('SELECT COUNT(*) FROM `mail`'));
    }
}
