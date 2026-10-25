<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Support\Lang;

final class LocaleMiddleware extends Middleware
{
    public function __construct(private readonly string $defaultLocale = 'vi')
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $locale = $request->getCookie('soulcms_locale', $this->defaultLocale);
        Lang::setLocale(is_string($locale) && in_array($locale, ['vi', 'en'], true)
            ? $locale
            : $this->defaultLocale);

        return $next->handle($request);
    }
}
