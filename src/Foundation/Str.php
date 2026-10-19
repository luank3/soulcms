<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

/**
 * String utilities.
 */
final class Str
{
    /** @var array<string,string> */
    private static array $slugCache = [];

    public static function slug(string $value, string $separator = '-'): string
    {
        $key = $value . '|' . $separator;

        if (isset(self::$slugCache[$key])) {
            return self::$slugCache[$key];
        }

        $value = self::ascii($value);
        $value = preg_replace('/[^a-zA-Z0-9]+/', $separator, $value) ?? '';
        $value = strtolower(trim($value, $separator));

        if ($value === '') {
            $value = 'item';
        }

        return self::$slugCache[$key] = $value;
    }

    /**
     * Transliterate unicode text down to plain ASCII where possible.
     */
    public static function ascii(string $value): string
    {
        if (!preg_match('/[\x80-\xff]/', $value)) {
            return $value;
        }

        if (function_exists('transliterator_transliterate')) {
            // "Any-Latin; Latin-ASCII" is the portable ICU identifier; the
            // \x{...} form is not accepted by every ICU build.
            $result = @transliterator_transliterate('Any-Latin; Latin-ASCII', $value);

            if (is_string($result) && $result !== '') {
                return $result;
            }
        }

        $map = [
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a', 'â' => 'a', 'ầ' => 'a',
            'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a', 'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a',
            'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a', 'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ẹ' => 'e', 'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i', 'ò' => 'o', 'ó' => 'o',
            'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o',
            'ỗ' => 'o', 'ộ' => 'o', 'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ợ' => 'o', 'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u', 'ư' => 'u',
            'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u', 'ỳ' => 'y', 'ý' => 'y',
            'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y', 'đ' => 'd', 'ñ' => 'n', 'ç' => 'c', 'ý' => 'y',
        ];

        return strtr(mb_strtolower($value, 'UTF-8'), $map);
    }

    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_', '.'], ' ', $value);

        return str_replace(' ', '', ucwords($value));
    }

    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function kebab(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value) ?? $value;

        return strtolower(str_replace([' ', '_'], '-', $value));
    }

    public static function random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    /**
     * HTML-escape for output.
     */
    public static function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }

        if (is_array($value)) {
            $value = implode(', ', array_map(static fn ($item): string => is_scalar($item) ? (string) $item : '', $value));
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape for use inside a JavaScript string or an HTML attribute.
     */
    public static function eAttr(mixed $value): string
    {
        return self::e($value);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_ends_with($haystack, $needle);
    }

    /**
     * Convert a "PascalCase"/"camelCase" table name to snake_case.
     */
    public static function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower($value);
    }
}
