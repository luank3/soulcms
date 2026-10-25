<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Mail\Controllers\MailController;
use SoulCMS\App\Modules\Mail\Repositories\MailRepository;
use SoulCMS\App\Modules\User\Controllers\UserController;
use SoulCMS\App\Modules\User\Module;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Events\Dispatcher;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Support\RateLimiter;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Routing\Router;

final class UserBlocklistTest extends TestCase
{
    public function testMarkIncomingConversationMessagesRead(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $connection->query("INSERT INTO mail VALUES (1, 'alice_bob', 'bob', 'incoming', 1, 1, 'private', 'no'), (2, 'bob_alice', 'alice', 'sent', 2, 2, 'private', 'yes'), (3, 'bob_carol', 'carol', 'other', 3, 3, 'private', 'no')");
        $mail = new MailRepository($connection);

        $mail->markConversationRead('alice', 'bob');

        self::assertSame('yes', $connection->scalar('SELECT `view` FROM `mail` WHERE `id` = 1'));
        self::assertSame('yes', $connection->scalar('SELECT `view` FROM `mail` WHERE `id` = 2'));
        self::assertSame('no', $connection->scalar('SELECT `view` FROM `mail` WHERE `id` = 3'));
    }

    public function testBlocklistStoresUniqueNicknamesAndCanRemoveThem(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, blocklist TEXT)');
        $connection->query("INSERT INTO users VALUES (1, 'alice', 'bob.carol')");
        $users = new UserRepository($connection);

        $users->blockUser(1, 'bob');
        $users->blockUser(1, 'dave');

        self::assertSame(['bob', 'carol', 'dave'], $users->blocklist(1));

