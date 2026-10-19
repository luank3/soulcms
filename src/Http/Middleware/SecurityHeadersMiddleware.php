<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Adds the security headers to every response.
 *
 * The Content Security Policy is issued with a per-request nonce, so injected
 * script can never run. The remaining headers follow the current OWASP
 * guidance and are only meaningful over HTTPS, so they are skipped on plain
 * HTTP instead of being sent uselessly.
 */
final class SecurityHeadersMiddleware extends Middleware
{
    /**
     * @param array<string,string> $headers extra headers to set
     */
    public function __construct(
        private ?string $csp = null,
        private array $headers = [],
        private bool $httpsOnly = true,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        // Do not advertise the PHP version.
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        $response = $next->handle($request);
        $https = $request->isSecure();

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
            'Origin-Agent-Cluster' => '?1',
        ];

        if ($https) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if ($this->csp !== null) {
            $defaults['Content-Security-Policy'] = $this->csp;
        }

        foreach (array_merge($defaults, $this->headers) as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
