<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class UserResetTokenTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testResetWorksWithoutSessionAndRejectsExpiredTokens(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSResetTokenTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-reset-session-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS reset token test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $session = new SessionManager($sessionName);
        $config = new Config(['app' => ['url' => ['path' => ''], 'session' => ['name' => $sessionName]]]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, pass TEXT, reset_token TEXT, reset_expires INTEGER)');
        $token = bin2hex(random_bytes(32));
        $connection->query('INSERT INTO users (id, nick, email, pass, reset_token, reset_expires) VALUES (1, ?, ?, ?, ?, ?)', [
            'alice', 'alice@example.test', 'old-hash', $token, time() + 3600,
        ]);
        $request = new Request('POST', Uri::fromString('/login/password/reset'), parsedBody: [
            'token' => $token,
            'password' => 'new-password',
            'password_confirm' => 'new-password',
        ]);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-password-reset-views', true));
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

            self::assertNull($session->peek('reset_user_id'));
            self::assertSame(302, $controller->resetPassword($request)->getStatusCode());
            self::assertNull($connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1'));
            self::assertTrue((new PasswordHasher())->verify('new-password', (string) $connection->scalar('SELECT `pass` FROM `users` WHERE `id` = 1')));

            $connection->query('UPDATE `users` SET `reset_token` = ?, `reset_expires` = ? WHERE `id` = 1', ['expired-token', time() - 1]);
            $expiredRequest = new Request('POST', Uri::fromString('/login/password/reset'), parsedBody: [
                'token' => 'expired-token',
                'password' => 'other-password',
                'password_confirm' => 'other-password',
            ]);
            $controller->setActionRequest($expiredRequest);
            self::assertNotSame(302, $controller->resetPassword($expiredRequest)->getStatusCode());
            self::assertTrue((new PasswordHasher())->verify('new-password', (string) $connection->scalar('SELECT `pass` FROM `users` WHERE `id` = 1')));
        } finally {
            Container::swap($previous);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            $_COOKIE = [];
            foreach (glob($sessionPath . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($sessionPath);
        }
    }

    public function testConsumingResetTokenUpdatesPasswordAndInvalidatesTokenAtomically(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, pass TEXT, reset_token TEXT, reset_expires INTEGER)');
        $connection->query('INSERT INTO users (id, nick, email, pass, reset_token, reset_expires) VALUES (1, ?, ?, ?, ?, ?)', [
            'alice', 'alice@example.test', 'old-hash', 'valid-token', 2000,
        ]);
        $users = new UserRepository($connection);

        self::assertTrue($users->consumeResetToken(1, 'valid-token', 1000, 'new-hash'));
        self::assertSame('new-hash', $connection->scalar('SELECT `pass` FROM `users` WHERE `id` = 1'));
        self::assertNull($connection->scalar('SELECT `reset_token` FROM `users` WHERE `id` = 1'));
        self::assertFalse($users->consumeResetToken(1, 'valid-token', 1000, 'replay-hash'));
        self::assertSame('new-hash', $connection->scalar('SELECT `pass` FROM `users` WHERE `id` = 1'));
    }

    public function testFindByResetTokenRequiresExactTokenAndFutureExpiry(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, email TEXT, reset_token TEXT, reset_expires INTEGER)');
        $connection->query('INSERT INTO users (id, nick, email, reset_token, reset_expires) VALUES (1, ?, ?, ?, ?), (2, ?, ?, ?, ?)', [
            'alice', 'alice@example.test', 'valid-token', 2000,
            'bob', 'bob@example.test', 'expired-token', 999,
        ]);
        $users = new UserRepository($connection);

        self::assertSame('alice', $users->findByResetToken('valid-token', 1000)['nick'] ?? null);
        self::assertNull($users->findByResetToken('forged-token', 1000));
        self::assertNull($users->findByResetToken('expired-token', 1000));
    }
}
