<?php

declare(strict_types=1);

namespace SoulCMS\Http\Handler;

use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * A component that can turn a request into a response.
 */
interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
