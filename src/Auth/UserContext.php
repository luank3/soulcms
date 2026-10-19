<?php

declare(strict_types=1);

namespace SoulCMS\Auth;

/**
 * The authenticated user of the current request.
 */
final class UserContext
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        public readonly bool $isAuthenticated = false,
        public readonly array $attributes = [],
    ) {
    }

    public function id(): int
    {
        return (int) ($this->attributes['id'] ?? 0);
    }

    public function nick(): string
    {
        return (string) ($this->attributes['nick'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->attributes['name'] ?? '');
    }

    public function email(): string
    {
        return (string) ($this->attributes['email'] ?? '');
    }

    public function level(): int
    {
        return (int) ($this->attributes['level'] ?? 0);
    }

    public function isBanned(): bool
    {
        return $this->level() < 0;
    }

    /**
     * Staff members start at level 120 (contributor), 127 is the developer.
     */
    public function isStaff(): bool
    {
        return $this->level() >= 120;
    }

    public function isAdmin(): bool
    {
        return $this->level() >= 126;
    }

    public function isDeveloper(): bool
    {
        return $this->level() >= 127;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
