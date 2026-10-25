<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Media\Module;
use SoulCMS\Http\Middleware\AuthenticateMiddleware;
use SoulCMS\Routing\Router;

final class MediaRoutesTest extends TestCase
{
    public function testLibraryAndUploadRoutesRequireLoginAndDownloadUsesMediaId(): void
    {
        $router = new Router();
        (new Module())->routes($router);

        $library = $router->getCollection()->get('media.library');
        self::assertNotNull($library);
        self::assertSame('/media/library', $library->getPattern());
        self::assertSame(['GET'], $library->getMethods());
        self::assertSame([AuthenticateMiddleware::class], $library->getMiddleware());

        $upload = $router->getCollection()->get('media.upload');
        self::assertNotNull($upload);
        self::assertSame(['POST'], $upload->getMethods());
        self::assertSame([AuthenticateMiddleware::class], $upload->getMiddleware());

        $download = $router->getCollection()->get('media.download');
        self::assertNotNull($download);
        self::assertSame('/media/download/{id:[0-9]+}/{filename:[^/]+}', $download->getPattern());
        self::assertSame(['GET'], $download->getMethods());
        self::assertSame([AuthenticateMiddleware::class], $download->getMiddleware());
    }
}
