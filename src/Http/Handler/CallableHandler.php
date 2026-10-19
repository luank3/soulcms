<?php

declare(strict_types=1);

namespace SoulCMS\Http\Handler;

use Closure;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Adapts an arbitrary callable into a request handler.
 */
final class CallableHandler implements RequestHandlerInterface
{
    /** @var Closure(Request):Response */
    private Closure $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback(...);
    }

    public function handle(Request $request): Response
    {
        return ($this->callback)($request);
    }
}
