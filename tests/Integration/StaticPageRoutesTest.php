<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Manager\Module as ManagerModule;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Middleware\RequireAdminMiddleware;
use SoulCMS\Routing\Router;

final class StaticPageRoutesTest extends TestCase
{
    public function testAdminStaticPageRoutesAreGuardedAndPublicPageRouteIsConstrained(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $container->instance('modules', new class {
            public function registerRoutes(Router $router): void
            {
            }
        });
        $router = new Router();
        (new ManagerModule())->routes($router);

        try {
            require dirname(__DIR__, 2) . '/routes/web.php';
        } finally {
            Container::swap($previous);
        }

        $admin = $router->getCollection()->get('manager.static_pages');
        self::assertNotNull($admin);
        self::assertSame('/manager/static_pages', $admin->getPattern());
        self::assertSame(['GET', 'POST'], $admin->getMethods());
        self::assertSame([RequireAdminMiddleware::class], $admin->getMiddleware());

        $page = $router->getCollection()->get('static_page');
        self::assertNotNull($page);
        self::assertSame('/{slug:[a-z0-9][a-z0-9-]*}.html', $page->getPattern());
        self::assertSame(['GET'], $page->getMethods());
        self::assertSame(['slug' => 'privacy'], $page->match('/privacy.html'));
        self::assertSame(['slug' => 'manager'], $page->match('/manager.html'));
        self::assertNull($page->match('/privacy/policy.html'));
    }
}
