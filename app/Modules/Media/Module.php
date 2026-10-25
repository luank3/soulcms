<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Media;

use SoulCMS\Http\Middleware\AuthenticateMiddleware;
use SoulCMS\Routing\Router;
use SoulCMS\Support\Lang;

final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string
    {
        return 'media';
    }

    public function title(): string
    {
        return Lang::get('module.media.title');
    }

    public function description(): string
    {
        return Lang::get('module.media.description');
    }

    public function routes(Router $router): void
    {
        $controller = \SoulCMS\App\Modules\Media\Controllers\MediaController::class;
        $router->add('/media/upload', [$controller, 'upload'], 'POST', 'media.upload', [AuthenticateMiddleware::class]);
        $router->add('/media/library', [$controller, 'library'], 'GET', 'media.library', [AuthenticateMiddleware::class]);
        $router->add('/media/download/{id:[0-9]+}/{filename:[^/]+}', [$controller, 'download'], 'GET', 'media.download', [AuthenticateMiddleware::class]);
    }
}
