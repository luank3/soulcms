<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Home;

use SoulCMS\Routing\Router;

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
        return 'Trang chủ';
    }

    public function description(): string
    {
        return 'Trang chủ, trang tĩnh và các endpoint cho công cụ tìm kiếm.';
    }

    public function routes(Router $router): void
    {
        // The core routes are registered in config/routes.php so that they are
        // always available, even when the module is disabled.
    }
}
