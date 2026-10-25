<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Shoutbox\Controllers\ShoutboxController;
use SoulCMS\App\Modules\Shoutbox\Module;
use SoulCMS\App\Modules\Shoutbox\Repositories\ChatRepository;
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
use SoulCMS\Routing\Router;
use SoulCMS\Support\RateLimiter;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class ShoutboxElementTest extends TestCase
{
    public function testSingleElementRouteIsRegistered(): void
    {
        $router = new Router();
        (new Module())->routes($router);
        $route = $router->getCollection()->get('shoutbox.element');

        self::assertNotNull($route);
        self::assertSame('/shoutbox/ele', $route->getPattern());
        self::assertSame(['GET'], $route->getMethods());
    }

    public function testElementReturnsOneEscapedChatMessageByChatId(): void
    {
        [$previous, $connection, $controller] = $this->makeController(
            "INSERT INTO chat VALUES (1, 'alice', 100, 'first'), (2, 'bob', 200, '<script>bad</script>')"
        );
        $request = new Request('GET', Uri::fromString('/shoutbox/ele'), query: ['chatID' => 2]);
        $controller->setActionRequest($request);

        try {
            $response = $controller->element($request);

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('text/html; charset=UTF-8', $response->getHeader('Content-Type'));
            self::assertStringContainsString('chatline__who">@bob', $response->getBody());
            self::assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;', $response->getBody());
            self::assertStringNotContainsString('@alice', $response->getBody());
            self::assertStringNotContainsString('<script>', $response->getBody());
        } finally {
            Container::swap($previous);
        }
    }

    public function testShoutboxMethodErrorUsesTheSelectedLocale(): void
    {
        [$previous, , $controller] = $this->makeController('');
        $request = new Request('GET', Uri::fromString('/shoutbox/send'));
        $controller->setActionRequest($request);
        \SoulCMS\Support\Lang::setLocale('vi');

        try {
            $response = $controller->send($request);
            self::assertSame(405, $response->getStatusCode());
            self::assertSame('{"status":"error","message":"Yêu cầu phương thức POST."}', $response->getBody());
        } finally {
            \SoulCMS\Support\Lang::setLocale('vi');
            Container::swap($previous);
        }
    }

    public function testMissingChatElementReturnsNotFound(): void
    {
        [$previous, , $controller] = $this->makeController('');
        $request = new Request('GET', Uri::fromString('/shoutbox/ele'), query: ['chatID' => 99]);
        $controller->setActionRequest($request);

        try {
            self::assertSame(404, $controller->element($request)->getStatusCode());
        } finally {
            Container::swap($previous);
        }
    }

    /** @return array{?Container,Connection,ShoutboxController} */
    private function makeController(string $rows): array
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE chat (id INTEGER PRIMARY KEY, name TEXT, time INTEGER, comment TEXT)');
        if ($rows !== '') {
            $connection->query($rows);
        }
        $request = new Request('GET', Uri::fromString('/shoutbox/ele'));
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $session = new SessionManager();
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-shoutbox-element-views', true));
        foreach ([
            Config::class => $config,
            Request::class => $request,
            AuthManager::class => $auth,
            SessionManager::class => $session,
            ViewFactory::class => $views,
        ] as $id => $service) {
            $container->instance($id, $service);
        }

        return [
            $previous,
            $connection,
            new ShoutboxController(
                $container,
                new ChatRepository($connection),
                new RateLimiter(sys_get_temp_dir() . '/soulcms-chat-element-' . bin2hex(random_bytes(6)), false),
            ),
        ];
    }
}
