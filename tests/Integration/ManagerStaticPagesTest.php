<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Manager\Controllers\ManagerController;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Module\ModuleManager;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Support\SettingsStore;
use SoulCMS\Support\StaticPageStore;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class ManagerStaticPagesTest extends TestCase
{
    public function testAdminCanCreateEditAndDeleteStaticPages(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $settings = new SettingsStore($connection, sys_get_temp_dir() . '/soulcms-manager-pages-' . bin2hex(random_bytes(6)) . '.json');
        $pages = new StaticPageStore($settings);
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $initial = new Request('POST', Uri::fromString('/manager/static_pages'));
        $auth = new AuthManager($connection, $initial, new PasswordHasher(), new Clock(), new SessionManager(), $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-manager-pages-views-' . bin2hex(random_bytes(5)), true));
        $modules = new ModuleManager($container, dirname(__DIR__, 2) . '/app/Modules');
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $initial,
            Request::class => $initial,
            'auth' => $auth,
            AuthManager::class => $auth,
            'view' => $views,
            ViewFactory::class => $views,
            ModuleManager::class => $modules,
            SettingsStore::class => $settings,
            StaticPageStore::class => $pages,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $controller = new ManagerController($container, new ArticleRepository($connection), new UserRepository($connection), $modules, $connection, new PasswordHasher(), $settings);

        try {
            $create = new Request('POST', Uri::fromString('/manager/static_pages'), parsedBody: [
                'slug' => 'privacy',
                'title' => 'Privacy',
                'content' => 'First version',
                'description' => 'Description',
                'keyword' => 'privacy',
            ]);
            $controller->setActionRequest($create);
            self::assertSame(302, $controller->staticPages($create)->getStatusCode());
            self::assertSame('First version', $pages->find('privacy')['content']);

            $edit = new Request('POST', Uri::fromString('/manager/static_pages'), parsedBody: [
                'current_slug' => 'privacy',
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'content' => 'Second version',
                'description' => '',
                'keyword' => '',
            ]);
            $controller->setActionRequest($edit);
            self::assertSame(302, $controller->staticPages($edit)->getStatusCode());
            self::assertNull($pages->find('privacy'));
            self::assertSame('Second version', $pages->find('privacy-policy')['content']);

            $delete = new Request('POST', Uri::fromString('/manager/static_pages/delete'), parsedBody: ['slug' => 'privacy-policy']);
            $controller->setActionRequest($delete);
            self::assertSame(302, $controller->staticPageDelete($delete)->getStatusCode());
            self::assertNull($pages->find('privacy-policy'));

            $get = new Request('GET', Uri::fromString('/manager/static_pages/delete'));
            $controller->setActionRequest($get);
            self::assertSame(405, $controller->staticPageDelete($get)->getStatusCode());
        } finally {
            Container::swap($previous);
        }
    }
}
