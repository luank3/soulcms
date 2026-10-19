<?php

declare(strict_types=1);

namespace SoulCMS\Http;

use SoulCMS\Auth\AuthManager;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\View\ViewFactory;

/**
 * Base class for the CMS controllers.
 *
 * Everything is injected by the container, which keeps the controllers
 * stateless and therefore trivially testable.
 */
abstract class Controller
{
    protected Container $container;

    protected Request $request;

    protected ViewFactory $view;

    protected AuthManager $auth;

    protected Config $config;

    /**
     * The request of the action currently being executed. It carries the
     * route parameters that the router attached to it.
     */
    private ?Request $actionRequest = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->request = $container->get(Request::class);
        $this->view = $container->get(ViewFactory::class);
        $this->auth = $container->get(AuthManager::class);
        $this->config = $container->get(Config::class);
    }

    /**
     * Attach the request of the running action (with the route parameters).
     */
    public function setActionRequest(Request $request): void
    {
        $this->actionRequest = $request;
    }

    /**
     * The request of the running action.
     */
    protected function currentRequest(): Request
    {
        return $this->actionRequest ?? $this->request;
    }

    /**
     * A route parameter.
     */
    protected function param(string $name, mixed $default = null): mixed
    {
        $params = $this->currentRequest()->getAttribute('routeParams', []);

        return is_array($params) ? ($params[$name] ?? $default) : $default;
    }

    /**
     * A sanitised query string parameter.
     */
    protected function query(string $name, mixed $default = null): mixed
    {
        return $this->currentRequest()->getQueryParam($name, $default);
    }

    /**
     * A sanitised request body field.
     */
    protected function input(string $name, mixed $default = null): mixed
    {
        $body = $this->currentRequest()->getParsedBody();

        return $body[$name] ?? $default;
    }

    protected function isPost(): bool
    {
        return $this->currentRequest()->getMethod() === 'POST';
    }

    protected function wantsJson(): bool
    {
        return $this->currentRequest()->wantsJson();
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function html(string $view, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return Response::html($this->view->render($view, $data, $layout));
    }
}
