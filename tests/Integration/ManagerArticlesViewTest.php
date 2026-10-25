<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class ManagerArticlesViewTest extends TestCase
{
    public function testCategoryListProvidesAnEditLinkForEachCategory(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $request = new Request('GET', Uri::fromString('/manager/articles'));
        $container->instance('config', $config);
        $container->instance(Config::class, $config);
        $container->instance('request', $request);
        $container->instance(Request::class, $request);
        $session = new SessionManager();
        $container->instance('session', $session);
        $container->instance(SessionManager::class, $session);
        $connection = new \SoulCMS\Database\Connection(new \PDO('sqlite::memory:'), \SoulCMS\Database\Driver::Sqlite);
        $auth = new \SoulCMS\Auth\AuthManager($connection, $request, new \SoulCMS\Auth\PasswordHasher(), new \SoulCMS\Foundation\Clock(), $session, $config);
        $container->instance('auth', $auth);
        $container->instance('csp', new \SoulCMS\Http\Csp());
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-manager-article-view-' . bin2hex(random_bytes(5)), true));
        $container->instance('view', $views);

        try {
            $html = $views->render('manager/articles', [
                'posts' => [],
                'categories' => [
                    ['id' => 4, 'name' => 'News', 'slug' => 'news', 'content' => '', 'keyword' => '', 'count_post' => 1],
                ],
                'pagination' => '',
            ]);
        } finally {
            Container::swap($previous);
        }

        self::assertStringContainsString('/manager/articles/category/edit?id=4', $html);
        self::assertStringContainsString('/manager/articles/category/add', $html);
        self::assertStringContainsString('Chỉnh sửa', $html);
    }
}
