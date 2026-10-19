<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Auth\AuthManager;
use SoulCMS\Http\Exception\ForbiddenHttpException;
use SoulCMS\Http\Exception\UnauthorizedHttpException;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Requires an authenticated user.
 */
final class AuthenticateMiddleware extends Middleware
{
    public function __construct(
        private AuthManager $auth,
        private ?string $redirectTo = null,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!$this->auth->check()) {
            if ($this->redirectTo !== null) {
                return Response::redirect($this->redirectTo);
            }

            throw new UnauthorizedHttpException();
        }

        return $next->handle($request);
    }
}
