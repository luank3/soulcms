<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Compresses textual responses.
 *
 * - Uses Brotli when the client advertises it, gzip otherwise.
 * - Skips responses that are already compressed (images, archives) or that
 *   are smaller than the configured threshold.
 * - Honours `Vary: Accept-Encoding` so caches stay correct.
 */
final class CompressionMiddleware extends Middleware
{
    private const COMPRESSIBLE = [
        'text/', 'application/json', 'application/javascript', 'application/xml',
        'text/css', 'text/html', 'text/plain', 'image/svg+xml', 'application/rss+xml',
    ];

    public function __construct(
        private int $threshold = 1024,
        private int $level = 5,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        if ($response->getHeader('Content-Encoding') !== null) {
            return $response;
        }

        $body = $response->getBody();

        if (strlen($body) < $this->threshold) {
            return $response;
        }

        $contentType = (string) $response->getHeader('Content-Type');

        if (!$this->isCompressible($contentType)) {
            return $response;
        }

        $encoding = $this->negotiateEncoding($request);

        if ($encoding === null) {
            return $response;
        }

        $compressed = $encoding === 'br'
            ? (function_exists('brotli_compress') ? brotli_compress($body, $this->level) : false)
            : gzencode($body, $this->level);

        if ($compressed === false || strlen($compressed) >= strlen($body)) {
            return $response;
        }

        return $response
            ->withHeader('Content-Encoding', $encoding)
            ->withHeader('Vary', 'Accept-Encoding')
            ->withHeader('Content-Length', (string) strlen($compressed))
            ->withBody($compressed);
    }

    private function isCompressible(string $contentType): bool
    {
        foreach (self::COMPRESSIBLE as $type) {
            if (str_starts_with($contentType, $type)) {
                return true;
            }
        }

        return false;
    }

    private function negotiateEncoding(Request $request): ?string
    {
        $accept = (string) $request->getHeader('Accept-Encoding');

        if ($accept === '') {
            return null;
        }

        $accepted = [];

        foreach (explode(',', $accept) as $part) {
            $bits = explode(';', trim($part));
            $encoding = strtolower(trim($bits[0]));
            $quality = 1.0;

            if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $matches)) {
                $quality = (float) $matches[1];
            }

            $accepted[$encoding] = $quality;
        }

        if (($accepted['br'] ?? 0) > 0 && function_exists('brotli_compress')) {
            return 'br';
        }

        if (($accepted['gzip'] ?? 0) > 0 || ($accepted['*'] ?? 0) > 0) {
            return 'gzip';
        }

        return null;
    }
}
