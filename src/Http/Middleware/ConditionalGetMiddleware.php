<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Answers conditional requests with 304 Not Modified.
 *
 * The ETag is derived from the response body, so a page served from the
 * application cache still produces a correct validator. This removes the
 * transfer cost of repeated page views for logged in users.
 */
final class ConditionalGetMiddleware extends Middleware
{
    public function __construct(private bool $enabled = true)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        if (!$this->enabled || $response->getStatusCode() !== 200) {
            return $response;
        }

        $body = $response->getBody();

        if ($body === '' || $response->hasHeader('ETag')) {
            return $response;
        }

        $etag = '"' . hash('xxh128', $body) . '"';
        $response = $response->withHeader('ETag', $etag);
        $response = $response->withHeader('Cache-Control', $response->getHeader('Cache-Control') ?? 'private, must-revalidate');

        $ifNoneMatch = $request->getHeader('If-None-Match');

        if ($ifNoneMatch !== null && $this->matches($ifNoneMatch, $etag)) {
            return new Response(304, [
                'ETag' => $etag,
                'Cache-Control' => $response->getHeader('Cache-Control') ?? 'private, must-revalidate',
            ]);
        }

        return $response;
    }

    private function matches(string $header, string $etag): bool
    {
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '*' || $candidate === $etag) {
                return true;
            }

            if (str_starts_with($candidate, 'W/') && substr($candidate, 2) === $etag) {
                return true;
            }
        }

        return false;
    }
}
