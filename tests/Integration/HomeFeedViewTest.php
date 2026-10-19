<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
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
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class HomeFeedViewTest extends TestCase
{
    private ?Container $previousContainer = null;
    private ViewFactory $views;

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        Container::swap($container);

        $config = new Config([
            'app' => [
                'url' => ['path' => ''],
                'content' => [
                    'post_list_order_by' => ['id', 'time', 'update_time', 'view'],
                    'post_list_sort' => ['asc', 'desc'],
                ],
            ],
        ]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $auth = new AuthManager(
            $connection,
            new Request('GET', Uri::fromString('/')),
            new PasswordHasher(),
            new Clock(),
            new SessionManager(),
            $config,
        );
        $engine = new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-test-views', true);
        $this->views = new ViewFactory($engine);

        $container->instance('config', $config);
        $container->instance('auth', $auth);
        $container->instance('view', $this->views);
    }

    protected function tearDown(): void
    {
        Container::swap($this->previousContainer);
    }

    public function testHomeFeedExposesSafeSortControlsAndRetainsCategory(): void
    {
        $html = $this->views->render('home/index', [
            'posts' => [],
            'categories' => [],
            'stats' => [],
            'sticked' => [],
            'online_users' => [],
            'pagination' => '',
            'current_page' => 1,
            'category' => ['id' => 5, 'name' => 'Tin tức', 'content' => ''],
            'order_by' => 'view',
            'sort' => 'asc',
        ]);

        self::assertStringContainsString('name="order_by"', $html);
        self::assertStringContainsString('name="sort"', $html);
        self::assertStringContainsString('value="view" selected', $html);
        self::assertStringContainsString('value="asc" selected', $html);
        self::assertStringContainsString('name="category" value="5"', $html);
    }
}
