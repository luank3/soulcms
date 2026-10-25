<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Mail\Repositories\MailRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Support\Lang;

final class MailLatestMessageTest extends TestCase
{
    public function testInboxRendersLatestMessageSafely(): void
    {
        $previous = \SoulCMS\Foundation\Container::getInstance();
        $container = new \SoulCMS\Foundation\Container();
        \SoulCMS\Foundation\Container::swap($container);
        $container->instance('config', new \SoulCMS\Foundation\Config(['app' => ['url' => ['path' => '']]]));
        $session = new \SoulCMS\Http\Session\SessionManager();
        $container->instance('session', $session);
        $container->instance('auth', new \SoulCMS\Auth\AuthManager(
            new Connection(new PDO('sqlite::memory:'), Driver::Sqlite),
            new \SoulCMS\Http\Request('GET', \SoulCMS\Http\Uri::fromString('/mail')),
            new \SoulCMS\Auth\PasswordHasher(),
            new \SoulCMS\Foundation\Clock(),
            $session,
            new \SoulCMS\Foundation\Config(['app' => ['url' => ['path' => '']]]),
        ));
        $container->instance('csp', new \SoulCMS\Http\Csp());
        $views = new \SoulCMS\View\ViewFactory(new \SoulCMS\View\Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-latest-mail-views', true));
        $container->instance('view', $views);

        Lang::setLocale('en');
        try {
            $html = $views->render('mail/index', [
                'messages' => [],
                'partners' => ['bob'],
                'partner' => '',
                'current_page' => 1,
                'latest' => [
                    'bob' => ['nick' => 'bob', 'content' => '<script>alert(1)</script>', 'time' => 123, 'view' => 'no'],
                ],
            ]);
            $empty = $views->render('mail/index', [
                'messages' => [],
                'partners' => [],
                'partner' => '',
                'latest' => [],
            ]);
            $systemNotifications = $views->render('mail/system', [
                'messages' => [['time' => 123, 'content' => 'A notification']],
            ]);
            $emptyNotifications = $views->render('mail/system', ['messages' => []]);
            $compose = $views->render('mail/send', [
                'receiver' => ['nick' => 'bob'],
                'errors' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
            \SoulCMS\Foundation\Container::swap($previous);
        }

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('Unread', $html);
        self::assertStringContainsString('Messages', $html);
        self::assertStringContainsString('1 conversation', $html);
        self::assertStringContainsString('Open', $html);
        self::assertStringContainsString('Reply', $html);
        self::assertStringContainsString('No conversations yet', $empty);
        self::assertStringContainsString('System notifications', $systemNotifications);
        self::assertStringContainsString('Clear all', $systemNotifications);
        self::assertStringContainsString('System', $systemNotifications);
        self::assertStringContainsString('No notifications', $emptyNotifications);
        self::assertStringContainsString('You will be notified when someone comments on your posts.', $emptyNotifications);
        self::assertStringContainsString('Send a message to @bob', $compose);
        self::assertStringContainsString('Message', $compose);
        self::assertStringContainsString('Write your message…', $compose);
        self::assertStringContainsString('Send', $compose);
        self::assertStringContainsString('Cancel', $compose);
    }

    public function testLatestDetailsAreFetchedAndOrderedByNewestMessage(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $connection->query("INSERT INTO mail VALUES (1, 'alice_bob', 'alice', 'first', 1, 1, 'private', 'yes'), (2, 'alice_bob', 'bob', 'latest', 2, 2, 'private', 'no'), (3, 'alice_carol', 'carol', 'hello', 3, 3, 'private', 'yes'), (4, 'alice_dave', 'dave', 'not requested', 4, 4, 'private', 'no')");
        $mail = new MailRepository($connection);

        $latest = $mail->latestDetails('alice', ['bob', 'carol']);

        self::assertSame('latest', $latest['bob']['content']);
        self::assertSame('bob', $latest['bob']['nick']);
        self::assertSame('hello', $latest['carol']['content']);
        self::assertArrayNotHasKey('dave', $latest);
    }
}