        $users->unblockUser(1, 'carol');
        self::assertSame(['bob', 'dave'], $users->blocklist(1));
    }

    public function testBlocklistRouteIsRegistered(): void
    {
        $router = new Router();
        (new Module())->routes($router);
        $route = $router->getCollection()->get('user.blocklist');

        self::assertNotNull($route);
        self::assertSame('/user/{account:[a-zA-Z0-9\\-_]+}/blocklist', $route->getPattern());
        self::assertSame(['GET', 'POST'], $route->getMethods());
    }
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testOwnerCanBlockAndUnblockMembersFromTheList(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSBlocklistOwnerTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-block-owner-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS blocklist owner test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, pass TEXT, level INTEGER, status TEXT, `on` INTEGER, remember_token TEXT, new_mail TEXT, mail_list TEXT, blocklist TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, author TEXT)');
        $connection->query('CREATE TABLE articles_cmt (id INTEGER PRIMARY KEY, author TEXT)');
        $connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY)');
        $connection->query("INSERT INTO users (id,nick,name,email,pass,level,`on`,new_mail,mail_list,blocklist) VALUES (1,'alice','Alice','alice@example.test','x',0,0,'','',''),(2,'bob','Bob','bob@example.test','x',0,0,'','','')");
        $storedSession = new SessionManager($sessionName);
        $storedSession->put('uid', 1);
        $sessionId = $storedSession->getId();
        session_write_close();
        session_id('');
        $_SESSION = [];
        $_COOKIE = [$sessionName => $sessionId];
        session_id($sessionId);
        $request = new Request('GET', Uri::fromString('/user/alice/blocklist'), cookies: [$sessionName => $sessionId], server: [
            'HTTP_USER_AGENT' => 'SoulCMS blocklist owner test',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);
        $config = new Config(['app' => ['url' => ['path' => ''], 'session' => ['name' => $sessionName]]]);
        $session = new SessionManager($sessionName);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $view = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-block-owner-views', true));
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'session' => $session,
            SessionManager::class => $session,
            'csp' => new \SoulCMS\Http\Csp(),
            'auth' => $auth,
            AuthManager::class => $auth,
            'view' => $view,
            ViewFactory::class => $view,
        ] as $key => $value) {
            $container->instance($key, $value);
        }
        try {
            $controller = new UserController(
                $container,
                new UserRepository($connection),
                new ArticleRepository($connection),
                new PasswordHasher(),
                $session,
                new RateLimiter(sys_get_temp_dir() . '/soulcms-test-ratelimit', false),
            );
            $controller->setActionRequest($request->withAttribute('routeParams', ['account' => 'alice']));
            $page = $controller->blocklist($request);
            self::assertSame(200, $page->getStatusCode());
            self::assertStringContainsString('Danh sách chặn', $page->getBody());

            $post = new Request('POST', Uri::fromString('/user/alice/blocklist'), cookies: [$sessionName => $sessionId], server: [
                'HTTP_USER_AGENT' => 'SoulCMS blocklist owner test',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ], parsedBody: ['_token' => $session->token(), 'action' => 'block', 'nick' => 'bob']);
            $controller->setActionRequest($post->withAttribute('routeParams', ['account' => 'alice']));
            self::assertSame('/user/alice/blocklist', $controller->blocklist($post)->getHeader('Location'));
            self::assertSame(['bob'], (new UserRepository($connection))->blocklist(1));

            $unblock = new Request('POST', Uri::fromString('/user/alice/blocklist'), cookies: [$sessionName => $sessionId], server: [
                'HTTP_USER_AGENT' => 'SoulCMS blocklist owner test',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ], parsedBody: ['_token' => $session->token(), 'action' => 'unblock', 'nick' => 'bob']);
            $controller->setActionRequest($unblock->withAttribute('routeParams', ['account' => 'alice']));
            $controller->blocklist($unblock);
            self::assertSame([], (new UserRepository($connection))->blocklist(1));

            \SoulCMS\Support\Lang::setLocale('en');
            $selfBlock = new Request('POST', Uri::fromString('/user/alice/blocklist'), cookies: [$sessionName => $sessionId], server: [
                'HTTP_USER_AGENT' => 'SoulCMS blocklist owner test',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ], parsedBody: ['_token' => $session->token(), 'action' => 'block', 'nick' => 'alice']);
            $controller->setActionRequest($selfBlock->withAttribute('routeParams', ['account' => 'alice']));
            self::assertStringContainsString('You cannot block yourself.', $controller->blocklist($selfBlock)->getBody());
        } finally {
            \SoulCMS\Support\Lang::setLocale('vi');
            Container::swap($previous);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            $_COOKIE = [];
            foreach (glob($sessionPath . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($sessionPath);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testBlockedMemberCannotStartOrSendPrivateMessages(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSBlocklistMailTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-blocklist-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS blocklist test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, pass TEXT, level INTEGER, status TEXT, `on` INTEGER, remember_token TEXT, new_mail TEXT, mail_list TEXT, blocklist TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
        $connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY AUTOINCREMENT, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $connection->query("INSERT INTO users (id,nick,name,email,pass,level,`on`,new_mail,mail_list,blocklist) VALUES (1,'alice','Alice','alice@example.test','x',0,0,'','bob',''),(2,'bob','Bob','bob@example.test','x',0,0,'','', 'alice')");
        $storedSession = new SessionManager($sessionName);
        $storedSession->put('uid', 1);
        $sessionId = $storedSession->getId();
        session_write_close();
        session_id('');
        $_SESSION = [];
        $_COOKIE = [$sessionName => $sessionId];
        session_id($sessionId);
        $request = new Request('GET', Uri::fromString('/mail/send/bob'), cookies: [$sessionName => $sessionId], server: [
            'HTTP_USER_AGENT' => 'SoulCMS blocklist test',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);
        $config = new Config(['app' => ['url' => ['path' => ''], 'session' => ['name' => $sessionName]]]);
        $session = new SessionManager($sessionName);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $view = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-block-mail-views', true));
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'session' => $session,
            SessionManager::class => $session,
            'csp' => new \SoulCMS\Http\Csp(),
            'auth' => $auth,
            AuthManager::class => $auth,
            'view' => $view,
            ViewFactory::class => $view,
        ] as $key => $value) {
            $container->instance($key, $value);
        }
        try {
            $controller = new MailController(
                $container,
                new MailRepository($connection),
                new UserRepository($connection),
                $connection,
                new Dispatcher(),
            );
            $controller->setActionRequest($request->withAttribute('routeParams', ['receiver' => 'bob']));

            self::assertSame(403, $controller->send($request)->getStatusCode());
            self::assertSame(0, (int) $connection->scalar('SELECT COUNT(*) FROM `mail`'));

            $post = new Request('POST', Uri::fromString('/mail/send/bob'), cookies: [$sessionName => $sessionId], server: [
                'HTTP_USER_AGENT' => 'SoulCMS blocklist test',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ], parsedBody: ['content' => 'Hello']);
            $controller->setActionRequest($post->withAttribute('routeParams', ['receiver' => 'bob']));
            self::assertSame(403, $controller->send($post)->getStatusCode());
            self::assertSame(0, (int) $connection->scalar('SELECT COUNT(*) FROM `mail`'));
        } finally {
            Container::swap($previous);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            $_COOKIE = [];
            foreach (glob($sessionPath . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($sessionPath);
        }
    }

}
