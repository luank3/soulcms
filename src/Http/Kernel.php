<?php

declare(strict_types=1);

namespace SoulCMS\Http;

use SoulCMS\Foundation\Container;
use SoulCMS\Http\Exception\HttpException;
use SoulCMS\Http\Middleware\Middleware;
use SoulCMS\Http\UrlRules;
use SoulCMS\Routing\Router;
use Throwable;

/**
 * The HTTP kernel: turns a Request into a Response.
 *
 * Responsibilities
 * ----------------
 *  - Run the global middleware stack.
 *  - Resolve the route and build the route specific middleware stack.
 *  - Translate exceptions into HTTP responses.
 */
final class Kernel
{
    /** @var list<Middleware> */
    private array $middleware = [];

    private bool $middlewareResolved = false;

    public function __construct(
        private Container $container,
        private Router $router,
        private ErrorHandler $errors,
    ) {
    }

    /**
     * @param list<Middleware> $middleware
     */
    public function setMiddleware(array $middleware): void
    {
        $this->middleware = $middleware;
        $this->middlewareResolved = true;
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->sendThroughPipeline($request);
        } catch (HttpException $exception) {
            $response = $this->errors->render($exception, $request);
        } catch (Throwable $exception) {
            $response = $this->errors->renderThrowable($exception, $request);
        }

        return $response;
    }

    private function sendThroughPipeline(Request $request): Response
    {
        $pipeline = new Pipeline($this->resolveMiddleware());

        return $pipeline->handle(
            $request,
            new RoutingHandler(
                $this->router,
                $this->container,
                $this->errors,
                $this->container->get(UrlRules::class)
            )
        );
    }

    /**
     * @return list<Middleware>
     */
    private function resolveMiddleware(): array
    {
        if ($this->middlewareResolved) {
            return $this->middleware;
        }

        foreach ((array) $this->container->get('config')->get('app.middleware', []) as $class) {
            if (is_string($class) && class_exists($class)) {
                $instance = $this->container->get($class);

                if ($instance instanceof Middleware) {
                    $this->middleware[] = $instance;
                }
            }
        }

        $this->middlewareResolved = true;

        return $this->middleware;
    }
}
