<?php

declare(strict_types=1);

namespace SoulCMS\Database\Schema;

/**
 * Portable column definition.
 *
 * The type is expressed with a driver neutral vocabulary so that the very
 * same definition can be rendered as MySQL, PostgreSQL or SQLite DDL.
 */
final class Column
{
    public const TYPE_ID = 'id';
    public const TYPE_INT = 'int';
    public const TYPE_INT_UNSIGNED = 'intUnsigned';
    public const TYPE_BIGINT = 'bigint';
    public const TYPE_BIGINT_UNSIGNED = 'bigintUnsigned';
    public const TYPE_STRING = 'string';
    public const TYPE_TEXT = 'text';
    public const TYPE_LONGTEXT = 'longtext';
    public const TYPE_BOOL = 'bool';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_TIMESTAMP = 'timestamp';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_JSON = 'json';
    public const TYPE_FLOAT = 'float';

    private bool $nullable = false;

    private mixed $default = null;

    private bool $hasDefault = false;

    private bool $autoIncrement = false;

    private bool $primary = false;

    private bool $unique = false;

    private bool $index = false;

    private ?int $length = null;

    private int $precision = 10;

    private int $scale = 2;

    private ?string $collation = null;

    private function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {
    }

    public static function make(string $name, string $type): self
    {
        return new self($name, $type);
    }

    /**
     * Auto-incrementing primary key.
     */
    public static function id(string $name = 'id'): self
    {
        $column = new self($name, self::TYPE_ID);
        $column->autoIncrement = true;
        $column->primary = true;

        return $column;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefault;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;

        return $this;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;

        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;

        return $this;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function index(bool $value = true): self
    {
        $this->index = $value;

        return $this;
    }

    public function hasIndex(): bool
    {
        return $this->index;
    }

    public function length(int $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function precision(int $precision, int $scale = 0): self
    {
        $this->precision = $precision;
        $this->scale = $scale;

        return $this;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function collation(string $collation): self
    {
        $this->collation = $collation;

        return $this;
    }

    public function getCollation(): ?string
    {
        return $this->collation;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * The base SQL type without driver specific decoration.
     */
    public function baseType(): string
    {
        return $this->type;
    }
}
