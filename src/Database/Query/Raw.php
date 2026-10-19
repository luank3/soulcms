<?php

declare(strict_types=1);

namespace SoulCMS\Database\Query;

/**
 * An explicit SQL expression.
 *
 * The query builder only ever quotes plain identifiers. Anything that is not
 * a plain identifier — a function call, a subquery, an arithmetic
 * expression — has to be wrapped in a `Raw` object, which makes every
 * unquoted fragment of SQL visible in the source code and impossible to
 * reach accidentally from user input.
 */
final class Raw
{
    public function __construct(public readonly string $sql)
    {
    }

    /**
     * A raw expression, e.g. `COUNT(posts.id) AS count`.
     */
    public static function expr(string $sql): self
    {
        return new self($sql);
    }

    public function __toString(): string
    {
        return $this->sql;
    }
}
