<?php

declare(strict_types=1);

use SoulCMS\Auth\AuthManager;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Routing\Router;
use SoulCMS\View\ViewFactory;

if (!function_exists('e')) {
    /**
     * Escape a value for HTML output.
     */
    function e(mixed $value): string
    {
        return SoulCMS\Foundation\Str::e($value);
    }
}

if (!function_exists('config')) {
    /**
     * Read a configuration value using dot notation.
     */
    function config(string $key, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = Container::getInstance()->get('config');

        return $config->get($key, $default);
    }
}

if (!function_exists('app')) {
    function app(?string $service = null): mixed
    {
        $container = Container::getInstance();

        return $service === null ? $container : $container->get($service);
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        /** @var Request $request */
        $request = Container::getInstance()->get('request');

        return $request;
    }
}

if (!function_exists('view')) {
    /**
     * Render a view or return the view factory.
     *
     * @param array<string,mixed> $data
     */
    function view(?string $template = null, array $data = [], ?string $layout = null): mixed
    {
        /** @var ViewFactory $view */
        $view = Container::getInstance()->get('view');

        if ($template === null) {
            return $view;
        }

        return $view->render($template, $data, $layout);
    }
}

if (!function_exists('auth')) {
    function auth(): AuthManager
    {
        /** @var AuthManager $auth */
        $auth = Container::getInstance()->get('auth');

        return $auth;
    }
}

if (!function_exists('session')) {
    function session(): SessionManager
    {
        /** @var SessionManager $session */
        $session = Container::getInstance()->get('session');

        return $session;
    }
}

if (!function_exists('url')) {
    /**
     * Build an application URL.
     */
    function url(string $path = '', bool $absolute = false): string
    {
        $basePath = (string) config('app.url.path', '');
        $path = '/' . ltrim($path, '/');

        if ($absolute) {
            $scheme = (string) config('app.url.scheme', 'http');
            $host = (string) config('app.url.host', 'localhost');

            return $scheme . '://' . $host . $basePath . $path;
        }

        return $basePath . $path;
    }
}

if (!function_exists('asset')) {
    /**
     * The cache-busting version of an asset, memoised per path.
     *
     * The templates ask for the same handful of assets on every request, so
     * the file system is only consulted once per path.
     *
     * @var array<string,string> $versions
     */
    $GLOBALS['__soul_asset_versions'] ??= [];

    /**
     * Build the URL of a public asset, with a cache-busting version.
     */
    function asset(string $path, bool $versioned = true): string
    {
        $path = '/' . ltrim($path, '/');
        $url = url($path);

        if (!$versioned) {
            return $url;
        }

        if (isset($GLOBALS['__soul_asset_versions'][$path])) {
            return $url . $GLOBALS['__soul_asset_versions'][$path];
        }

        $file = SoulCMS\Foundation\Paths::join('public', $path);

        if (!is_file($file)) {
            $GLOBALS['__soul_asset_versions'][$path] = '';

            return $url;
        }

        $GLOBALS['__soul_asset_versions'][$path] = '?v=' . substr((string) filemtime($file), -6);

        return $url . $GLOBALS['__soul_asset_versions'][$path];
    }
}

if (!function_exists('route')) {
    /**
     * Build the URL of a named route.
     *
     * @param array<string,scalar|null> $parameters
     */
    function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        /** @var Router $router */
        $router = Container::getInstance()->get('router');

        return $router->url($name, $parameters, $absolute, (string) config('app.url.path', ''));
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect the visitor.
     *
     * Absolute URLs are only accepted when they point at the configured
     * host, so the helper can never be turned into an open redirect.
     */
    function redirect(string $uri, int $status = 302): never
    {
        $location = safe_url($uri);

        header('Location: ' . $location, true, $status);
        exit;
    }
}

if (!function_exists('safe_url')) {
    /**
     * Return a URL that is safe to send in a Location header.
     *
     * Relative paths are resolved against the application root; absolute URLs
     * are only kept when their host matches the configured one.
     */
    function safe_url(string $uri): string
    {
        $uri = trim($uri);

        if ($uri === '') {
            return url('/');
        }

        // Protocol relative URLs ("//evil.test") are never allowed.
        if (str_starts_with($uri, '//') || str_starts_with($uri, '\\')) {
            return url('/');
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $uri)) {
            return url($uri);
        }

        $host = (string) config('app.url.host', '');
        $parsed = parse_url($uri);

        if (!is_array($parsed) || !isset($parsed['host'])) {
            return url('/');
        }

        if (strcasecmp((string) $parsed['host'], $host) !== 0) {
            return url('/');
        }

        $port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';

        return $parsed['scheme'] . '://' . $parsed['host'] . $port . ($parsed['path'] ?? '/')
            . (isset($parsed['query']) ? '?' . $parsed['query'] : '')
            . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return session()->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Read a value that was flashed to the session by the previous request.
     */
    function old(string $key, mixed $default = null): mixed
    {
        $bag = session()->get('old_input', []);

        return is_array($bag) ? ($bag[$key] ?? $default) : $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return session()->getFlash($key, $default);
    }
}

