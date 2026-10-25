<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Support\Lang;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class AuthViewLocaleTest extends TestCase
{
    public function testLoginAndRegistrationFormsUseEnglishCatalogue(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $session = new SessionManager();
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/auth-locale-' . bin2hex(random_bytes(5)), true));
        foreach ([
            'config' => $config,
            'session' => $session,
            'view' => $views,
            ViewFactory::class => $views,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        Lang::setLocale('en');

        try {
            $login = $views->render('user/login', [
                'errors' => [],
                'old' => [],
                'page_title' => Lang::get('auth.login.title'),
            ], 'layouts/auth');
            $register = $views->render('user/register', [
                'errors' => [],
                'old' => [],
                'captcha_question' => '2 + 3',
                'page_title' => Lang::get('auth.register.title'),
            ], 'layouts/auth');
            $forgot = $views->render('user/forgot_password', [
                'sent' => false,
                'page_title' => Lang::get('auth.forgot.title'),
            ], 'layouts/auth');
            $reset = $views->render('user/reset_password', [
                'token' => 'reset-token',
                'errors' => [],
                'page_title' => Lang::get('auth.reset.title'),
            ], 'layouts/auth');
            $profileEdit = $views->render('user/edit', [
                'profile' => ['nick' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.test', 'sex' => 'girl'],
                'errors' => ['email' => 'Invalid email address'],
            ]);
            $blocklist = $views->render('user/blocklist', [
                'profile' => ['nick' => 'alice'],
                'blocked_users' => [['nick' => 'bob', 'name' => 'Bob']],
                'errors' => ['nick' => 'Member not found'],
            ]);
            $emptyBlocklist = $views->render('user/blocklist', [
                'profile' => ['nick' => 'alice'],
                'blocked_users' => [],
                'errors' => [],
            ]);
            $members = $views->render('user/list', [
                'users' => [['id' => 2, 'nick' => 'bob', 'level' => 1, 'karma' => 9, 'reg' => 0]],
                'total' => 1,
                'pagination' => '',
            ]);
            $emptyMembers = $views->render('user/list', [
                'users' => [],
                'total' => 0,
                'pagination' => '',
            ]);
        } finally {
            Lang::setLocale('vi');
            Container::swap($previous);
        }

        self::assertStringContainsString('<html lang="en"', $login);
        self::assertStringContainsString('<title>Sign in · SoulCMS</title>', $login);
        self::assertStringContainsString('<h1>Sign in</h1>', $login);
        self::assertStringContainsString('Username or email', $login);
        self::assertStringContainsString('Remember me', $login);
        self::assertStringContainsString('<h1>Create account</h1>', $register);
        self::assertStringContainsString('Confirm password', $register);
        self::assertStringContainsString('Verification: 2 + 3', $register);
        self::assertStringContainsString('<h1>Forgot password</h1>', $forgot);
        self::assertStringContainsString('Send reset link', $forgot);
        self::assertStringContainsString('<h1>Set a new password</h1>', $reset);
        self::assertStringContainsString('New password', $reset);
        self::assertStringContainsString('<h1 class="panel__title">Edit profile</h1>', $profileEdit);
        self::assertStringContainsString('Display name', $profileEdit);
        self::assertStringContainsString('Gender', $profileEdit);
        self::assertStringContainsString('Save changes', $profileEdit);
        self::assertStringContainsString('Cancel', $profileEdit);
        self::assertStringContainsString('Unable to save profile', $profileEdit);
        self::assertStringContainsString('Invalid email address', $profileEdit);
        self::assertStringContainsString('<h1 class="panel__title">Block list</h1>', $blocklist);
        self::assertStringContainsString('Member username', $blocklist);
        self::assertStringContainsString('Block member', $blocklist);
        self::assertStringContainsString('Unable to update block list', $blocklist);
        self::assertStringContainsString('Bob', $blocklist);
        self::assertStringContainsString('Unblock', $blocklist);
        self::assertStringContainsString('Back to profile', $blocklist);
        self::assertStringContainsString('You have not blocked any members yet.', $emptyBlocklist);
        self::assertStringContainsString('Members', $members);
        self::assertStringContainsString('1 member', $members);
        self::assertStringContainsString('Role', $members);
        self::assertStringContainsString('Join date', $members);
        self::assertStringContainsString('No members yet', $emptyMembers);
        self::assertStringContainsString('The list will appear here when someone registers.', $emptyMembers);
    }
}
