<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Middleware\LocaleMiddleware;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Http\Uri;
use SoulCMS\Support\Lang;

final class LocaleMiddlewareTest extends TestCase
{
    public function testSupportedLocaleCookieSelectsCatalogueForCurrentRequest(): void
    {
        $next = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return Response::text(Lang::locale());
            }
        };

        $response = (new LocaleMiddleware('vi'))->process(
            new Request('GET', Uri::fromString('/'), cookies: ['soulcms_locale' => 'en']),
            $next
        );

        self::assertSame('en', $response->getBody());
    }

    public function testUnsupportedLocaleCookieFallsBackToConfiguredLocale(): void
    {
        $next = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return Response::text(Lang::locale());
            }
        };

        $response = (new LocaleMiddleware('vi'))->process(
            new Request('GET', Uri::fromString('/'), cookies: ['soulcms_locale' => 'fr']),
            $next
        );

        self::assertSame('vi', $response->getBody());
    }
}
