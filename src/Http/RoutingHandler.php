<?php

declare(strict_types=1);

namespace SoulCMS\Http;

use SoulCMS\Foundation\Container;
use SoulCMS\Http\Exception\HttpException;
use SoulCMS\Http\Exception\MethodNotAllowedHttpException;
use SoulCMS\Http\Exception\NotFoundHttpException;
use SoulCMS\Http\Handler\CallableHandler;
use SoulCMS\Http\Handler\ControllerHandler;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Middleware\Middleware;
use SoulCMS\Routing\Router;

/**
 * Terminal handler of the global pipeline: resolves the route, appends the
 * route specific middleware and dispatches to the controller.
 */
final class RoutingHandler implements RequestHandlerInterface
{
    public function __construct(
        private Router $router,
        private Container $container,
        private ErrorHandler $errors,
        private UrlRules $rules,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (HttpException $exception) {
            // The error is rendered here so that the response still passes
            // through the middleware stack (compression, headers, caching).
            return $this->errors->render($exception, $request);
        }
    }

    private function dispatch(Request $request): Response
    {
        $request = $this->applyUrlRules($request);

        $match = $this->router->dispatch($request);

        if ($match === null) {
            $allowed = $this->router->allowedMethods($request->getPath());

            if ($allowed !== []) {
                throw new MethodNotAllowedHttpException($allowed);
            }

            throw new NotFoundHttpException();
        }

        $request = $request->withAttribute('routeParams', $match['parameters']);

        if ($match['name'] !== null) {
            $request = $request->withAttribute('routeName', $match['name']);
        }

        $handler = $this->buildHandler($match['handler']);
        $middleware = $this->buildMiddleware($match['middleware']);

        if ($middleware === []) {
            return $handler->handle($request);
        }

        $pipeline = new Pipeline($middleware);

        return $pipeline->handle($request, $handler);
    }

    /**
     * Apply the administrator's URL rules: blocked paths are refused and
     * rewritten paths are matched as if the visitor had typed the target.
     */
    private function applyUrlRules(Request $request): Request
    {
        $path = $request->getPath();

        if ($this->rules->isDisabled($path)) {
            throw new NotFoundHttpException();
        }

        $target = $this->rules->rewrite($path, $request->getMethod());

        if ($target === null) {
            return $request;
        }

        $parts = explode('?', $target, 2);
        $uri = $request->getUri();

        $rewritten = new Uri(
            $uri->getScheme(),
            $uri->getHost(),
            $uri->getPort(),
            '/' . ltrim($parts[0], '/'),
            $parts[1] ?? '',
            ''
        );

        return $request->withUri($rewritten);
    }

    private function buildHandler(mixed $handler): RequestHandlerInterface
    {
        if ($handler instanceof RequestHandlerInterface) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            return new ControllerHandler($this->container, (string) $class, (string) $method);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);

            return new ControllerHandler($this->container, $class, $method);
        }

        if (is_callable($handler)) {
            return new CallableHandler($handler);
        }

        throw new \InvalidArgumentException('Unsupported route handler.');
    }

    /**
     * @param list<string> $middleware
     * @return list<Middleware>
     */
    private function buildMiddleware(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $item) {
            if ($item instanceof Middleware) {
                $resolved[] = $item;

                continue;
            }

            if (is_string($item) && class_exists($item)) {
                $instance = $this->container->get($item);

                if ($instance instanceof Middleware) {
                    $resolved[] = $instance;
                }
            }
        }

        return $resolved;
    }
}
