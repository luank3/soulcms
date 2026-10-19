<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Manager;

use SoulCMS\Http\Middleware\RequireAdminMiddleware;
use SoulCMS\Routing\Router;

/**
 * The administration panel.
 */
final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string
    {
        return 'manager';
    }

    public function title(): string
    {
        return 'Quản trị';
    }

    public function description(): string
    {
        return 'Bảng quản trị: bài viết, thành viên, module và cài đặt.';
    }

    public function routes(Router $router): void
    {
        $controller = \SoulCMS\App\Modules\Manager\Controllers\ManagerController::class;
        $guard = [RequireAdminMiddleware::class];

        $router->add('/manager', [$controller, 'dashboard'], 'GET', 'manager.dashboard', $guard);
        $router->add('/manager/users', [$controller, 'users'], 'GET|POST', 'manager.users', $guard);
        $router->add('/manager/users/edit', [$controller, 'userEdit'], 'POST', 'manager.users.edit', $guard);
        $router->add('/manager/articles', [$controller, 'articles'], 'GET|POST', 'manager.articles', $guard);
        $router->add('/manager/articles/category/delete', [$controller, 'categoryDelete'], 'POST', 'manager.articles.category.delete', $guard);
        $router->add('/manager/articles/post/delete', [$controller, 'postDelete'], 'POST', 'manager.articles.post.delete', $guard);
        $router->add('/manager/modules', [$controller, 'modules'], 'GET|POST', 'manager.modules', $guard);
        $router->add('/manager/settings', [$controller, 'settings'], 'GET|POST', 'manager.settings', $guard);
        $router->add('/manager/url-rules', [$controller, 'urlRules'], 'GET|POST', 'manager.url_rules', $guard);
        $router->add('/manager/url-rules/edit', [$controller, 'urlRuleEdit'], 'POST', 'manager.url_rules.edit', $guard);
        $router->add('/manager/url-rules/delete', [$controller, 'urlRuleDelete'], 'POST', 'manager.url_rules.delete', $guard);
    }
}
