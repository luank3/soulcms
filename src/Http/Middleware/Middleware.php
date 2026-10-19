<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Base class for middleware.
 *
 * Middleware may short-circuit the pipeline by returning a response, or by
 * delegating to the next handler.
 */
abstract class Middleware
{
    /**
     * @param RequestHandlerInterface $next
     */
    abstract public function process(Request $request, RequestHandlerInterface $next): Response;
}
