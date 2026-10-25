<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Home;

use SoulCMS\Routing\Router;
use SoulCMS\Support\Lang;

/**
 * The public landing pages: forum index, static pages and SEO endpoints.
 */
final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string
    {
        return 'home';
    }

    public function title(): string
    {
        return Lang::get('module.home.title');
    }

    public function description(): string
    {
        return Lang::get('module.home.description');
    }

    public function routes(Router $router): void
    {
        // Public routes are registered in routes/web.php so they remain
        // available even when this module is disabled.
    }
}
