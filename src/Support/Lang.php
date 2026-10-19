<?php

declare(strict_types=1);

namespace SoulCMS\Support;

/**
 * A tiny message catalogue.
 *
 * The application speaks one language at a time. The catalogue lives in
 * `lang/{locale}.php` and falls back to English, so a missing translation
 * degrades to a readable message rather than a raw key.
 */
final class Lang
{
    /** @var array<string,array<string,string>> */
    private static array $catalogues = [];

    private static string $locale = 'vi';

    private static ?string $fallback = 'en';

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale !== '' ? $locale : 'vi';
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /**
     * Translate a key.
     *
     * @param array<string,scalar> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $message = self::catalogue(self::$locale)[$key] ?? null;

        if ($message === null && self::$fallback !== null) {
            $message = self::catalogue(self::$fallback)[$key] ?? null;
        }

        if ($message === null) {
            return $key;
        }

        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }

        return $message;
    }

    /**
     * @return array<string,string>
     */
    private static function catalogue(string $locale): array
    {
        if (isset(self::$catalogues[$locale])) {
            return self::$catalogues[$locale];
        }

        $file = \SoulCMS\Foundation\Paths::join('lang', $locale . '.php');

        if (!is_file($file)) {
            return self::$catalogues[$locale] = [];
        }

        $messages = require $file;

        return self::$catalogues[$locale] = is_array($messages) ? $messages : [];
    }
}
