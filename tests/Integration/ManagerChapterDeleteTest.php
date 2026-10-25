<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Manager\Controllers\ManagerController;
use SoulCMS\App\Modules\Manager\Module;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Module\ModuleManager;
use SoulCMS\Http\Middleware\RequireAdminMiddleware;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Routing\Router;
use SoulCMS\Support\SettingsStore;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class ManagerChapterDeleteTest extends TestCase
{
    public function testAdminChapterDeleteRouteIsPostOnlyAndGuarded(): void
    {
        $router = new Router();
        (new Module())->routes($router);
        $route = $router->getCollection()->get('manager.articles.chapter.delete');

        self::assertNotNull($route);
        self::assertSame('/manager/articles/chapter/delete', $route->getPattern());
        self::assertSame(['POST'], $route->getMethods());
        self::assertSame([RequireAdminMiddleware::class], $route->getMiddleware());
    }

    public function testManagerShoutboxCleanRouteIsAdminGuardedAndPostOnly(): void
    {
        $router = new Router();
        (new Module())->routes($router);
        $route = $router->getCollection()->get('manager.shoutbox.clean');

        self::assertNotNull($route);
        self::assertSame('/manager/shoutbox/clean', $route->getPattern());
        self::assertSame(['POST'], $route->getMethods());
        self::assertSame([RequireAdminMiddleware::class], $route->getMiddleware());
    }

    public function testShoutboxCleanDeletesChatRowsAndUsesPostOnlyResponse(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE chat (id INTEGER PRIMARY KEY, name TEXT, time INTEGER, comment TEXT)');
        $connection->query("INSERT INTO chat VALUES (1, 'alice', 1, 'hello'), (2, 'bob', 2, 'hi')");
        $controller = $this->managerController($container, $connection);
        $request = new Request('POST', Uri::fromString('/manager/shoutbox/clean'), parsedBody: []);
        $controller->setActionRequest($request);

        try {
            $response = $controller->shoutboxClean($request);
            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/manager/shoutbox/clean', $response->getHeader('Location'));
            self::assertSame(0, (int) $connection->scalar('SELECT COUNT(*) FROM `chat`'));

            $notPost = new Request('GET', Uri::fromString('/manager/shoutbox/clean'));
            $controller->setActionRequest($notPost);
            self::assertSame(405, $controller->shoutboxClean($notPost)->getStatusCode());
        } finally {
            Container::swap($previous);
        }
    }

    private function managerController(Container $container, Connection $connection): ManagerController
    {
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $request = new Request('POST', Uri::fromString('/manager'));
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), new SessionManager(), $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-manager-clean-views', true));
        $modules = new ModuleManager($container, dirname(__DIR__, 2) . '/app/Modules');
        $settings = new SettingsStore($connection, sys_get_temp_dir() . '/soulcms-manager-clean-settings-' . bin2hex(random_bytes(6)) . '.json');
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'auth' => $auth,
            AuthManager::class => $auth,
            'view' => $views,
            ViewFactory::class => $views,
            ModuleManager::class => $modules,
            SettingsStore::class => $settings,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }

        return new ManagerController(
            $container,
            new ArticleRepository($connection),
            new UserRepository($connection),
            $modules,
            $connection,
            new PasswordHasher(),
            $settings,
        );
    }

    public function testDeleteActionRemovesOnlyTheRequestedChapter(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, time INTEGER, update_time INTEGER, category INTEGER, author TEXT, blocked INTEGER, sticked INTEGER, view INTEGER, buyfile INTEGER, `like` TEXT)');
        $connection->query('CREATE TABLE articles_chap (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, time INTEGER, box INTEGER, author TEXT)');
        $connection->query("INSERT INTO articles_chap VALUES (1, 'One', 'one', 'a', 1, 10, 'alice'), (2, 'Two', 'two', 'b', 2, 10, 'alice')");
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $request = new Request('POST', Uri::fromString('/manager/articles/chapter/delete'), parsedBody: ['id' => 1]);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), new SessionManager(), $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-manager-chapter-views', true));
        $modules = new ModuleManager($container, dirname(__DIR__, 2) . '/app/Modules');
        $settings = new SettingsStore($connection, sys_get_temp_dir() . '/soulcms-manager-chapter-settings-' . bin2hex(random_bytes(6)) . '.json');
        foreach ([
            'config' => $config,
            Config::class => $config,
            Request::class => $request,
            AuthManager::class => $auth,
            ViewFactory::class => $views,
            ModuleManager::class => $modules,
            SettingsStore::class => $settings,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $controller = new ManagerController(
            $container,
            new ArticleRepository($connection),
            new UserRepository($connection),
            $modules,
            $connection,
            new PasswordHasher(),
            $settings,
        );
        $controller->setActionRequest($request);

        try {
            $response = $controller->chapterDelete($request);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/manager/articles', $response->getHeader('Location'));
            self::assertNull($connection->selectOne('SELECT `id` FROM `articles_chap` WHERE `id` = 1'));
            self::assertNotNull($connection->selectOne('SELECT `id` FROM `articles_chap` WHERE `id` = 2'));
        } finally {
            Container::swap($previous);
        }
    }
}
