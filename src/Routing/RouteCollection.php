<?php

declare(strict_types=1);

namespace SoulCMS\Routing;

/**
 * Immutable, indexed collection of routes.
 *
 * The collection maintains two lookup structures:
 *
 *  1. A static map  - exact `path => Route` lookups, O(1).
 *  2. Dynamic buckets - routes containing parameters, bucketed by the number
 *     of path segments so that a request only ever tests routes of a
 *     compatible shape. Each bucket is additionally protected by a static
 *     prefix check, so a failing match is rejected with a string comparison
 *     instead of a regular expression evaluation.
 *
 * The result is a router that stays fast as the route table grows, which is
 * the usual failure mode of regex-per-route implementations.
 */
final class RouteCollection
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string,array<string,Route>> method => path => route */
    private array $static = [];

    /** @var array<string,array<int,list<Route>>> method => segmentCount => routes */
    private array $dynamic = [];

    /** @var array<string,list<string>> path => methods */
    private array $pathMethods = [];

    /** @var array<string,Route> */
    private array $named = [];

    private bool $frozen = false;

    public function add(Route $route): void
    {
        if ($this->frozen) {
            throw new \LogicException('Cannot add routes to a frozen collection.');
        }

        $this->routes[] = $route;

        if ($route->getName() !== null) {
            $this->named[$route->getName()] = $route;
        }
    }

    /**
     * Build the lookup indexes. Must be called once all routes are registered.
     */
    public function freeze(): void
    {
        $this->static = [];
        $this->dynamic = [];
        $this->pathMethods = [];

        foreach ($this->routes as $route) {
            $pattern = $route->getPattern();

            if (!isset($this->pathMethods[$pattern])) {
                $this->pathMethods[$pattern] = [];
            }

            foreach ($route->getMethods() as $method) {
                $this->pathMethods[$pattern][] = $method;

                if ($route->isStatic()) {
                    $this->static[$method][$pattern] = $route;

                    continue;
                }

                $this->dynamic[$method][$route->getSegmentCount()][] = $route;
            }
        }

        foreach ($this->dynamic as $method => $buckets) {
            foreach ($buckets as $count => $bucket) {
                usort($bucket, static fn (Route $a, Route $b): int => self::specificity($b) <=> self::specificity($a));
                $this->dynamic[$method][$count] = $bucket;
            }
        }

        $this->frozen = true;
    }

    /**
     * Higher value = matched earlier.
     *
     * Routes with more literal characters are more specific than routes
     * with parameters, so "/articles/{slug}.html" wins over
     * "/articles/{slug}".
     */
    private static function specificity(Route $route): int
    {
        $literal = (string) preg_replace('/\{[^{}]*\}/', '', $route->getPattern());

        return strlen($literal) * 100 - $route->getSegmentCount();
    }

    /**
     * @return list<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function get(string $name): ?Route
    {
        return $this->named[$name] ?? null;
    }

    /**
     * @return array<string,list<string>>
     */
    public function getPathMethods(): array
    {
        return $this->pathMethods;
    }

    /**
     * Match a path/method pair.
     *
     * @return array{route:Route,parameters:array<string,string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);

        $matched = $this->matchExact($method, $path);

        if ($matched !== null) {
            return $matched;
        }

        if ($method === 'HEAD') {
            $matched = $this->matchExact('GET', $path);

            if ($matched !== null) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * @return array{route:Route,parameters:array<string,string>}|null
     */
    private function matchExact(string $method, string $path): ?array
    {
        if (isset($this->static[$method][$path])) {
            return ['route' => $this->static[$method][$path], 'parameters' => $this->static[$method][$path]->getDefaults()];
        }

        $buckets = $this->dynamic[$method] ?? [];

        if ($buckets === []) {
            return null;
        }

        $segments = $path === '/' ? 1 : count(explode('/', trim($path, '/')));

        // Exact segment count first, then the remaining buckets (routes with
        // optional trailing segments may match a shorter path).
        $order = [$segments];

        foreach (array_keys($buckets) as $count) {
            if ($count !== $segments) {
                $order[] = $count;
            }
        }

        foreach ($order as $count) {
            $bucket = $buckets[$count] ?? [];

            foreach ($bucket as $route) {
                $prefix = $route->getStaticPrefix();

                if ($prefix !== '/' && !str_starts_with($path, $prefix)) {
                    continue;
                }

                $parameters = $route->match($path);

                if ($parameters !== null) {
                    return ['route' => $route, 'parameters' => $parameters];
                }
            }
        }

        return null;
    }

    /**
     * Determine which methods could serve the given path. Used to answer with
     * 405 + Allow header instead of a misleading 404.
     *
     * @return list<string>
     */
    public function allowedMethods(string $path): array
    {
        $allowed = [];

        foreach (array_keys($this->static) as $method) {
            if (isset($this->static[$method][$path])) {
                $allowed[] = $method;
            }
        }

        if ($allowed === []) {
            foreach ($this->dynamic as $method => $buckets) {
                foreach ($buckets as $bucket) {
                    foreach ($bucket as $route) {
                        $prefix = $route->getStaticPrefix();

                        if ($prefix !== '/' && !str_starts_with($path, $prefix)) {
                            continue;
                        }

                        if ($route->match($path) !== null) {
                            $allowed[] = $method;
                            break 2;
                        }
                    }
                }
            }
        }

        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        sort($allowed);

        return array_values(array_unique($allowed));
    }

    /**
     * Serialisable snapshot of the whole collection.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'routes' => array_map(static fn (Route $route): array => $route->toArray(), $this->routes),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $collection = new self();

        foreach ((array) ($data['routes'] ?? []) as $routeData) {
            $collection->add(Route::fromArray((array) $routeData));
        }

        $collection->freeze();

        return $collection;
    }
}
