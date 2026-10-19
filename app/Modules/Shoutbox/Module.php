<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Shoutbox;

use SoulCMS\Routing\Router;

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
        return 'Trò chuyện';
    }

    public function description(): string
    {
        return 'Phòng trò chuyện công khai của cộng đồng.';
    }

    public function routes(Router $router): void
    {
        $controller = \SoulCMS\App\Modules\Shoutbox\Controllers\ShoutboxController::class;

        $router->add('/shoutbox', [$controller, 'index'], 'GET', 'shoutbox.index');
        $router->add('/shoutbox/count', [$controller, 'count'], 'GET', 'shoutbox.count');
        $router->add('/shoutbox/list', [$controller, 'list'], 'GET', 'shoutbox.list');
        $router->add('/shoutbox/send', [$controller, 'send'], 'GET|POST', 'shoutbox.send');
    }
}
