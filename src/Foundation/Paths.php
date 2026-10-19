<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

/**
 * Absolute path registry for the application.
 *
 * All paths are resolved once at boot and cached as immutable strings so that
 * hot-path code never pays for repeated realpath() / concatenation.
 */
final class Paths
{
    private static ?string $base = null;

    /** @var array<string,string> */
    private static array $cache = [];

    public static function base(): string
    {
        if (self::$base === null) {
            self::$base = \dirname(__DIR__, 2);
        }

        return self::$base;
    }

    public static function setBase(string $path): void
    {
        self::$base = rtrim($path, '/\\');
        self::$cache = [];
    }

    public static function join(string ...$parts): string
    {
        $path = self::base();

        foreach ($parts as $part) {
            $path .= '/' . trim($part, '/\\');
        }

        return $path;
    }

    public static function config(string $file = ''): string
    {
        return $file === '' ? self::join('config') : self::join('config', $file);
    }

    public static function storage(string $file = ''): string
    {
        return $file === '' ? self::join('storage') : self::join('storage', $file);
    }

    public static function cache(string $file = ''): string
    {
        return $file === '' ? self::join('storage', 'cache') : self::join('storage', 'cache', $file);
    }

    public static function logs(string $file = ''): string
    {
        return $file === '' ? self::join('storage', 'logs') : self::join('storage', 'logs', $file);
    }

    public static function views(string $file = ''): string
    {
        return $file === '' ? self::join('resources', 'views') : self::join('resources', 'views', $file);
    }

    public static function modules(string $file = ''): string
    {
        return $file === '' ? self::join('app', 'Modules') : self::join('app', 'Modules', $file);
    }

    public static function database(string $file = ''): string
    {
        return $file === '' ? self::join('database') : self::join('database', $file);
    }
}
