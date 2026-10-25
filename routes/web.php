<?php

/**
 * SoulCMS application routes.
 *
 * This file is loaded by the router factory. It has access to the container
 * through the static helper, which keeps the file usable from the CLI as well.
 */

declare(strict_types=1);

use SoulCMS\App\Modules\Articles\Controllers\ArticleController;
use SoulCMS\App\Modules\Home\Controllers\HomeController;
use SoulCMS\App\Modules\Home\Controllers\LanguageController;
use SoulCMS\Foundation\Container;
use SoulCMS\Routing\Router;

/**
 * The router instance is provided by the including scope (the router factory
 * or the CLI). It is only fetched from the container when the file is loaded
 * stand-alone, which would otherwise recurse into the router factory.
 *
 * @var Router $router
 */
if (!isset($router)) {
    $router = Container::getInstance()->get('router');
}

$router->get('/', [HomeController::class, 'index'], 'home');
$router->get('/home', [HomeController::class, 'index'], 'home.alias');
$router->get('/index.html', [HomeController::class, 'index'], 'home.index');
$router->get('/robots.txt', [HomeController::class, 'robots'], 'robots');
$router->get('/sitemap.xml', [ArticleController::class, 'sitemap'], 'sitemap');
$router->get('/plugin/{plugin:[a-zA-Z0-9_\-]+}', [HomeController::class, 'plugin'], 'plugin');
$router->get('/faq/{page:terms|about|help}', [HomeController::class, 'faq'], 'faq');
$router->get('/health', [HomeController::class, 'health'], 'health');
$router->get('/language', [LanguageController::class, 'switch'], 'language.switch');
$router->get('/{slug:[a-z0-9][a-z0-9-]*}.html', [HomeController::class, 'staticPage'], 'static_page');

/*
|--------------------------------------------------------------------------
| Module routes
|--------------------------------------------------------------------------
| Every enabled module contributes its own routes to the table.
*/
Container::getInstance()->get('modules')->registerRoutes($router);
