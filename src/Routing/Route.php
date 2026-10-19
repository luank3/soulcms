<?php

declare(strict_types=1);

namespace SoulCMS\Routing;

use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * A single route definition.
 *
 * Routes are immutable: registering a route produces a new instance, which
 * keeps the compiled route table safe to cache between requests.
 */
final class Route
{
    /** @var list<string> */
    private array $methods;

    /** @var list<string> */
    private array $middleware = [];

    /** @var array<string,mixed> */
    private array $defaults = [];

    private ?string $name = null;

    private ?string $regex = null;

    /** @var list<string> */
    private array $variables = [];

    private string $staticPrefix = '/';

    private int $segmentCount = 1;

    /**
     * @param list<string>              $methods
     * @param list<string>              $middleware
     * @param array<string,mixed>       $defaults
     */
    public function __construct(
        private string $pattern,
        array $methods,
        private mixed $handler,
        array $middleware = [],
        array $defaults = [],
        ?string $name = null,
    ) {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
        $this->middleware = $middleware;
        $this->defaults = $defaults;
        $this->name = $name;

        $this->compile();
    }

    private function compile(): void
    {
        $compiled = RouteCompiler::compile($this->pattern);

        $this->regex = $compiled['regex'];
        $this->variables = $compiled['variables'];
        $this->staticPrefix = $compiled['staticPrefix'];
        $this->segmentCount = $compiled['segmentCount'];
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return list<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function supportsMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * @return list<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return array<string,mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRegex(): string
    {
        return (string) $this->regex;
    }

    /**
     * @return list<string>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getStaticPrefix(): string
    {
        return $this->staticPrefix;
    }

    public function getSegmentCount(): int
    {
        return $this->segmentCount;
    }

    public function isStatic(): bool
    {
        return $this->variables === [];
    }

    /**
     * Match a path against this route.
     *
     * @return array<string,string>|null
     */
    public function match(string $path): ?array
    {
        if (!preg_match((string) $this->regex, $path, $matches)) {
            return null;
        }

        $parameters = [];

        foreach ($this->variables as $variable) {
            if (isset($matches[$variable]) && $matches[$variable] !== '') {
                $parameters[$variable] = $matches[$variable];
            }
        }

        return $parameters + $this->defaults;
    }

    /**
     * Serialisable representation used by the route cache.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'pattern' => $this->pattern,
            'methods' => $this->methods,
            'handler' => $this->handler,
            'middleware' => $this->middleware,
            'defaults' => $this->defaults,
            'name' => $this->name,
            'regex' => $this->regex,
            'variables' => $this->variables,
            'staticPrefix' => $this->staticPrefix,
            'segmentCount' => $this->segmentCount,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $route = new self(
            (string) $data['pattern'],
            (array) $data['methods'],
            $data['handler'],
            (array) $data['middleware'],
            (array) $data['defaults'],
            isset($data['name']) ? (string) $data['name'] : null,
        );

        if (isset($data['regex'], $data['variables'], $data['staticPrefix'], $data['segmentCount'])) {
            $route->regex = (string) $data['regex'];
            $route->variables = (array) $data['variables'];
            $route->staticPrefix = (string) $data['staticPrefix'];
            $route->segmentCount = (int) $data['segmentCount'];
        }

        return $route;
    }
}
