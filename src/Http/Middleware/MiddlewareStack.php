<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

/**
 * A named group of middleware that can be attached to a route.
 */
final class MiddlewareStack
{
    /** @var list<Middleware> */
    private array $middleware = [];

    public function __construct(Middleware ...$middleware)
    {
        $this->middleware = array_values($middleware);
    }

    /**
     * @param list<Middleware> $middleware
     */
    public static function of(array $middleware): self
    {
        $stack = new self();
        $stack->middleware = array_values($middleware);

        return $stack;
    }

    public function push(Middleware ...$middleware): self
    {
        foreach ($middleware as $item) {
            $this->middleware[] = $item;
        }

        return $this;
    }

    /**
     * @return list<Middleware>
     */
    public function all(): array
    {
        return $this->middleware;
    }

    public function isEmpty(): bool
    {
        return $this->middleware === [];
    }
}