if (!function_exists('now')) {
    function now(): int
    {
        return time();
    }
}

if (!function_exists('slugify')) {
    function slugify(string $value, string $separator = '-'): string
    {
        return SoulCMS\Foundation\Str::slug($value, $separator);
    }
}

if (!function_exists('paging')) {
    /**
     * Render the pagination bar.
     *
     * @param string $baseUrl the query string the page number is appended to
     */
    function paging(string $baseUrl, int $currentPage, int $totalPages, int $visible = 5): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $currentPage = max(1, min($currentPage, $totalPages));
        $base = $baseUrl === '' ? '?' : (str_contains($baseUrl, '?') ? $baseUrl . '&' : $baseUrl . '?');
        $base = rtrim($base, '&') . (str_contains($base, '?') ? '&' : '?');

        $item = static function (string $label, string $href, bool $current = false, bool $disabled = false): string {
            if ($disabled) {
                return '<li class="is-disabled"><span>' . $label . '</span></li>';
            }

            $class = $current ? ' class="is-current"' : '';

            return '<li' . $class . '><a href="' . e($href) . '">' . $label . '</a></li>';
        };

        $html = '<nav class="pager" aria-label="' . e(\SoulCMS\Support\Lang::get('pagination.label')) . '"><ul class="pager__list">';

        $html .= $item('‹', $base . 'page=' . max(1, $currentPage - 1), false, $currentPage <= 1);

        $start = max(1, $currentPage - intdiv($visible, 2));
        $end = min($totalPages, $start + $visible - 1);
        $start = max(1, $end - $visible + 1);

        for ($page = $start; $page <= $end; ++$page) {
            $html .= $item((string) $page, $base . 'page=' . $page, $page === $currentPage);
        }

        $html .= $item('›', $base . 'page=' . min($totalPages, $currentPage + 1), false, $currentPage >= $totalPages);
        $html .= '</ul><span>' . e(\SoulCMS\Support\Lang::get('pagination.summary', [
            'current' => (string) $currentPage,
            'total' => (string) $totalPages,
        ])) . '</span></nav>';

        return $html;
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $precision) . ' ' . $units[$power];
    }
}

if (!function_exists('time_ago')) {
    function time_ago(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            $count = $diff;
            $unit = 'seconds';
        } elseif ($diff < 3600) {
            $count = intdiv($diff, 60);
            $unit = 'minutes';
        } elseif ($diff < 86400) {
            $count = intdiv($diff, 3600);
            $unit = 'hours';
        } elseif ($diff < 2592000) {
            $count = intdiv($diff, 86400);
            $unit = 'days';
        } else {
            return date('d.m.Y', $timestamp);
        }

        return \SoulCMS\Support\Lang::get('time.' . $unit . ($count === 1 ? '_one' : '_many'), [
            'count' => (string) $count,
        ]);
    }
}

if (!function_exists('icon')) {
    /**
     * Render an icon from the SVG sprite.
     *
     * @param string $name sprite symbol id, without the "i-" prefix
     * @param string $class extra classes for the <svg> element
     */
    function icon(string $name, string $class = '', int $size = 18): string
    {
        $name = preg_replace('/[^a-z0-9\-]/', '', strtolower($name)) ?? '';

        if ($name === '') {
            return '';
        }

        static $sprite = null;

        $sprite ??= asset('assets/img/icons.svg');

        return sprintf(
            '<svg class="%s" width="%d" height="%d" aria-hidden="true" focusable="false"><use href="%s#i-%s"></use></svg>',
            e($class),
            $size,
            $size,
            e($sprite),
            $name
        );
    }
}

if (!function_exists('csp_nonce')) {
    /**
     * The per-request Content Security Policy nonce.
     */
    function csp_nonce(): string
    {
        return Container::getInstance()->get('csp')->nonce();
    }
}

if (!function_exists('theme')) {
    /**
     * The theme the visitor asked for. Rendered on the server so the page
     * never flashes the wrong colours.
     */
    function theme(): string
    {
        $cookie = $_COOKIE['soulcms_theme'] ?? null;

        return $cookie === 'light' ? 'light' : 'dark';
    }
}

