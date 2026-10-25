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

final class ManagerCategoryEditTest extends TestCase
{
    public function testCategoryEditRouteIsGuardedAndSupportsGetAndPost(): void
    {
        $router = new Router();
        (new Module())->routes($router);
        $route = $router->getCollection()->get('manager.articles.category.edit');

        self::assertNotNull($route);
        self::assertSame('/manager/articles/category/edit', $route->getPattern());
        self::assertSame(['GET', 'POST'], $route->getMethods());
        self::assertSame([RequireAdminMiddleware::class], $route->getMiddleware());
    }

    public function testAdminCanOpenCategoryEditFormAndSaveChangedFields(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY)');
        $connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY, community INTEGER, name TEXT, slug TEXT, content TEXT, keyword TEXT, meta_data TEXT)');
        $connection->query("INSERT INTO articles_category VALUES (1, 3, 'Old', 'old', 'Old description', 'old-key', NULL)");
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $get = new Request('GET', Uri::fromString('/manager/articles/category/edit'), query: ['id' => 1]);
        $session = new SessionManager();
        $auth = new AuthManager($connection, $get, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-category-edit-view-' . bin2hex(random_bytes(5)), true));
        $modules = new ModuleManager($container, dirname(__DIR__, 2) . '/app/Modules');
        $settings = new SettingsStore($connection, sys_get_temp_dir() . '/soulcms-category-settings-' . bin2hex(random_bytes(5)) . '.json');
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $get,
            Request::class => $get,
            'auth' => $auth,
            AuthManager::class => $auth,
            'session' => $session,
            SessionManager::class => $session,
            'view' => $views,
            ViewFactory::class => $views,
            ModuleManager::class => $modules,
            SettingsStore::class => $settings,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $controller = new ManagerController($container, new ArticleRepository($connection), new UserRepository($connection), $modules, $connection, new PasswordHasher(), $settings);

        try {
            \SoulCMS\Support\Lang::setLocale('en');
            $controller->setActionRequest($get);
            $page = $controller->categoryEdit($get);
            self::assertSame(200, $page->getStatusCode());
            self::assertStringContainsString('<title>Edit category ·', $page->getBody());
            self::assertStringContainsString('value="Old"', $page->getBody());

            $post = new Request('POST', Uri::fromString('/manager/articles/category/edit'), parsedBody: [
                'id' => 1,
                'name' => 'New',
                'slug' => 'new',
                'content' => 'Updated description',
                'keyword' => 'new-key',
            ]);
            $controller->setActionRequest($post);
            $response = $controller->categoryEdit($post);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('New', $connection->scalar('SELECT `name` FROM `articles_category` WHERE `id` = 1'));
            self::assertSame(3, (int) $connection->scalar('SELECT `community` FROM `articles_category` WHERE `id` = 1'));
        } finally {
            \SoulCMS\Support\Lang::setLocale('vi');
            Container::swap($previous);
        }
    }

    public function testCategoryEditRequiresBothNameAndSlug(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY)');
        $connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY, community INTEGER, name TEXT, slug TEXT, content TEXT, keyword TEXT, meta_data TEXT)');
        $connection->query("INSERT INTO articles_category VALUES (1, 3, 'Old', 'old', 'Description', 'key', NULL)");
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $request = new Request('POST', Uri::fromString('/manager/articles/category/edit'), parsedBody: ['id' => 1, 'name' => 'New', 'slug' => '']);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), new SessionManager(), $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-category-edit-invalid-' . bin2hex(random_bytes(5)), true));
        $modules = new ModuleManager($container, dirname(__DIR__, 2) . '/app/Modules');
        $settings = new SettingsStore($connection);
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
        $controller = new ManagerController($container, new ArticleRepository($connection), new UserRepository($connection), $modules, $connection, new PasswordHasher(), $settings);
        $controller->setActionRequest($request);

        try {
            $response = $controller->categoryEdit($request);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('Tên chuyên mục và đường dẫn không được để trống.', $response->getBody());
            self::assertSame('Old', $connection->scalar('SELECT `name` FROM `articles_category` WHERE `id` = 1'));
        } finally {
            Container::swap($previous);
        }
    }

    public function testCategoryCreateSetsOnlyEditableFieldsAndUsesSchemaDefaultCommunity(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY)');
        $connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY AUTOINCREMENT, community INTEGER NOT NULL DEFAULT 1, name TEXT NOT NULL, slug TEXT NOT NULL, content TEXT NOT NULL, keyword TEXT NOT NULL, meta_data TEXT NULL)');
        $articles = new ArticleRepository($connection);

        $id = $articles->createCategory([
            'name' => 'Guides',
            'slug' => 'guides',
            'content' => 'Helpful articles',
            'keyword' => 'guide',
            'community' => 99,
            'meta_data' => 'not editable here',
        ]);

        self::assertSame(1, $id);
        self::assertSame([
            'community' => 1,
            'name' => 'Guides',
            'slug' => 'guides',
            'content' => 'Helpful articles',
            'keyword' => 'guide',
            'meta_data' => null,
        ], $connection->selectOne('SELECT `community`, `name`, `slug`, `content`, `keyword`, `meta_data` FROM `articles_category` WHERE `id` = ?', [$id]));
    }

    public function testCategoryUpdateChangesOnlyEditableFields(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY)');
        $connection->query('CREATE TABLE articles_category (id INTEGER PRIMARY KEY, community INTEGER, name TEXT, slug TEXT, content TEXT, keyword TEXT, meta_data TEXT)');
        $connection->query("INSERT INTO articles_category VALUES (1, 3, 'Old', 'old', 'Old description', 'old-key', NULL)");
        $articles = new ArticleRepository($connection);

        self::assertSame(1, $articles->updateCategory(1, [
            'name' => 'New',
            'slug' => 'new',
            'content' => 'Updated description',
            'keyword' => 'new-key',
        ]));
        self::assertSame([
            'community' => 3,
            'name' => 'New',
            'slug' => 'new',
            'content' => 'Updated description',
            'keyword' => 'new-key',
            'meta_data' => null,
        ], $connection->selectOne('SELECT `community`, `name`, `slug`, `content`, `keyword`, `meta_data` FROM `articles_category` WHERE `id` = 1'));
    }
}
