<?php

declare(strict_types=1);

namespace SoulCMS\Http\Exception;

/**
 * Raised when user supplied data fails validation.
 */
final class ValidationException extends HttpException
{
    /**
     * @param array<string,list<string>> $errors
     */
    public function __construct(private array $errors = [], string $message = 'The given data was invalid.')
    {
        parent::__construct(422, $message);
    }

    /**
     * @return array<string,list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
