<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Serves static assets straight from disk with long lived cache headers.
 *
 * The middleware never touches PHP, the database or the session, which makes
 * it the cheapest possible path through the application.
 */
final class StaticAssetMiddleware extends Middleware
{
    /** @var array<string,string> */
    private const MIME_TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'txt' => 'text/plain; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
    ];

    public function __construct(
        private string $publicPath,
        private int $maxAge = 31536000,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $path = $request->getPath();

        if ($path === '/' || str_contains($path, '..')) {
            return $next->handle($request);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!isset(self::MIME_TYPES[$extension])) {
            return $next->handle($request);
        }

        $file = $this->publicPath . $path;

        if (!is_file($file)) {
            return $next->handle($request);
        }

        $etag = '"' . hash('xxh128', (string) filemtime($file) . ':' . (string) filesize($file)) . '"';

        $response = new Response(200, [
            'Content-Type' => self::MIME_TYPES[$extension],
            'Cache-Control' => 'public, max-age=' . $this->maxAge . ', immutable',
            'ETag' => $etag,
            'Content-Length' => (string) filesize($file),
        ], (string) file_get_contents($file));

        $ifNoneMatch = $request->getHeader('If-None-Match');

        if ($ifNoneMatch !== null && str_contains($ifNoneMatch, $etag)) {
            return new Response(304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=' . $this->maxAge . ', immutable',
            ]);
        }

        return $response;
    }
}
