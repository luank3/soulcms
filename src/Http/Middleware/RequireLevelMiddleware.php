<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

use SoulCMS\Auth\AuthManager;
use SoulCMS\Http\Exception\ForbiddenHttpException;
use SoulCMS\Http\Handler\RequestHandlerInterface;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * Base class for the privilege level guards.
 *
 * SoulCMS keeps the SketchCMS level semantics: 0 is a regular member, 120 is
 * a contributor, 126 an administrator and 127 a developer.
 */
abstract class RequireLevelMiddleware extends Middleware
{
    public function __construct(protected AuthManager $auth)
    {
    }

    abstract protected function minimumLevel(): int;

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!$this->auth->check()) {
            throw new ForbiddenHttpException('Authentication required.');
        }

        if ($this->auth->level() < $this->minimumLevel()) {
            throw new ForbiddenHttpException('You do not have permission to access this area.');
        }

        return $next->handle($request);
    }
}
