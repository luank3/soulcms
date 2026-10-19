<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

/**
 * Minimal dotenv-style environment reader.
 *
 * Environment variables always win over .env values so that container
 * platforms (Kubernetes, Docker, serverless) can override configuration
 * without rewriting files.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $file): void
    {
        self::$loaded = true;

        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key === '' || str_starts_with($value, '#')) {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];

                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);

                    if ($first === '"') {
                        $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
                    }
                } else {
                    $hash = strpos($value, ' #');

                    if ($hash !== false) {
                        $value = rtrim(substr($value, 0, $hash));
                    }
                }
            }

            if (getenv($key) === false) {
                self::$values[$key] = $value;
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        fclose($handle);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? self::$values[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    public static function loaded(): bool
    {
        return self::$loaded;
    }
}
