<?php

declare(strict_types=1);

namespace SoulCMS\Http\Exception;

final class BadRequestHttpException extends HttpException
{
    public function __construct(string $message = 'Bad request.', array $headers = [])
    {
        parent::__construct(400, $message, $headers);
    }
}
