<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Middleware\CompressionMiddleware;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * The compression layer must produce a stream the browser can actually
 * decode: "gzip" means the gzip container (RFC 1952), not a raw zlib stream
 * (RFC 1950). Getting this wrong makes every page fail with
 * ERR_CONTENT_DECODING_FAILED.
 */
final class CompressionMiddlewareTest extends TestCase
{
    public function testGzipResponseIsAValidGzipStream(): void
    {
        $body = str_repeat('SoulCMS compresses its own output. ', 200);

        $response = $this->compress($body, 'gzip, deflate, br');

        self::assertSame('gzip', $response->getHeader('Content-Encoding'));
        self::assertNotNull($response->getHeader('Vary'));

        $payload = $response->getBody();

        // The gzip magic number.
        self::assertSame("\x1f\x8b", substr($payload, 0, 2), 'The body must start with the gzip magic number.');

        // And it must decode back to the original content.
        $decoded = gzdecode($payload);

        self::assertIsString($decoded);
        self::assertSame($body, $decoded);
    }

    public function testContentLengthMatchesTheEncodedBody(): void
    {
        $response = $this->compress(str_repeat('a moderately sized body for the test ', 20), 'gzip');

        self::assertSame('gzip', $response->getHeader('Content-Encoding'));
        self::assertSame(
            (string) strlen($response->getBody()),
            (string) $response->getHeader('Content-Length')
        );
    }

    public function testSmallResponsesAreNotCompressed(): void
    {
        $response = $this->compress('tiny', 'gzip');

        self::assertNull($response->getHeader('Content-Encoding'));
        self::assertSame('tiny', $response->getBody());
    }

    public function testIncompressibleContentIsNotCompressed(): void
    {
        // Random data does not shrink, so the middleware must leave it alone.
        $body = random_bytes(4096);

        $response = $this->compress($body, 'gzip');

        self::assertNull($response->getHeader('Content-Encoding'));
        self::assertSame($body, $response->getBody());
    }

    public function testNonTextualContentIsNotCompressed(): void
    {
        $response = $this->compress(str_repeat('x', 4096), 'gzip', 'image/png');

        self::assertNull($response->getHeader('Content-Encoding'));
    }

    public function testClientWithoutAcceptEncodingGetsPlainBody(): void
    {
        $response = $this->compress(str_repeat('SoulCMS ', 500), '');

        self::assertNull($response->getHeader('Content-Encoding'));
    }

    private function compress(string $body, string $acceptEncoding, string $contentType = 'text/html; charset=UTF-8'): Response
    {
        $destination = new class($body, $contentType) implements RequestHandlerInterface {
            public function __construct(private string $body, private string $contentType)
            {
            }

            public function handle(Request $request): Response
            {
                return new Response(200, ['Content-Type' => $this->contentType], $this->body);
            }
        };

        $middleware = new CompressionMiddleware(256, 5);

        $request = new Request(
            'GET',
            new \SoulCMS\Http\Uri('http://localhost', 'localhost', null, '/', '', ''),
            $acceptEncoding === '' ? [] : ['Accept-Encoding' => $acceptEncoding]
        );

        return $middleware->process($request, $destination);
    }
}
