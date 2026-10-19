<?php

declare(strict_types=1);

namespace SoulCMS\Database\Exception;

use PDOException;
use RuntimeException;
use Throwable;

/**
 * Database level failure with the offending statement attached.
 */
final class DatabaseException extends RuntimeException
{
    private string $sql = '';

    /**
     * @var array<string|int,mixed>
     */
    private array $bindings = [];

    public static function fromQuery(Throwable $exception, string $sql, array $bindings = []): self
    {
        $message = $exception instanceof PDOException
            ? ($exception->getMessage() . ' (SQLSTATE ' . ($exception->getCode() ?: 'unknown') . ')')
            : $exception->getMessage();

        $instance = new self($message, 0, $exception);
        $instance->sql = $sql;
        $instance->bindings = $bindings;

        return $instance;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<string|int,mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
