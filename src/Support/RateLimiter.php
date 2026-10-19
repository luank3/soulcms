<?php

declare(strict_types=1);

namespace SoulCMS\Support;

use SoulCMS\Foundation\Paths;

/**
 * A rate limiter backed by APCu when available and by a lock-protected file
 * store otherwise.
 *
 * The file store keeps one bucket file per shard, so concurrent workers only
 * contend on the shard they use.
 */
final class RateLimiter
{
    public function __construct(
        private string $directory,
        private bool $useApcu = true,
    ) {
    }

    public static function default(): self
    {
        return new self(Paths::cache('ratelimit'), function_exists('apcu_fetch') && ini_get('apcu.enable'));
    }

    /**
     * Register a hit and report whether it is allowed.
     *
     * @param int $maxAttempts number of allowed requests per decay window
     * @param int $decaySeconds window length in seconds
     */
    public function hit(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $bucket = $this->bucket($key);

        if ($this->useApcu) {
            return $this->hitApcu($key, $bucket, $maxAttempts, $decaySeconds);
        }

        return $this->hitFile($key, $bucket, $maxAttempts, $decaySeconds);
    }

    public function availableIn(string $key, int $decaySeconds): int
    {
        $bucket = $this->bucket($key);

        if ($this->useApcu) {
            $entry = apcu_fetch('soulcms:rl:' . $key, false);
        } else {
            $entry = $this->read($key);
        }

        if (!is_array($entry)) {
            return 0;
        }

        $expiry = (int) ($entry['expires'] ?? 0);

        if ($expiry <= time()) {
            return 0;
        }

        return max(0, $expiry - time());
    }

    public function clear(string $key): void
    {
        if ($this->useApcu) {
            apcu_delete('soulcms:rl:' . $key);

            return;
        }

        $file = $this->file($key);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * @return array{hits:int,expires:int}
     */
    private function hitApcu(string $key, string $bucket, int $maxAttempts, int $decaySeconds): bool
    {
        $entry = apcu_fetch('soulcms:rl:' . $key, false);

        if (!is_array($entry) || (int) ($entry['expires'] ?? 0) <= time()) {
            $entry = ['hits' => 0, 'expires' => time() + $decaySeconds];
        }

        ++$entry['hits'];
        $entry['expires'] = (int) $entry['expires'];

        apcu_store('soulcms:rl:' . $key, $entry, max(1, $decaySeconds));

        return $entry['hits'] <= $maxAttempts;
    }

    /**
     * @return array{hits:int,expires:int}
     */
    private function hitFile(string $key, string $bucket, int $maxAttempts, int $decaySeconds): bool
    {
        $this->ensureDirectory($key);

        $file = $this->file($key);
        $handle = fopen($file, 'c+');

        if ($handle === false) {
            return true; // Fail open: never block a request because of the limiter.
        }

        try {
            flock($handle, LOCK_EX);

            $raw = stream_get_contents($handle);
            $entry = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            if (!is_array($entry) || (int) ($entry['expires'] ?? 0) <= time()) {
                $entry = ['hits' => 0, 'expires' => time() + $decaySeconds];
            }

            ++$entry['hits'];
            $entry['expires'] = (int) $entry['expires'];

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($entry));
            fflush($handle);

            return $entry['hits'] <= $maxAttempts;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array{hits:int,expires:int}|null
     */
    private function read(string $key): ?array
    {
        $file = $this->file($key);

        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function bucket(string $key): string
    {
        return substr(hash('xxh128', $key), 0, 2);
    }

    private function file(string $key): string
    {
        return $this->directory . '/' . $this->bucket($key) . '/' . hash('xxh128', $key) . '.json';
    }

    private function ensureDirectory(string $key): void
    {
        $directory = $this->directory . '/' . $this->bucket($key);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}
