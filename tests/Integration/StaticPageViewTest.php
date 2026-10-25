<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Home\Controllers\HomeController;
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
use SoulCMS\Support\SettingsStore;
use SoulCMS\Support\StaticPageStore;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class StaticPageViewTest extends TestCase
{
    public function testPublishedPageUsesSeoFieldsAndRendersContentSafely(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $request = new Request('GET', Uri::fromString('/privacy.html'));
        $config = new Config(['app' => ['name' => 'SoulCMS', 'url' => ['path' => '']]]);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), new SessionManager(), $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-static-view-' . bin2hex(random_bytes(5)), true));
        $settings = new SettingsStore($connection, sys_get_temp_dir() . '/soulcms-static-view-settings-' . bin2hex(random_bytes(5)) . '.json');
        $pages = new StaticPageStore($settings);
        $pages->save(null, [
            'slug' => 'privacy',
            'title' => 'Privacy',
            'content' => '[b]Safe[/b] <script>alert(1)</script>',
            'description' => 'Privacy description',
            'keyword' => 'privacy, data',
        ]);
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'auth' => $auth,
            AuthManager::class => $auth,
            'view' => $views,
            ViewFactory::class => $views,
            StaticPageStore::class => $pages,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $controller = new HomeController($container, new ArticleRepository($connection), new UserRepository($connection));
        $controller->setActionRequest($request->withAttribute('routeParams', ['slug' => 'privacy']));

        try {
            $response = $controller->staticPage($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('<title>Privacy · SoulCMS</title>', $response->getBody());
            self::assertStringContainsString('name="description" content="Privacy description"', $response->getBody());
            self::assertStringContainsString('<strong>Safe</strong>', $response->getBody());
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getBody());
            self::assertStringNotContainsString('<script>alert(1)</script>', $response->getBody());
        } finally {
            Container::swap($previous);
        }
    }
}
