<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Controllers\UserController;
use SoulCMS\App\Modules\User\Module;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Paths;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Routing\Router;
use SoulCMS\Support\RateLimiter;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class UserProfileEditTest extends TestCase
{
    public function testProfileShowsSavedWaifuDecorationAndUsesSafeAssetPath(): void
    {
        $previous = Container::getInstance();
        $previousBase = Paths::base();
        $base = sys_get_temp_dir() . '/soulcms-waifu-profile-' . bin2hex(random_bytes(5));
        mkdir($base . '/public/images', 0777, true);
        foreach (['header.png', 'rleft.png', 'rright.png'] as $image) {
            file_put_contents($base . '/public/images/' . $image, 'image');
        }
        Paths::setBase($base);
        $container = new Container();
        Container::swap($container);
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, level INTEGER, reg INTEGER, sex TEXT, avatar TEXT, cover TEXT, `on` INTEGER DEFAULT 0, new_mail TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY, user_id INTEGER, header TEXT, rleft TEXT, rright TEXT, close INTEGER)');
        $connection->query("INSERT INTO users VALUES (1, 'alice', 'Alice', '', 0, 0, 'girl', '', '', 0, '')");
        $connection->query("INSERT INTO users_waifu VALUES (1, 1, '/images/header.png', '/images/rleft.png', '/images/rright.png', 0)");
        $connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, author TEXT, title TEXT, slug TEXT, time INTEGER, update_time INTEGER, category INTEGER, view INTEGER, sticked INTEGER, blocked INTEGER)');
        $connection->query('CREATE TABLE articles_cmt (id INTEGER PRIMARY KEY, blogid TEXT, author TEXT, time INTEGER)');
        $request = new Request('GET', Uri::fromString('/user/alice'));
        $session = new SessionManager();
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-waifu-view-' . bin2hex(random_bytes(5)), true));
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
        ] as $id => $service) {
            $container->instance($id, $service);
        }

        try {
            $controller = new UserController($container, new UserRepository($connection), new ArticleRepository($connection), new PasswordHasher(), $session, new RateLimiter(sys_get_temp_dir() . '/waifu-test-' . bin2hex(random_bytes(5)), false));
            $controller->setActionRequest($request->withAttribute('routeParams', ['account' => 'alice']));
            $response = $controller->userDetail($request);

            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('/images/header.png', $response->getBody());
            self::assertStringContainsString('/images/rleft.png', $response->getBody());
            self::assertStringContainsString('/images/rright.png', $response->getBody());
            self::assertStringNotContainsString('Ẩn trang trí', $response->getBody());
            self::assertStringNotContainsString('Hiện trang trí', $response->getBody());

            $connection->query('UPDATE `users_waifu` SET `close` = 1 WHERE `user_id` = 1');
            $controller->setActionRequest($request->withAttribute('routeParams', ['account' => 'alice']));
            $hidden = $controller->userDetail($request)->getBody();
            self::assertStringNotContainsString('profile-hero__header', $hidden);
            self::assertStringNotContainsString('profile-hero__side--left', $hidden);
            self::assertStringNotContainsString('profile-hero__side--right', $hidden);
            self::assertStringNotContainsString('Hiện trang trí', $hidden);
        } finally {
            Container::swap($previous);
            Paths::setBase($previousBase);
            foreach (glob($base . '/public/images/*') ?: [] as $image) {
                unlink($image);
            }
            rmdir($base . '/public/images');
            rmdir($base . '/public');
            rmdir($base);
        }
    }

    public function testProfileEditRouteIsRegisteredBeforeTheProfileRoute(): void
    {
        $router = new Router();
        (new Module())->routes($router);
        $route = $router->getCollection()->get('user.edit');

        self::assertNotNull($route);
        self::assertSame('/user/{account:[a-zA-Z0-9\\-_]+}/info', $route->getPattern());
        self::assertSame(['GET', 'POST'], $route->getMethods());
    }

    #[RunInSeparateProcess]
    public function testProfileCanBeViewedAndUpdatedOnlyByItsOwner(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSProfileEditTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-profile-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS profile edit test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $config = new Config(['app' => ['url' => ['path' => ''], 'session' => ['name' => $sessionName]]]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, level INTEGER, reg INTEGER, sex TEXT, avatar TEXT, cover TEXT, `on` INTEGER DEFAULT 0, new_mail TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY, user_id INTEGER)');
        $connection->query("INSERT INTO users VALUES (1, 'alice', 'Alice', 'alice@example.test', 0, 0, 'girl', '15', '8', 0, '')");
        $sessionName = 'SoulCMSProfileEditTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-profile-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS profile edit test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $storedSession = new SessionManager($sessionName);
        $storedSession->put('uid', 1);
        $sessionId = $storedSession->getId();
        session_write_close();
        session_id('');
        $_SESSION = [];
        $_COOKIE = [$sessionName => $sessionId];
        session_id($sessionId);
        $session = new SessionManager($sessionName);
        $request = new Request(
            'GET',
            Uri::fromString('/user/alice/info'),
            cookies: [$sessionName => $sessionId],
            server: ['HTTP_USER_AGENT' => 'SoulCMS profile edit test', 'HTTP_ACCEPT_LANGUAGE' => 'en'],
        );
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $view = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-profile-edit-views', true));
        $container->instance('config', $config);
        $container->instance(Config::class, $config);
        $container->instance('request', $request);
        $container->instance(Request::class, $request);
        $container->instance('session', $session);
        $container->instance(SessionManager::class, $session);
        $container->instance('csp', new \SoulCMS\Http\Csp());
        $container->instance('auth', $auth);
        $container->instance(AuthManager::class, $auth);
        $container->instance('view', $view);
        $container->instance(ViewFactory::class, $view);

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
            $response = $controller->editProfile($request);

            self::assertSame(200, $response->getStatusCode(), $response->getBody());
            self::assertStringContainsString('action="/user/alice/info"', $response->getBody());
            self::assertStringContainsString('name="name"', $response->getBody());
            self::assertStringContainsString('name="email"', $response->getBody());

            $post = new Request('POST', Uri::fromString('/user/alice/info'), parsedBody: [
                '_token' => $session->token(),
                'name' => 'Alice Updated',
                'email' => 'new@example.test',
                'sex' => 'other',
            ]);
            $controller->setActionRequest($post->withAttribute('routeParams', ['account' => 'alice']));
            $updated = $controller->editProfile($post);

            self::assertSame(302, $updated->getStatusCode());
            self::assertSame('/user/alice', $updated->getHeader('Location'));
            self::assertSame('Alice Updated', $connection->scalar('SELECT `name` FROM `users` WHERE `id` = 1'));
            self::assertSame('new@example.test', $connection->scalar('SELECT `email` FROM `users` WHERE `id` = 1'));
            self::assertSame('other', $connection->scalar('SELECT `sex` FROM `users` WHERE `id` = 1'));

            $invalid = new Request('POST', Uri::fromString('/user/alice/info'), parsedBody: [
                '_token' => $session->token(),
                'name' => 'Alice',
                'email' => 'alice@example.test',
                'sex' => 'alien',
            ]);
            $controller->setActionRequest($invalid->withAttribute('routeParams', ['account' => 'alice']));
            self::assertSame(200, $controller->editProfile($invalid)->getStatusCode());
            self::assertSame('other', $connection->scalar('SELECT `sex` FROM `users` WHERE `id` = 1'));

            $connection->query("INSERT INTO users (id, nick, name, email, level, reg, sex) VALUES (2, 'bob', 'Bob', 'taken@example.test', 0, 0, 'boy')");
            $duplicate = new Request('POST', Uri::fromString('/user/alice/info'), parsedBody: [
                '_token' => $session->token(),
                'name' => 'Alice',
                'email' => 'taken@example.test',
                'sex' => 'girl',
            ]);
            $controller->setActionRequest($duplicate->withAttribute('routeParams', ['account' => 'alice']));
            self::assertSame(200, $controller->editProfile($duplicate)->getStatusCode());
            self::assertSame('new@example.test', $connection->scalar('SELECT `email` FROM `users` WHERE `id` = 1'));

            session_write_close();
            session_id('');
            $_SESSION = [];
            $_COOKIE = [];
            $guestSession = new SessionManager($sessionName);
            $guestRequest = new Request('GET', Uri::fromString('/user/alice/info'));
            $guestAuth = new AuthManager($connection, $guestRequest, new PasswordHasher(), new Clock(), $guestSession, $config);
            $container->instance('auth', $guestAuth);
            $container->instance(AuthManager::class, $guestAuth);
            $guestController = new UserController(
                $container,
                new UserRepository($connection),
                new ArticleRepository($connection),
                new PasswordHasher(),
                $guestSession,
                new RateLimiter(sys_get_temp_dir() . '/soulcms-test-ratelimit', false),
            );
            $guestController->setActionRequest($guestRequest->withAttribute('routeParams', ['account' => 'alice']));

            self::assertSame(403, $guestController->editProfile($guestRequest)->getStatusCode());
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
