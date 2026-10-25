<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Shoutbox;

use SoulCMS\Routing\Router;
use SoulCMS\Support\Lang;

/**
 * The public chat room.
 */
final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string
    {
        return 'shoutbox';
    }

    public function title(): string
    {
        return Lang::get('module.shoutbox.title');
    }

    public function description(): string
    {
        return Lang::get('module.shoutbox.description');
    }

    public function routes(Router $router): void
    {
        $controller = \SoulCMS\App\Modules\Shoutbox\Controllers\ShoutboxController::class;

        $router->add('/shoutbox', [$controller, 'index'], 'GET', 'shoutbox.index');
        $router->add('/shoutbox/count', [$controller, 'count'], 'GET', 'shoutbox.count');
        $router->add('/shoutbox/ele', [$controller, 'element'], 'GET', 'shoutbox.element');
        $router->add('/shoutbox/list', [$controller, 'list'], 'GET', 'shoutbox.list');
        $router->add('/shoutbox/send', [$controller, 'send'], 'GET|POST', 'shoutbox.send');
    }
}
