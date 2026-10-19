<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Exception\HttpException;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Support\RateLimiter;

/**
 * Throttles mutating requests per client IP.
 */
final class RateLimitMiddleware extends Middleware
{
    /**
     * @param int $maxAttempts requests allowed per window
     * @param int $decaySeconds window length
     * @param list<string> $methods HTTP methods that are throttled
     */
    public function __construct(
        private RateLimiter $limiter,
        private int $maxAttempts = 30,
        private int $decaySeconds = 60,
        private array $methods = ['POST', 'PUT', 'PATCH', 'DELETE'],
        private string $prefix = 'global',
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!in_array($request->getMethod(), $this->methods, true)) {
            return $next->handle($request);
        }

        $key = $this->prefix . ':' . $request->getClientIp();

        if (!$this->limiter->hit($key, $this->maxAttempts, $this->decaySeconds)) {
            $retryAfter = $this->limiter->availableIn($key, $this->decaySeconds);

            throw new HttpException(429, 'Too many requests. Please try again later.', [
                'Retry-After' => (string) max(1, $retryAfter),
            ]);
        }

        return $next->handle($request);
    }
}
