<?php

declare(strict_types=1);

namespace SoulCMS\Http;

/**
 * The URL rules configured in the administration panel.
 *
 * Two kinds of rules exist, mirroring the SketchCMS behaviour:
 *
 *  - **rewrite** - maps a public path onto another path, e.g.
 *    `{"/tin-tuc": {"target": "/articles?category=2"}}`;
 *  - **disable** - blocks a path entirely, e.g. `{"/disable": ["/register"]}`.
 *
 * The rules are a plain value object: the container builds one per request
 * from the stored configuration, so the hot path costs a single small file
 * read (or one indexed query) and nothing at all when no rule is configured.
 */
final class UrlRules
{
    /** @var array{rewrite:array<string,array<string,mixed>>,disable:list<string>} */
    private array $rules;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->rules = self::normalise($config);
    }

    /**
     * @param array<string,mixed> $config
     * @return array{rewrite:array<string,array<string,mixed>>,disable:list<string>}
     */
    private static function normalise(array $config): array
    {

        $rewrite = [];

        foreach ((array) ($config['rewrite'] ?? []) as $path => $rule) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            if (is_string($rule)) {
                $rule = ['target' => $rule];
            }

            if (!is_array($rule) || !isset($rule['target'])) {
                continue;
            }

            $rewrite['/' . trim($path, '/')] = $rule;
        }

        $disable = [];

        foreach ((array) ($config['disable'] ?? []) as $path) {
            if (is_string($path) && $path !== '') {
                $disable[] = '/' . trim($path, '/');
            }
        }

        return ['rewrite' => $rewrite, 'disable' => $disable];
    }

    /**
     * @return array{rewrite:array<string,array<string,mixed>>,disable:list<string>}
     */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * Whether the path has been blocked by an administrator.
     */
    public function isDisabled(string $path): bool
    {
        $rules = $this->all();

        if ($rules['disable'] === []) {
            return false;
        }

        $normalised = '/' . trim($path, '/');

        return in_array($normalised, $rules['disable'], true);
    }

    /**
     * The path a public URL is rewritten to, or null when there is no rule.
     *
     * The target may carry a query string: "/articles?category=2".
     */
    public function rewrite(string $path, string $method = 'GET'): ?string
    {
        $rules = $this->all();

        if ($rules['rewrite'] === []) {
            return null;
        }

        $normalised = '/' . trim($path, '/');
        $rule = $rules['rewrite'][$normalised] ?? null;

        if ($rule === null) {
            return null;
        }

        $target = (string) $rule['target'];
        $allowed = $rule['method'] ?? 'GET|POST';

        $allowedMethods = array_map('strtoupper', explode('|', (string) $allowed));

        if ($allowed !== '*' && !in_array(strtoupper($method), $allowedMethods, true)) {
            return null;
        }

        return $target === '' ? null : $target;
    }
}
