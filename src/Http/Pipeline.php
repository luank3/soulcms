<?php

declare(strict_types=1);

namespace SoulCMS\Http;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Middleware\Middleware;
use SoulCMS\Http\Middleware\MiddlewareStack;

/**
 * Runs a stack of middleware around a final handler.
 *
 * The pipeline is implemented with an explicit cursor instead of recursive
 * closure wrapping, which keeps the stack depth flat and avoids allocating
 * one closure per middleware per request.
 */
final class Pipeline
{
    /**
     * @param list<Middleware|MiddlewareStack> $middleware
     */
    public function __construct(private array $middleware)
    {
    }

    public function handle(Request $request, RequestHandlerInterface $destination): Response
    {
        $cursor = new PipelineCursor($this->middleware, $destination);

        return $cursor->handle($request);
    }
}
