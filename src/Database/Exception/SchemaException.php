<?php

declare(strict_types=1);

namespace SoulCMS\Database\Exception;

use RuntimeException;

/**
 * Raised when the requested table or column does not exist.
 */
final class SchemaException extends RuntimeException
{
}
