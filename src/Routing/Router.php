<?php

declare(strict_types=1);

namespace SoulCMS\Routing;

use Closure;
use LogicException;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Route registration facade.
 *
 * Routes may be registered as a flat list or inside groups. Groups apply a
 * path prefix and a middleware stack to every route they contain.
 */
final class Router
{
    private RouteCollection $routes;

    /** @var list<array{prefix:string,middleware:list<string>}> */
    private array $groups = [];

    /** @var array<string,string> */
    private array $patterns = [];

    public function __construct(?RouteCollection $routes = null)
    {
        $this->routes = $routes ?? new RouteCollection();
    }

    public function getCollection(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Register a route.
     *
     * @param list<string>|string $methods
     */
    public function add(
        string $pattern,
        mixed $handler,
        array|string $methods = 'GET',
        ?string $name = null,
        array $middleware = [],
        array $defaults = [],
    ): void {
        $pattern = $this->prefixPattern($pattern);
        $middleware = array_merge($this->currentMiddleware(), $middleware);

        $methodList = is_array($methods) ? $methods : explode('|', $methods);
        $methodList = array_values(array_filter(array_map('strtoupper', $methodList)));

        if ($methodList === []) {
            throw new LogicException('A route must declare at least one HTTP method.');
        }

        if ($name !== null && isset($this->patterns[$name]) && $this->patterns[$name] !== $pattern) {
            // Re-registering the same name for a different pattern is allowed
            // (useful for module overrides) but the name must stay unique.
            $this->patterns[$name] = $pattern;
        }

        if ($name !== null) {
            $this->patterns[$name] = $pattern;
        }

        $this->routes->add(new Route($pattern, $methodList, $handler, $middleware, $defaults, $name));
    }

    public function get(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add($pattern, $handler, 'GET', $name, $middleware);
    }

    public function post(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add($pattern, $handler, 'POST', $name, $middleware);
    }

    public function put(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add($pattern, $handler, 'PUT', $name, $middleware);
    }

    public function patch(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add($pattern, $handler, 'PATCH', $name, $middleware);
    }

    public function delete(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add($pattern, $handler, 'DELETE', $name, $middleware);
    }

    /**
     * Register a route that answers to every HTTP method.
     */
    public function any(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add($pattern, $handler, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $name, $middleware);
    }

    /**
     * Register a group of routes sharing a prefix and middleware stack.
     *
     * @param list<string> $middleware
     */
    public function group(string $prefix, array $middleware, Closure $callback): void
    {
        $this->groups[] = [
            'prefix' => $prefix,
            'middleware' => $middleware,
        ];

        try {
            $callback($this);
        } finally {
            array_pop($this->groups);
        }
    }

    /**
     * @return list<string>
     */
    private function currentMiddleware(): array
    {
        $middleware = [];

        foreach ($this->groups as $group) {
            $middleware = array_merge($middleware, $group['middleware']);
        }

        return $middleware;
    }

    private function prefixPattern(string $pattern): string
    {
        $prefix = '';

        foreach ($this->groups as $group) {
            $prefix .= '/' . trim($group['prefix'], '/');
        }

        $prefix = rtrim($prefix, '/');

        if ($prefix === '') {
            return $pattern;
        }

        if ($pattern === '/' || $pattern === '') {
            return $prefix === '' ? '/' : $prefix;
        }

        return $prefix . '/' . ltrim($pattern, '/');
    }

    public function freeze(): RouteCollection
    {
        $this->routes->freeze();

        return $this->routes;
    }

    /**
     * Match a request and return the handler + parameters.
     *
     * @return array{handler:mixed,parameters:array<string,string>,middleware:list<string>,name:?string}|null
     */
    public function dispatch(Request $request): ?array
    {
        $path = $request->getPath();
        $method = $request->getMethod();

        $match = $this->routes->match($method, $path);

        if ($match === null) {
            return null;
        }

        $route = $match['route'];

        return [
            'handler' => $route->getHandler(),
            'parameters' => $match['parameters'],
            'middleware' => $route->getMiddleware(),
            'name' => $route->getName(),
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedMethods(string $path): array
    {
        return $this->routes->allowedMethods($path);
    }

    /**
     * Build a URL for a named route.
     *
     * @param array<string,scalar|null> $parameters
     */
    public function url(string $name, array $parameters = [], bool $absolute = false, string $basePath = ''): string
    {
        $route = $this->routes->get($name);

        if ($route === null) {
            return $basePath . '/';
        }

        $path = $route->getPattern();

        foreach ($parameters as $key => $value) {
            $path = preg_replace('/\{' . preg_quote((string) $key, '/') . '(?::[^}]+)?\}/', (string) $value, $path) ?? $path;
        }

        // Remove any placeholder that was not provided.
        $path = preg_replace('/\{[^{}]*\}/', '', $path) ?? $path;
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path === '' || $path === '/') {
            $path = '/';
        }

        if ($absolute) {
            return $basePath . $path;
        }

        return $path;
    }

    /**
     * @return array<string,string>
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }
}
