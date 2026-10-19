<?php

declare(strict_types=1);

namespace SoulCMS\Http\Exception;

final class UnauthorizedHttpException extends HttpException
{
    public function __construct(string $message = 'Authentication required.', array $headers = [])
    {
        parent::__construct(401, $message, $headers);
    }
}
