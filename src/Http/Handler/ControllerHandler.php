<?php

declare(strict_types=1);

namespace SoulCMS\Http\Handler;

use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Invokes a controller action.
 *
 * Action signature convention
 * ---------------------------
 * The first argument is always the (immutable) Request, followed by the route
 * parameters in the order they appear in the route pattern. Every extra route
 * parameter remains available through `$request->getAttribute()`.
 *
 * The reflection metadata is memoised per class::method, so the hot path
 * performs zero reflection calls after the first request.
 */
final class ControllerHandler implements RequestHandlerInterface
{
    /** @var array<string,array{parameters:list<ReflectionMethod>}> */
    private static array $cache = [];

    public function __construct(
        private Container $container,
        private string $class,
        private string $method,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!class_exists($this->class)) {
            throw new RuntimeException(sprintf('Controller "%s" does not exist.', $this->class));
        }

        $controller = $this->container->get($this->class);

        if (!method_exists($controller, $this->method)) {
            throw new RuntimeException(sprintf(
                'Action "%s::%s()" does not exist.',
                $this->class,
                $this->method
            ));
        }

        $reflection = $this->reflect();
        $parameters = $request->getAttribute('routeParams', []);
        $arguments = [$request->withAttributes($parameters)];

        $index = 0;

        foreach ($reflection['parameters'] as $parameter) {
            ++$index;

            if ($index === 1) {
                continue; // The request itself.
            }

            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                $arguments[] = null;

                continue;
            }

            throw new RuntimeException(sprintf(
                'Cannot resolve argument "$%s" for "%s::%s()".',
                $name,
                $this->class,
                $this->method
            ));
        }

        if (method_exists($controller, 'setActionRequest')) {
            $controller->setActionRequest($arguments[0]);
        }

        $result = $controller->{$this->method}(...$arguments);

        return $this->toResponse($result, $request);
    }

    /**
     * @return array{parameters:list<ReflectionMethod>}
     */
    private function reflect(): array
    {
        $key = $this->class . '::' . $this->method;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $method = new ReflectionMethod($this->class, $this->method);

        return self::$cache[$key] = ['parameters' => $method->getParameters()];
    }

    private function toResponse(mixed $result, Request $request): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result === null) {
            return new Response(204);
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return Response::html((string) $result);
    }
}
