<?php

declare(strict_types=1);

namespace SoulCMS\Http;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Middleware\Middleware;
use SoulCMS\Http\Middleware\MiddlewareStack;

/**
 * Stateful cursor over a middleware stack.
 */
final class PipelineCursor implements RequestHandlerInterface
{
    private int $index = 0;

    /**
     * @param list<Middleware|MiddlewareStack> $middleware
     */
    public function __construct(
        private array $middleware,
        private RequestHandlerInterface $destination,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (isset($this->middleware[$this->index])) {
            $current = $this->middleware[$this->index];
            ++$this->index;

            if ($current instanceof MiddlewareStack) {
                return $current->handle($request, $this);
            }

            return $current->process($request, $this);
        }

        return $this->destination->handle($request);
    }
}
