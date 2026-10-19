<?php

declare(strict_types=1);

namespace SoulCMS\Http\Exception;

use RuntimeException;

/**
 * Base class for all HTTP-aware exceptions.
 */
class HttpException extends RuntimeException
{
    /** @var array<string,string> */
    private array $headers;

    public function __construct(
        private int $statusCode = 500,
        string $message = '',
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode, $previous);

        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'HTTP Error ' . $status,
        };
    }
}
