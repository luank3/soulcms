<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;

final class AuthManagerTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRestoresAuthenticationFromAnIncomingSessionCookie(): void
    {
        $sessionName = 'SoulCMSAuthTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-auth-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS auth test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

        $previousRequestSession = new SessionManager($sessionName);
        $previousRequestSession->put('uid', 1);
        $sessionId = $previousRequestSession->getId();
        session_write_close();
        session_id('');
        $_SESSION = [];
        $_COOKIE = [$sessionName => $sessionId];
        session_id($sessionId);

        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, pass TEXT, level INTEGER, status TEXT, `on` INTEGER, remember_token TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
        $connection->query('INSERT INTO users (id, nick, name, email, pass, level, status, `on`) VALUES (1, ?, ?, ?, ?, 127, NULL, 0)', ['admin', 'Admin', 'admin@example.test', 'unused']);

        $session = new SessionManager($sessionName);
        $request = new Request(
            'GET',
            Uri::fromString('/'),
            cookies: [$sessionName => $sessionId],
            server: ['HTTP_USER_AGENT' => 'SoulCMS auth test', 'HTTP_ACCEPT_LANGUAGE' => 'en'],
        );
        $auth = new AuthManager(
            $connection,
            $request,
            new PasswordHasher(),
            new Clock(),
            $session,
            new Config(['app' => ['session' => ['name' => $sessionName]]]),
        );

        $authenticated = $auth->check();

        self::assertTrue($session->isStarted());
        self::assertSame(1, $session->peek('uid'));
        self::assertTrue($authenticated);
        self::assertSame('admin', $auth->user()->nick());

        session_write_close();
        session_id('');
        foreach (glob($sessionPath . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($sessionPath);
    }

    #[RunInSeparateProcess]
    public function testAnonymousCheckDoesNotStartASessionWithoutSessionCookie(): void
    {
        $sessionName = 'SoulCMSAnonymousTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-auth-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];

        $session = new SessionManager($sessionName);
        $auth = new AuthManager(
            new Connection(new PDO('sqlite::memory:'), Driver::Sqlite),
            new Request('GET', Uri::fromString('/')),
            new PasswordHasher(),
            new Clock(),
            $session,
            new Config(['app' => ['session' => ['name' => $sessionName]]]),
        );

        self::assertFalse($auth->check());
        self::assertFalse($session->isStarted());

        rmdir($sessionPath);
    }
}
