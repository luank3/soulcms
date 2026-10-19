<?php

declare(strict_types=1);

namespace SoulCMS\Http\Exception;

final class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(private array $allowedMethods = [], string $message = 'Method not allowed.')
    {
        $headers = $allowedMethods !== [] ? ['Allow' => implode(', ', $allowedMethods)] : [];

        parent::__construct(405, $message, $headers);
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
