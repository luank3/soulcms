<?php

declare(strict_types=1);

namespace SoulCMS\Routing;

/**
 * Compiles human readable route patterns into anchored PCRE patterns.
 *
 * Supported syntax
 * ----------------
 *   /articles/{id}              -> named parameter, default [^/]+
 *   /articles/{id:[0-9]+}       -> named parameter with a custom constraint
 *   /articles/{id:[0-9]+}?      -> optional parameter
 *   /articles/post-{:id}/edit   -> SketchCMS compatible anonymous parameter
 *   /files/{path:.*}            -> greedy segment (captures slashes)
 *
 * The compiler is intentionally allocation-light: it is executed once at
 * boot (or restored from the route cache) and never at request time.
 */
final class RouteCompiler
{
    public const DEFAULT_PATTERN = '[^/]+';

    /**
     * Compile a route pattern.
     *
     * @return array{regex:string, variables:list<string>, staticPrefix:string, segmentCount:int}
     */
    public static function compile(string $pattern): array
    {
        $pattern = self::normalise($pattern);

        $variables = [];
        $index = 0;
        $offset = 0;
        $regex = '';
        $length = strlen($pattern);

        while ($offset < $length) {
            $open = strpos($pattern, '{', $offset);

            if ($open === false) {
                $regex .= preg_quote(substr($pattern, $offset), '#');
                break;
            }

            $regex .= preg_quote(substr($pattern, $offset, $open - $offset), '#');

            $close = strpos($pattern, '}', $open);

            if ($close === false) {
                // Unterminated placeholder: treat the remainder as a literal.
                $regex .= preg_quote(substr($pattern, $open), '#');
                break;
            }

            $placeholder = substr($pattern, $open + 1, $close - $open - 1);
            $offset = $close + 1;

            [$name, $constraint, $optional] = self::parsePlaceholder($placeholder, $index);

            if ($name === '') {
                ++$index;

                continue;
            }

            $variables[] = $name;

            $group = '(?P<' . $name . '>' . $constraint . ')';

            if ($optional) {
                $regex .= '(?:' . $group . ')?';
            } else {
                $regex .= $group;
            }
        }

        $compiled = [
            'regex' => '#^' . $regex . '$#uD',
            'variables' => $variables,
            'staticPrefix' => self::staticPrefix($pattern),
            'segmentCount' => self::segmentCount($pattern),
        ];

        return $compiled;
    }

    /**
     * @return array{0:string,1:string,2:bool}
     */
    private static function parsePlaceholder(string $placeholder, int &$index): array
    {
        $optional = false;

        if (str_ends_with($placeholder, '?')) {
            $optional = true;
            $placeholder = substr($placeholder, 0, -1);
        }

        $constraint = self::DEFAULT_PATTERN;
        $name = '';

        if (str_contains($placeholder, ':')) {
            [$name, $constraint] = explode(':', $placeholder, 2);
            $name = trim($name);
            $constraint = $constraint === '' ? self::DEFAULT_PATTERN : $constraint;
        } else {
            $name = trim($placeholder);
        }

        // SketchCMS compatibility: "{:id}" means "unnamed parameter called id".
        // The well known keywords also carry their own constraint.
        if ($name === '') {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $constraint) === 1) {
                $name = $constraint;

                $constraint = match ($constraint) {
                    'id', 'number' => '[0-9]+',
                    'word' => '[a-zA-Z]+',
                    'slug' => '[a-z0-9\-]+',
                    default => self::DEFAULT_PATTERN,
                };
            } else {
                $name = 'p' . $index;
            }
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            $name = 'p' . $index;
        }

        return [$name, $constraint, $optional];
    }

    private static function normalise(string $pattern): string
    {
        if ($pattern === '' || $pattern === '/') {
            return '/';
        }

        $pattern = '/' . trim($pattern, '/');

        return $pattern === '/' ? '/' : rtrim($pattern, '/');
    }

    /**
     * Longest leading literal segment - used to reject non-matching paths
     * before running the (more expensive) regular expression.
     */
    private static function staticPrefix(string $pattern): string
    {
        $normalised = self::normalise($pattern);

        if (!str_contains($normalised, '{')) {
            return $normalised;
        }

        $prefix = '';
        $offset = 0;
        $length = strlen($normalised);

        while ($offset < $length) {
            $slash = strpos($normalised, '/', $offset);
            $end = $slash === false ? $length : $slash;

            $segment = substr($normalised, $offset, $end - $offset);

            if (str_contains($segment, '{')) {
                break;
            }

            $prefix .= $segment;

            if ($slash === false) {
                break;
            }

            $prefix .= '/';
            $offset = $slash + 1;
        }

        return $prefix === '' ? '/' : rtrim($prefix, '/');
    }

    private static function segmentCount(string $pattern): int
    {
        $normalised = self::normalise($pattern);

        if ($normalised === '/') {
            return 1;
        }

        return count(explode('/', trim($normalised, '/')));
    }
}
