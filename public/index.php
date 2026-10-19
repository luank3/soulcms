<?php

/**
 * SoulCMS front controller.
 *
 * Every request that is not a static file is routed here.
 */

declare(strict_types=1);

use SoulCMS\Foundation\Application;

define('SOULCMS_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$application = Application::create(dirname(__DIR__));

$response = $application->handle($application->container()->get('request'));

$response->send();
