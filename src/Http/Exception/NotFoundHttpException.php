<?php

declare(strict_types=1);

namespace SoulCMS\Http\Exception;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'The requested resource was not found.', array $headers = [])
    {
        parent::__construct(404, $message, $headers);
    }
}
