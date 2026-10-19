<?php

declare(strict_types=1);

namespace SoulCMS\Http\Exception;

final class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'Access denied.', array $headers = [])
    {
        parent::__construct(403, $message, $headers);
    }
}
