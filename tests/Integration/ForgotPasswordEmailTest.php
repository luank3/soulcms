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
use SoulCMS\Support\SmtpMailer;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class ForgotPasswordEmailTest extends TestCase
{
    public function testFailedSmtpDeliveryClearsTheTokenAndKeepsTheResponseGeneric(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, pass TEXT, reset_token TEXT, reset_expires INTEGER)');
        $connection->query("INSERT INTO users (id, nick, email) VALUES (1, 'alice', 'alice@example.test')");
        $config = new Config(['app' => [
            'url' => ['path' => ''],
            'mail' => ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'user', 'password' => 'pass', 'from_address' => 'sender@example.test'],
        ]]);
        $request = new Request('POST', Uri::fromString('/login/password/forgot'), parsedBody: ['email' => 'alice@example.test']);
        $session = new SessionManager();
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-mail-failure-' . bin2hex(random_bytes(5)), true));
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
            SmtpMailer::class => new SmtpMailer($config, static fn (): bool => false),
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $controller = new UserController(
            $container,
            new UserRepository($connection),
            new ArticleRepository($connection),
            new PasswordHasher(),
            $session,
            new RateLimiter(sys_get_temp_dir() . '/soulcms-mail-failure-limiter-' . bin2hex(random_bytes(5)), false),
        );

        try {
            $response = $controller->forgotPassword($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('nếu e-mail tồn tại trong hệ thống', mb_strtolower($response->getBody()));
            self::assertNull($connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1'));
        } finally {
            Container::swap($previous);
        }
    }

    public function testForgotPasswordEmailsResetLinkWithoutDisclosingUnknownAddresses(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, pass TEXT, reset_token TEXT, reset_expires INTEGER)');
        $connection->query("INSERT INTO users (id, nick, email, pass) VALUES (1, 'alice', 'alice@example.test', 'old-hash')");
        $config = new Config(['app' => [
            'name' => 'SoulCMS',
            'url' => ['scheme' => 'https', 'host' => 'forum.example.test', 'path' => '/community'],
            'session' => ['name' => 'SoulCMSForgotPasswordTest'],
            'mail' => [
                'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
                'username' => 'mailer', 'password' => 'secret',
                'from_address' => 'sender@example.test', 'from_name' => 'SoulCMS', 'timeout' => 5,
            ],
        ]]);
        $session = new SessionManager('SoulCMSForgotPasswordTest');
        $request = new Request('POST', Uri::fromString('/login/password/forgot'), parsedBody: ['email' => 'alice@example.test']);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-forgot-test-' . bin2hex(random_bytes(5)), true));
        $captured = [];
        $send = true;
        $mailer = new SmtpMailer($config, static function (array $settings, string $from, string $to, string $subject, string $body) use (&$captured, &$send): bool {
            $captured = compact('settings', 'from', 'to', 'subject', 'body');
            return $send;
        });
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
            SmtpMailer::class => $mailer,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $controller = new UserController(
            $container,
            new UserRepository($connection),
            new ArticleRepository($connection),
            new PasswordHasher(),
            $session,
            new RateLimiter(sys_get_temp_dir() . '/soulcms-forgot-limiter-' . bin2hex(random_bytes(5)), false),
        );

        try {
            $response = $controller->forgotPassword($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('nếu e-mail tồn tại trong hệ thống', mb_strtolower($response->getBody()));
            self::assertSame('alice@example.test', $captured['to']);
            self::assertSame('Đặt lại mật khẩu SoulCMS', $captured['subject']);
            self::assertStringStartsWith('https://forum.example.test/community/login/password/reset?token=' . $connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1'), trim(substr($captured['body'], strpos($captured['body'], 'https://'))));
            self::assertGreaterThan(time(), (int) $connection->scalar('SELECT `reset_expires` FROM `users` WHERE `id` = 1'));

            $token = (string) $connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1');
            $resetUrl = trim(explode("\n", substr($captured['body'], strpos($captured['body'], 'https://')))[0]);
            parse_str((string) parse_url($resetUrl, PHP_URL_QUERY), $query);
            $resetRequest = new Request('GET', Uri::fromString('/login/password/reset'), query: ['token' => $query['token'] ?? '']);
            $controller->setActionRequest($resetRequest);
            $resetResponse = $controller->resetPassword($resetRequest);
            self::assertSame(200, $resetResponse->getStatusCode());
            self::assertStringContainsString('name="token" value="' . $token . '"', $resetResponse->getBody());

            $connection->query('UPDATE `users` SET `reset_token` = NULL, `reset_expires` = NULL WHERE `id` = 1');
            $send = false;
            $failedRequest = new Request('POST', Uri::fromString('/login/password/forgot'), parsedBody: ['email' => 'alice@example.test']);
            $controller->setActionRequest($failedRequest);
            self::assertSame(200, $controller->forgotPassword($failedRequest)->getStatusCode());
            self::assertNull($connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1'));

            $unknown = new Request('POST', Uri::fromString('/login/password/forgot'), parsedBody: ['email' => 'missing@example.test']);
            $controller->setActionRequest($unknown);
            $unknownResponse = $controller->forgotPassword($unknown);
            self::assertSame(200, $unknownResponse->getStatusCode());
            self::assertStringContainsString('nếu e-mail tồn tại trong hệ thống', mb_strtolower($unknownResponse->getBody()));
        } finally {
            Container::swap($previous);
        }
    }
}