if (!function_exists('member_role')) {
    /**
     * Resolve the role of a member from the CMS level scale.
     *
     * @param array<string,mixed>|null $user
     * @return array{key:string,label:string,level:int}
     */
    function member_role(?array $user): array
    {
        $level = (int) ($user['level'] ?? 0);
        $registered = (int) ($user['reg'] ?? time());
        $days = (int) floor((time() - $registered) / 86400);

        if ($level < 0) {
            $key = 'banned';
            $labelKey = 'user.role.banned';
        } elseif ($level >= 127) {
            $key = 'developer';
            $labelKey = 'user.role.developer';
        } elseif ($level >= 126) {
            $key = 'administrator';
            $labelKey = 'user.role.administrator';
        } elseif ($level >= 122) {
            $key = 'super-moderator';
            $labelKey = 'user.role.super_moderator';
        } elseif ($level === 121) {
            $key = 'moderator';
            $labelKey = 'user.role.moderator';
        } elseif ($level >= 120) {
            $key = 'contributor';
            $labelKey = 'user.role.contributor';
        } elseif ($days >= 3650) {
            $key = 'legend';
            $labelKey = 'user.role.legend';
        } elseif ($days >= 2920) {
            $key = 'veteran';
            $labelKey = 'user.role.veteran';
        } elseif ($days >= 1825) {
            $key = 'senior';
            $labelKey = 'user.role.senior';
        } elseif ($days >= 730) {
            $key = 'junior';
            $labelKey = 'user.role.junior';
        } elseif ($days >= 30) {
            $key = 'member';
            $labelKey = 'user.role.member';
        } else {
            $key = 'newbie';
            $labelKey = 'user.role.newbie';
        }

        return ['key' => $key, 'label' => \SoulCMS\Support\Lang::get($labelKey), 'level' => $level];
    }
}

if (!function_exists('member_chip')) {
    /**
     * The identity chip: a member's role is data, so it wears the role colour.
     *
     * @param array<string,mixed>|null $user
     */
    function member_chip(?array $user, bool $plain = false): string
    {
        $role = member_role($user);

        return sprintf(
            '<span class="chip role-%s%s">%s</span>',
            e($role['key']),
            $plain ? ' chip--plain' : '',
            e($role['label'])
        );
    }
}

if (!function_exists('avatar_svg')) {
    /**
     * A deterministic local avatar: initials over a hue derived from the
     * nickname. Self contained, so no third party image host is needed.
     *
     * @param array<string,mixed>|null $user
     */
    function avatar_svg(?array $user, int $size = 34): string
    {
        $nick = (string) ($user['nick'] ?? $user['name'] ?? '?');
        $initials = mb_strtoupper(mb_substr($nick, 0, 2));

        $hue = crc32(mb_strtolower($nick)) % 360;
        $fill = sprintf('hsl(%d 30%% 38%%)', $hue);
        $ink = sprintf('hsl(%d 62%% 82%%)', $hue);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="%d" height="%d" role="img" aria-label="%s">'
            . '<rect width="32" height="32" fill="%s"/>'
            . '<text x="16" y="16" fill="%s" font-family="ui-monospace,SFMono-Regular,Menlo,monospace" font-size="12" font-weight="600"'
            . ' text-anchor="middle" dominant-baseline="central">%s</text></svg>',
            $size,
            $size,
            e($nick),
            $fill,
            $ink,
            e($initials)
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

if (!function_exists('is_active')) {
    /**
     * Whether a navigation entry matches the current request.
     */
    function is_active(string $path, string $prefix = ''): bool
    {
        $current = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $current = '/' . trim($current, '/');
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return $current === '/';
        }

        if ($prefix !== '' && str_starts_with($current, '/' . trim($prefix, '/'))) {
            return true;
        }

        return $current === $path;
    }
}

if (!function_exists('markdown_to_html')) {
    /**
     * A deliberately small text renderer: it escapes first and only then adds
     * the few marks the forum actually uses.
     */
    function markdown_to_html(string $text): string
    {
        return \SoulCMS\Support\Bbcode::render($text, 'e');
    }
}

if (!function_exists('content_excerpt')) {
    /**
     * Render a plain-text excerpt from formatted post content.
     */
    function content_excerpt(string $text, int $limit = 160): string
    {
        $plainText = html_entity_decode(strip_tags(markdown_to_html($text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/\s+/u', ' ', $plainText) ?? $plainText;

        return \SoulCMS\Foundation\Str::limit(trim($plainText), max(1, $limit), '…');
    }
}

if (!function_exists('marker_color')) {
    /**
     * A stable colour for a category or tag, derived from its key. The feed
     * gutter uses it so a reader can recognise a section at a glance.
     */
    function marker_color(string $key): string
    {
        $hue = crc32(mb_strtolower($key)) % 360;

        return sprintf('hsl(%d 52%% 52%%)', $hue);
    }
}

if (!function_exists('marker_style')) {
    /**
     * The inline custom property that paints a marker.
     */
    function marker_style(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return sprintf('style="--marker:%s"', e(marker_color($key)));
    }
}
