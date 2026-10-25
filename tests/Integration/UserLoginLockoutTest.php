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

final class UserLoginLockoutTest extends TestCase
{
    public function testForgotPasswordDoesNotPutResetTokenInTheBrowserSession(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $session = new SessionManager();
        $config = new Config(['app' => ['url' => ['path' => ''], 'mail' => [
            'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'mailer', 'password' => 'secret', 'from_address' => 'sender@example.test',
        ]]]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, pass TEXT, reset_token TEXT, reset_expires INTEGER)');
        $connection->query('INSERT INTO users (id, nick, email, pass) VALUES (1, ?, ?, ?)', ['alice', 'alice@example.test', 'old-hash']);
        $request = new Request('POST', Uri::fromString('/login/password/forgot'), parsedBody: ['email' => 'alice@example.test']);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-password-reset-views', true));
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'auth' => $auth,
            AuthManager::class => $auth,
            'view' => $views,
            ViewFactory::class => $views,
            SmtpMailer::class => new SmtpMailer($config, static fn (): bool => true),
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $key => $value) {
            $container->instance($key, $value);
        }

        try {
            $controller = new UserController(
                $container,
                new UserRepository($connection),
                new ArticleRepository($connection),
                new PasswordHasher(),
                $session,
                new RateLimiter(sys_get_temp_dir() . '/soulcms-reset-' . bin2hex(random_bytes(6)), false),
            );
            $controller->forgotPassword($request);

            self::assertNull($session->peek('reset_token'));
            self::assertNull($session->peek('reset_user_id'));
            $token = (string) $connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1');
            self::assertNotSame('', $token);

            $reset = new Request('POST', Uri::fromString('/login/password/reset'), parsedBody: [
                'token' => $token,
                'password' => 'new-password',
                'password_confirm' => 'new-password',
            ]);
            $controller->setActionRequest($reset);
            $response = $controller->resetPassword($reset);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/login', $response->getHeader('Location'));
            self::assertNull($connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1'));
            self::assertTrue((new PasswordHasher())->verify('new-password', (string) $connection->scalar('SELECT `pass` FROM `users` WHERE `id` = 1')));
        } finally {
            Container::swap($previous);
        }
    }

    public function testAccountLockExpiresAfterConfiguredDecayWindow(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, pass TEXT, failed_logins INTEGER DEFAULT 0, lock_time INTEGER DEFAULT 0)');
        $connection->query('INSERT INTO users (id, nick, email, pass, failed_logins, lock_time) VALUES (1, ?, ?, ?, 5, ?)', [
            'alice',
            'alice@example.test',
            (new PasswordHasher())->legacyHash('correct-password'),
            time(),
        ]);
        $auth = new AuthManager(
            $connection,
            new Request('GET', Uri::fromString('/login')),
            new PasswordHasher(),
            new Clock(),
            new SessionManager(),
            new Config(['app' => ['security' => ['login_max_attempts' => 5, 'login_decay_seconds' => 300]]]),
        );

        self::assertTrue($auth->isLocked(1));

        $connection->query('UPDATE `users` SET `lock_time` = ? WHERE `id` = 1', [time() - 301]);

        self::assertFalse($auth->isLocked(1));
        self::assertSame(0, (int) $connection->scalar('SELECT `failed_logins` FROM `users` WHERE `id` = 1'));
        self::assertSame(0, (int) $connection->scalar('SELECT `lock_time` FROM `users` WHERE `id` = 1'));
    }

    public function testFailedPasswordAttemptIsCountedOnce(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $config = new Config([
            'app' => [
                'url' => ['path' => ''],
                'security' => ['login_max_attempts' => 5, 'login_decay_seconds' => 300],
            ],
        ]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, pass TEXT, failed_logins INTEGER DEFAULT 0, lock_time INTEGER DEFAULT 0, level INTEGER DEFAULT 0, status TEXT, `on` INTEGER DEFAULT 0, remember_token TEXT)');
        $connection->query('INSERT INTO users (id, nick, email, pass) VALUES (1, ?, ?, ?)', [
            'alice',
            'alice@example.test',
            (new PasswordHasher())->legacyHash('correct-password'),
        ]);
        $request = new Request(
            'POST',
            Uri::fromString('/login'),
            headers: ['Accept' => 'application/json'],
            parsedBody: ['identifier' => 'alice', 'password' => 'wrong-password'],
        );
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), new SessionManager(), $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-login-lockout-views', true));
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'auth' => $auth,
            AuthManager::class => $auth,
            'view' => $views,
            ViewFactory::class => $views,
        ] as $key => $value) {
            $container->instance($key, $value);
        }

        try {
            $controller = new UserController(
                $container,
                new UserRepository($connection),
                new ArticleRepository($connection),
                new PasswordHasher(),
                new SessionManager(),
                new RateLimiter(sys_get_temp_dir() . '/soulcms-login-lockout-' . bin2hex(random_bytes(6)), false),
            );
            $response = $controller->login($request);

            self::assertSame(422, $response->getStatusCode());
            self::assertSame(1, (int) $connection->scalar('SELECT `failed_logins` FROM `users` WHERE `id` = 1'));
        } finally {
            Container::swap($previous);
        }
    }
}
