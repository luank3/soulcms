<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Controllers\ArticleController;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Mail\Repositories\MailRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Events\PostLiked;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Events\Dispatcher;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class ArticleLikeTest extends TestCase
{
    private Connection $connection;
    private ArticleRepository $articles;

    protected function setUp(): void
    {
        $this->connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $this->connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, author TEXT, title TEXT, slug TEXT, content TEXT, meta_data TEXT, time INTEGER, update_time INTEGER, category INTEGER, view INTEGER, blocked INTEGER, sticked INTEGER, buyfile INTEGER, `like` TEXT)');
        $this->connection->query("INSERT INTO articles_post (id, author, title, slug, content, meta_data, time, update_time, category, view, blocked, sticked, buyfile, `like`) VALUES (1, 'alice', 'First', 'first', '', NULL, 1, 1, 1, 0, 0, 0, 0, 'bob'), (2, 'carol', 'Second', 'second', '', NULL, 1, 1, 1, 0, 0, 0, 0, NULL)");
        $this->articles = new ArticleRepository($this->connection);
    }

    #[RunInSeparateProcess]
    public function testAuthenticatedMemberCanLikePostOnceAndAuthorGetsSystemNotice(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSPostLikeTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-post-like-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS post like test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $this->connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $this->connection->query('CREATE TABLE articles_chap (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, time INTEGER, box INTEGER, author TEXT)');
        $this->connection->query('CREATE TABLE articles_cmt (id INTEGER PRIMARY KEY, blogid TEXT, author TEXT, comment TEXT, time INTEGER)');
        $this->connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER)');
        $this->connection->query('CREATE TABLE articles_tags (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, count INTEGER)');
        $this->connection->query('CREATE TABLE articles_post_tags (post_id INTEGER, tag_id INTEGER)');
        $this->connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, level INTEGER, reg INTEGER, karma INTEGER, xu INTEGER, avatar TEXT, cover TEXT, sex TEXT, `on` INTEGER DEFAULT 0, new_mail TEXT)');
        $this->connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, header INTEGER, rleft INTEGER, rright INTEGER, close INTEGER)');
        $this->connection->query('CREATE TABLE mail (id INTEGER PRIMARY KEY AUTOINCREMENT, sender_receiver TEXT, nick TEXT, content TEXT, time INTEGER, update_time INTEGER, type TEXT, view TEXT)');
        $this->connection->query("INSERT INTO articles_post (id, author, title, slug, content, meta_data, time, update_time, category, view, blocked, sticked, buyfile, `like`) VALUES (10, 'bob', 'Test post', 'test-post', 'Content', NULL, 1, 1, 1, 0, 0, 0, 0, NULL)");
        $this->connection->query("UPDATE articles_post SET `like` = NULL WHERE id = 1");
        $this->connection->query("INSERT INTO users (id, nick, name, email, level, reg, karma, xu) VALUES (1, 'alice', 'Alice', '', 0, 1, 0, 0), (2, 'bob', 'Bob', '', 0, 1, 0, 0), (3, 'carol', 'Carol', '', 0, 1, 0, 0)");
        $session = new SessionManager($sessionName);
        $session->put('uid', 1);
        $request = new Request('POST', Uri::fromString('/articles/10-test-post.html'), server: [
            'HTTP_USER_AGENT' => 'SoulCMS post like test',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ], parsedBody: ['_token' => $session->token(), 'action' => 'like']);
        $config = new Config(['app' => ['name' => 'SoulCMS', 'url' => ['path' => ''], 'session' => ['name' => $sessionName], 'content' => ['system_bot' => 'SystemBOT']]]);
        $auth = new AuthManager($this->connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-post-like-views', true));
        $events = new Dispatcher();
        $events->listen(PostLiked::class, new \SoulCMS\App\Listeners\NotifyPostAuthorOfLike($this->connection, $config));
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'auth' => $auth,
            'events' => $events,
            AuthManager::class => $auth,
            'session' => $session,
            SessionManager::class => $session,
            'view' => $views,
            ViewFactory::class => $views,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $key => $value) {
            $container->instance($key, $value);
        }
        $controller = new ArticleController(
            $container,
            $this->articles,
            new UserRepository($this->connection),
            $this->connection,
            $events,
            new SessionManager(),
        );
        $actionRequest = $request->withAttribute('routeParams', ['postSlug' => '10-test-post']);
        $controller->setActionRequest($actionRequest);

        try {
            self::assertSame(1, $session->peek('uid'), 'The session uid was not restored.');
            self::assertTrue($auth->check(), 'The authenticated session did not resolve.');
            $response = $controller->postDetail($actionRequest);

            self::assertSame(302, $response->getStatusCode(), $response->getBody());
            self::assertSame('/articles/10-test-post.html', $response->getHeader('Location'));
            self::assertSame('alice', $this->connection->scalar('SELECT `like` FROM `articles_post` WHERE `id` = 10'));
            self::assertSame(0, (int) $this->connection->scalar('SELECT `view` FROM `articles_post` WHERE `id` = 10'));
            self::assertSame(1, (int) $this->connection->scalar('SELECT COUNT(*) FROM `mail` WHERE `type` = ?', ['system']));

            $repeat = new Request('POST', Uri::fromString('/articles/10-test-post.html'), server: [
                'HTTP_USER_AGENT' => 'SoulCMS post like test',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ], parsedBody: ['_token' => $session->token(), 'action' => 'like']);
            $controller->setActionRequest($repeat->withAttribute('routeParams', ['postSlug' => '10-test-post']));
            $controller->postDetail($repeat);
            self::assertSame('alice', $this->connection->scalar('SELECT `like` FROM `articles_post` WHERE `id` = 10'));
            self::assertSame(1, (int) $this->connection->scalar('SELECT COUNT(*) FROM `mail` WHERE `type` = ?', ['system']));
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

    #[RunInSeparateProcess]
    public function testLikeControlsAppearOnlyForAuthenticatedNonAuthors(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSPostLikeViewTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-post-like-view-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS post like view test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $this->connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $this->connection->query('CREATE TABLE articles_chap (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, time INTEGER, box INTEGER, author TEXT)');
        $this->connection->query('CREATE TABLE articles_cmt (id INTEGER PRIMARY KEY, blogid TEXT, author TEXT, comment TEXT, time INTEGER)');
        $this->connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER)');
        $this->connection->query('CREATE TABLE articles_tags (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, count INTEGER)');
        $this->connection->query('CREATE TABLE articles_post_tags (post_id INTEGER, tag_id INTEGER)');
        $this->connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, level INTEGER, reg INTEGER, karma INTEGER, xu INTEGER, avatar TEXT, cover TEXT, sex TEXT, `on` INTEGER DEFAULT 0, new_mail TEXT)');
        $this->connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, header INTEGER, rleft INTEGER, rright INTEGER, close INTEGER)');
        $this->connection->query("INSERT INTO users (id, nick, name, email, level, reg, karma, xu) VALUES (1, 'alice', 'Alice', '', 0, 1, 0, 0), (2, 'bob', 'Bob', '', 0, 1, 0, 0), (3, 'carol', 'Carol', '', 0, 1, 0, 0)");
        $session = new SessionManager($sessionName);
        $config = new Config(['app' => ['name' => 'SoulCMS', 'url' => ['path' => ''], 'session' => ['name' => $sessionName]]]);
        $request = new Request('GET', Uri::fromString('/articles/1-first.html'));
        $auth = new AuthManager($this->connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-post-like-view-views', true));
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'auth' => $auth,
            AuthManager::class => $auth,
            'session' => $session,
            SessionManager::class => $session,
            'view' => $views,
            ViewFactory::class => $views,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $key => $value) {
            $container->instance($key, $value);
        }
        $controller = new ArticleController(
            $container,
            $this->articles,
            new UserRepository($this->connection),
            $this->connection,
            new Dispatcher(),
            $session,
        );

        try {
            $guestRequest = $request->withAttribute('routeParams', ['postSlug' => '1-first']);
            $controller->setActionRequest($guestRequest);
            $guestResponse = $controller->postDetail($guestRequest);
            self::assertStringNotContainsString('Thích bài viết', $guestResponse->getBody());

            $session->put('uid', 3);
            $memberRequest = new Request('GET', Uri::fromString('/articles/1-first.html'));
            $memberAuth = new AuthManager($this->connection, $memberRequest, new PasswordHasher(), new Clock(), $session, $config);
            self::assertSame('carol', $memberAuth->user()->nick());
            $container->instance('auth', $memberAuth);
            $container->instance(AuthManager::class, $memberAuth);
            $memberController = new ArticleController(
                $container,
                $this->articles,
                new UserRepository($this->connection),
                $this->connection,
                new Dispatcher(),
                new SessionManager(),
            );
            $memberController->setActionRequest($memberRequest->withAttribute('routeParams', ['postSlug' => '1-first']));
            $memberResponse = $memberController->postDetail($memberRequest);
            self::assertStringContainsString('name="action" value="like"', $memberResponse->getBody());

            $session->put('uid', 1);
            $authorRequest = new Request('GET', Uri::fromString('/articles/1-first.html'));
            $authorAuth = new AuthManager($this->connection, $authorRequest, new PasswordHasher(), new Clock(), $session, $config);
            self::assertSame('alice', $authorAuth->user()->nick());
            $container->instance('auth', $authorAuth);
            $container->instance(AuthManager::class, $authorAuth);
            $authorController = new ArticleController(
                $container,
                $this->articles,
                new UserRepository($this->connection),
                $this->connection,
                new Dispatcher(),
                new SessionManager(),
            );
            $authorController->setActionRequest($authorRequest->withAttribute('routeParams', ['postSlug' => '1-first']));
            $authorResponse = $authorController->postDetail($authorRequest);
            self::assertStringNotContainsString('Thích bài viết', $authorResponse->getBody());
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

    public function testLikeIsAddedOnlyOnceAndCannotBeAddedByTheAuthor(): void
    {
        self::assertTrue($this->articles->addPostLike(1, 'dave'));
        self::assertFalse($this->articles->addPostLike(1, 'DAVE'));
        self::assertFalse($this->articles->addPostLike(1, 'ALICE'));
        self::assertFalse($this->articles->addPostLike(404, 'dave'));
        self::assertSame('dave.bob', $this->connection->scalar('SELECT `like` FROM `articles_post` WHERE `id` = 1'));
    }

    public function testLikeSummaryCountsExistingLikesAndChecksViewerCaseInsensitively(): void
    {
        self::assertSame(['count' => 1, 'liked' => true], $this->articles->postLikeSummary(1, 'BOB'));
        self::assertSame(['count' => 0, 'liked' => false], $this->articles->postLikeSummary(2, 'alice'));
        self::assertSame(['count' => 0, 'liked' => false], $this->articles->postLikeSummary(404, 'alice'));
    }
}
