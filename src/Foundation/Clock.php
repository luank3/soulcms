<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

/**
 * Immutable clock abstraction; enables deterministic testing of time-based
 * features (post listing order, rate limiting, token expiry).
 */
class Clock
{
    public function now(): int
    {
        return time();
    }

    public function microtime(): float
    {
        return microtime(true);
    }

    public function date(string $format, ?int $timestamp = null): string
    {
        return date($format, $timestamp ?? $this->now());
    }
}
