<?php

declare(strict_types=1);

namespace SoulCMS\Http\Middleware;

/**
 * Requires at least the contributor level (120).
 */
final class RequireStaffMiddleware extends RequireLevelMiddleware
{
    protected function minimumLevel(): int
    {
        return 120;
    }
}
