<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Mail;

use SoulCMS\Routing\Router;
use SoulCMS\Support\Lang;

/**
 * Private messages between members.
 */
final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string
    {
        return 'mail';
    }

    public function title(): string
    {
        return Lang::get('module.mail.title');
    }

    public function description(): string
    {
        return Lang::get('module.mail.description');
    }

    public function routes(Router $router): void
    {
        $controller = \SoulCMS\App\Modules\Mail\Controllers\MailController::class;

        $router->add('/mail', [$controller, 'index'], 'GET', 'mail.index');
        $router->add('/mail/send/{receiver:[a-zA-Z0-9\-_]+}', [$controller, 'send'], 'GET|POST', 'mail.send');
        $router->add('/mail/system', [$controller, 'system'], 'GET|POST', 'mail.system');
        $router->post('/mail/system/clear', [$controller, 'clearSystem'], 'mail.system.clear');
    }
}
