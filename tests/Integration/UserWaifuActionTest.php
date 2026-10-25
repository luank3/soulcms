<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Controllers\UserController;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Support\RateLimiter;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class UserWaifuActionTest extends TestCase
{
    public function testWaifuVisibilityCanOnlyBeChangedByTheOwnerUsingPost(): void
    {
        $router = new \SoulCMS\Routing\Router();
        (new \SoulCMS\App\Modules\User\Module())->routes($router);
        $route = $router->getCollection()->get('user.waifu');
        self::assertNotNull($route);
        self::assertSame(['POST'], $route->getMethods());

        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, level INTEGER, reg INTEGER, sex TEXT, avatar TEXT, cover TEXT, `on` INTEGER, new_mail TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY, user_id INTEGER, header TEXT, rleft TEXT, rright TEXT, close INTEGER)');
        $connection->query("INSERT INTO users VALUES (1, 'alice', 'Alice', '', 0, 0, 'girl', '', '', 0, '')");
        $connection->query("INSERT INTO users_waifu VALUES (1, 1, '', '', '', 0)");
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $session = new SessionManager();
        $session->put('uid', 1);
        $request = new Request('POST', Uri::fromString('/user/alice/waifu'));
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/waifu-action-' . bin2hex(random_bytes(5)), true));
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
        $controller = new UserController($container, new UserRepository($connection), new ArticleRepository($connection), new PasswordHasher(), $session, new RateLimiter(sys_get_temp_dir() . '/waifu-action-limit-' . bin2hex(random_bytes(5)), false));

        try {
            $close = new Request('POST', Uri::fromString('/user/alice/waifu'), parsedBody: ['action' => 'close']);
            $controller->setActionRequest($close->withAttribute('routeParams', ['account' => 'alice']));
            self::assertSame(302, $controller->waifu($close)->getStatusCode());
            self::assertSame(1, (int) $connection->scalar('SELECT `close` FROM `users_waifu` WHERE `user_id` = 1'));

            $invalid = new Request('POST', Uri::fromString('/user/alice/waifu'), parsedBody: ['action' => 'destroy']);
            $controller->setActionRequest($invalid->withAttribute('routeParams', ['account' => 'alice']));
            self::assertSame(422, $controller->waifu($invalid)->getStatusCode());
            self::assertSame(1, (int) $connection->scalar('SELECT `close` FROM `users_waifu` WHERE `user_id` = 1'));

            $open = new Request('POST', Uri::fromString('/user/alice/waifu'), parsedBody: ['action' => 'open']);
            $controller->setActionRequest($open->withAttribute('routeParams', ['account' => 'alice']));
            self::assertSame(302, $controller->waifu($open)->getStatusCode());
            self::assertSame(0, (int) $connection->scalar('SELECT `close` FROM `users_waifu` WHERE `user_id` = 1'));

            $controller->setActionRequest($close->withAttribute('routeParams', ['account' => 'bob']));
            self::assertSame(404, $controller->waifu($close)->getStatusCode());
        } finally {
            Container::swap($previous);
        }
    }
}
