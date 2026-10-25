<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
use SoulCMS\Support\RegistrationCaptcha;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class RegistrationCaptchaTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRegisterRejectsWrongCaptchaBeforeCreatingAccount(): void
    {
        [$previous, $container, $connection, $controller, $session] = $this->fixture('SoulCMSRegisterCaptchaWrongTest');
        try {
            $session->put('_registration_captcha', hash_hmac('sha256', '12', $session->token()));
            $request = new Request('POST', Uri::fromString('/register'), parsedBody: [
                '_token' => $session->token(),
                'nick' => 'newmember',
                'email' => 'newmember@example.test',
                'password' => 'secret123',
                'password_confirm' => 'secret123',
                'captcha_answer' => '11',
            ]);
            $container->instance('request', $request);
            $container->instance(Request::class, $request);
            $controller->setActionRequest($request);
            \SoulCMS\Support\Lang::setLocale('en');

            $response = $controller->register($request);

            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('The verification answer is incorrect.', $response->getBody());
            self::assertSame(0, (int) $connection->scalar('SELECT COUNT(*) FROM users WHERE nick = ?', ['newmember']));
            self::assertStringContainsString('Verification:', $response->getBody());
        } finally {
            $this->cleanup($previous, $session);
        }
    }

    #[RunInSeparateProcess]
    public function testOtherRegistrationValidationErrorsIssueANewChallenge(): void
    {
        [$previous, $container, $connection, , $session] = $this->fixture('SoulCMSRegisterCaptchaRetryTest');
        try {
            $captcha = new RegistrationCaptcha($session);
            $question = $captcha->issue();
            self::assertSame(1, preg_match('/^(\\d+) \\+ (\\d+) = \\?$/', $question, $parts));
            $answer = (string) ((int) $parts[1] + (int) $parts[2]);
            $request = new Request('POST', Uri::fromString('/register'), parsedBody: [
                '_token' => $session->token(),
                'nick' => 'ab',
                'email' => 'valid@example.test',
                'password' => 'secret123',
                'password_confirm' => 'secret123',
                'captcha_answer' => $answer,
            ]);
            $config = new Config(['app' => ['url' => ['path' => ''], 'session' => ['name' => 'SoulCMSRegisterCaptchaRetryTest']]]);
            $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
            $container->instance('config', $config);
            $container->instance(Config::class, $config);
            $container->instance('request', $request);
            $container->instance(Request::class, $request);
            $container->instance('auth', $auth);
            $container->instance(AuthManager::class, $auth);
            $controller = new UserController(
                $container,
                new UserRepository($connection),
                new ArticleRepository($connection),
                new PasswordHasher(),
                $session,
                new RateLimiter(sys_get_temp_dir() . '/soulcms-captcha-rate-' . bin2hex(random_bytes(4)), false),
            );
            $controller->setActionRequest($request);

            $response = $controller->register($request);

            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('Tên đăng nhập', $response->getBody());
            self::assertMatchesRegularExpression('/Xác minh: \\d+ \\+ \\d+ = \\?/', $response->getBody());
        } finally {
            $this->cleanup($previous, $session);
        }
    }

    public function testCaptchaChallengeAnswerIsOneUse(): void
    {
        [$previous, , , , $session] = $this->fixture('SoulCMSRegisterCaptchaReuseTest');
        try {
            $captcha = new RegistrationCaptcha($session);
            $question = $captcha->issue();
            self::assertSame(1, preg_match('/^(\\d+) \\+ (\\d+) = \\?$/', $question, $parts));
            $answer = (string) ((int) $parts[1] + (int) $parts[2]);
            self::assertTrue($captcha->verify($answer));
            self::assertFalse($captcha->verify($answer));
        } finally {
            $this->cleanup($previous, $session);
        }
    }

    /** @return array{?Container,Container,Connection,UserController,SessionManager} */
    private function fixture(string $sessionName): array
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $path = sys_get_temp_dir() . '/soulcms-register-captcha-' . bin2hex(random_bytes(6));
        mkdir($path, 0700);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        session_save_path($path);
        $_COOKIE = [];
        $_SESSION = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS registration captcha test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $config = new Config(['app' => ['url' => ['path' => ''], 'session' => ['name' => $sessionName]]]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, nick TEXT, name TEXT, email TEXT, pass TEXT, level INTEGER, reg INTEGER, sex TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
        $session = new SessionManager($sessionName);
        $request = new Request('GET', Uri::fromString('/register'));
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-register-captcha-views', true));
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
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $users = new UserRepository($connection);
        $controller = new UserController(
            $container,
            $users,
            new ArticleRepository($connection),
            new PasswordHasher(),
            $session,
            new RateLimiter(sys_get_temp_dir() . '/soulcms-captcha-rate-' . bin2hex(random_bytes(4)), false),
        );

        return [$previous, $container, $connection, $controller, $session];
    }

    private function cleanup(?Container $previous, SessionManager $session): void
    {
        if ($session->isStarted() && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        $_COOKIE = [];
        $_SESSION = [];
        Container::swap($previous);
    }

    public function testChallengeAnswerIsAcceptedOnceAndThenConsumed(): void
    {
        [$path, $session] = $this->session('SoulCMSCaptchaValidTest');
        try {
            $captcha = new RegistrationCaptcha($session);
            $question = $captcha->issue();
            self::assertSame(1, preg_match('/^(\d+) \+ (\d+) = \?$/', $question, $parts));

            self::assertTrue($captcha->verify((string) ((int) $parts[1] + (int) $parts[2])));
            self::assertFalse($captcha->verify((string) ((int) $parts[1] + (int) $parts[2])));
        } finally {
            $this->closeSession($path);
        }
    }

    #[RunInSeparateProcess]
    public function testIncorrectAnswerConsumesTheChallenge(): void
    {
        [$path, $session] = $this->session('SoulCMSCaptchaInvalidTest');
        try {
            $captcha = new RegistrationCaptcha($session);
            $captcha->issue();

            self::assertFalse($captcha->verify('not the answer'));
            self::assertFalse($captcha->verify('0'));
        } finally {
            $this->closeSession($path);
        }
    }

    /** @return array{string,SessionManager} */
    private function session(string $name): array
    {
        $path = sys_get_temp_dir() . '/soulcms-captcha-' . bin2hex(random_bytes(6));
        mkdir($path, 0700);
        session_save_path($path);
        $_COOKIE = [];
        $_SESSION = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS captcha test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

        return [$path, new SessionManager($name)];
    }

    private function closeSession(string $path): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        $_COOKIE = [];
        foreach (glob($path . '/*') ?: [] as $entry) {
            unlink($entry);
        }
        rmdir($path);
    }
}
