<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

/**
 * Configuration repository.
 *
 * Configuration is composed once at boot from:
 *   1. config/*.php files (defaults)
 *   2. config/local.php (optional, environment overrides, git-ignored)
 *   3. Environment variables
 *
 * Values are read once and memoised; a missing key never triggers a warning.
 */
final class Config
{
    /** @var array<string,mixed> */
    private array $items = [];

    private static ?self $instance = null;

    /**
     * @param array<string,mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function swap(self $config): void
    {
        self::$instance = $config;
    }

    /**
     * @param array<string,mixed> $items
     */
    public function set(array $items): void
    {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}
