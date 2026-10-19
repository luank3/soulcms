<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Echoes the correlation id of the request back to the client.
 *
 * The id is also written to the log, so a support request can be matched
 * with the exact request that caused it.
 */
final class RequestIdMiddleware extends Middleware
{
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        return $response->withHeader('X-Request-Id', $request->getId());
    }
}
