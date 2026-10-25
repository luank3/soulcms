<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Home\Controllers\LanguageController;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class LanguageSwitchTest extends TestCase
{
    public function testLanguageSwitchSetsSupportedLocaleCookieAndSafeReturnLocation(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $config = new Config(['app' => ['url' => ['path' => '', 'scheme' => 'https', 'host' => 'example.test']]]);
        $session = new SessionManager();
        $request = new Request('GET', Uri::fromString('/language'), query: [
            'locale' => 'en',
            'return' => '/articles?page=2',
        ]);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/language-switch-' . bin2hex(random_bytes(5)), true));
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        foreach ([
            'config' => $config,
            Config::class => $config,
            Request::class => $request,
            ViewFactory::class => $views,
            AuthManager::class => $auth,
        ] as $id => $service) {
            $container->instance($id, $service);
        }

        try {
            $controller = new LanguageController($container);
            $controller->setActionRequest($request);
            $response = $controller->switch($request);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/articles?page=2', $response->getHeader('Location'));
            self::assertSame('soulcms_locale=en; Path=/; Max-Age=31536000; SameSite=Lax; HttpOnly; Secure', $response->getHeader('Set-Cookie'));

            $hostileRequest = new Request('GET', Uri::fromString('/language'), query: [
                'locale' => 'vi',
                'return' => 'https://evil.example/path',
            ]);
            $controller->setActionRequest($hostileRequest);
            self::assertSame('/', $controller->switch($hostileRequest)->getHeader('Location'));
        } finally {
            Container::swap($previous);
        }
    }

    public function testLanguageSwitchRejectsUnsupportedLocalesWithoutSettingCookie(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $session = new SessionManager();
        $request = new Request('GET', Uri::fromString('/language'), query: ['locale' => 'fr']);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/language-switch-invalid-' . bin2hex(random_bytes(5)), true));
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        foreach ([
            'config' => $config,
            Config::class => $config,
            Request::class => $request,
            ViewFactory::class => $views,
            AuthManager::class => $auth,
        ] as $id => $service) {
            $container->instance($id, $service);
        }

        try {
            $controller = new LanguageController($container);
            $controller->setActionRequest($request);
            $response = $controller->switch($request);

            self::assertSame(422, $response->getStatusCode());
            self::assertFalse($response->hasHeader('Set-Cookie'));
        } finally {
            Container::swap($previous);
        }
    }
}
