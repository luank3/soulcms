<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\User;

use SoulCMS\Routing\Router;

/**
 * Registration, authentication and member profiles.
 */
final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string
    {
        return 'user';
    }

    public function title(): string
    {
        return 'Thành viên';
    }

    public function description(): string
    {
        return 'Đăng ký, đăng nhập và hồ sơ thành viên.';
    }

    public function routes(Router $router): void
    {
        $router->add('/register', [\SoulCMS\App\Modules\User\Controllers\UserController::class, 'register'], 'GET|POST', 'user.register');
        $router->add('/login', [\SoulCMS\App\Modules\User\Controllers\UserController::class, 'login'], 'GET|POST', 'user.login');
        $router->add('/logout', [\SoulCMS\App\Modules\User\Controllers\UserController::class, 'logout'], 'GET|POST', 'user.logout');
        $router->add('/login/password/forgot', [\SoulCMS\App\Modules\User\Controllers\UserController::class, 'forgotPassword'], 'GET|POST', 'user.password.forgot');
        $router->add('/login/password/reset', [\SoulCMS\App\Modules\User\Controllers\UserController::class, 'resetPassword'], 'GET|POST', 'user.password.reset');
        $router->add('/users', [\SoulCMS\App\Modules\User\Controllers\UserController::class, 'userList'], 'GET|POST', 'user.list');
        $router->add('/user/{account:[a-zA-Z0-9\-_]+}', [\SoulCMS\App\Modules\User\Controllers\UserController::class, 'userDetail'], 'GET|POST', 'user.detail');
    }
}
