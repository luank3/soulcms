<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

/**
 * Requires at least the administrator level (126).
 */
final class RequireAdminMiddleware extends RequireLevelMiddleware
{
    protected function minimumLevel(): int
    {
        return 126;
    }
}
