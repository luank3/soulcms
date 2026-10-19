<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Http\Exception\HttpException;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Http\Session\SessionManager;

/**
 * CSRF protection for state changing requests.
 *
 * The token is compared in constant time and can be supplied either as a
 * form field (`_token`) or as a header (`X-CSRF-Token`), which keeps AJAX
 * calls working without weakening the protection.
 */
final class CsrfMiddleware extends Middleware
{
    /** @var list<string> */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    /**
     * @param list<string> $exempt route name prefixes that skip the check
     */
    public function __construct(
        private SessionManager $session,
        private array $exempt = [],
        private string $field = '_token',
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $next->handle($request);
        }

        if ($this->isExempt($request)) {
            return $next->handle($request);
        }

        $token = $this->extractToken($request);

        if (!$this->session->validateToken($token)) {
            throw new HttpException(419, 'CSRF token mismatch. Please reload the page and try again.');
        }

        return $next->handle($request);
    }

    private function isExempt(Request $request): bool
    {
        $routeName = (string) $request->getAttribute('routeName', '');

        foreach ($this->exempt as $prefix) {
            if ($routeName !== '' && str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function extractToken(Request $request): ?string
    {
        $body = $request->getParsedBody();

        if (isset($body[$this->field]) && is_string($body[$this->field])) {
            return $body[$this->field];
        }

        $header = $request->getHeader('X-CSRF-Token');

        return $header !== null && $header !== '' ? $header : null;
    }
}
