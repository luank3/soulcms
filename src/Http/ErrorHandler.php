<?php

declare(strict_types=1);

namespace SoulCMS\Http;

use SoulCMS\Foundation\Container;
use SoulCMS\Http\Exception\HttpException;
use SoulCMS\Support\Logger;
use Throwable;

/**
 * Turns exceptions into HTTP responses.
 *
 * The handler lives inside the middleware pipeline so that error responses
 * receive the same treatment as successful ones (compression, security
 * headers, conditional GET). It is used by the routing handler, which
 * catches the exceptions raised while dispatching a route, and by the kernel
 * as a last resort.
 */
final class ErrorHandler
{
    public function __construct(private Container $container)
    {
    }

    public function render(HttpException $exception, Request $request): Response
    {
        $response = Response::html(
            $this->view($exception->getStatusCode(), $exception->getMessage(), $request),
            $exception->getStatusCode()
        );

        return $response->withHeaders($exception->getHeaders());
    }

    public function renderThrowable(Throwable $exception, Request $request): Response
    {
        $debug = (bool) $this->container->get('config')->get('app.debug', false);

        $this->log($exception);

        $message = $debug
            ? $exception->getMessage() . ' (' . $exception->getFile() . ':' . $exception->getLine() . ')'
            : 'Máy chủ gặp sự cố khi xử lý yêu cầu.';

        return Response::html($this->view(500, $message, $request), 500);
    }

    private function view(int $status, string $message, Request $request): string
    {
        $views = $this->container->get('view');

        if ($views instanceof \SoulCMS\View\ViewFactory && $views->exists('errors/' . $status)) {
            return $views->render('errors/' . $status, [
                'status' => $status,
                'message' => $message,
            ], 'layouts/app');
        }

        return sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>%1$d</title>'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;margin:0;display:grid;place-items:center;min-height:100vh;background:#0f1115;color:#e6e6e6}'
            . 'main{max-width:38rem;padding:2rem;text-align:center}h1{font-size:3rem;margin:0 0 .5rem}p{opacity:.75}</style></head>'
            . '<body><main><h1>%1$d</h1><p>%2$s</p></main></body></html>',
            $status,
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );
    }

    private function log(Throwable $exception): void
    {
        $logger = $this->container->get('logger');

        if ($logger instanceof Logger) {
            $logger->error($exception->getMessage(), [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }
}
