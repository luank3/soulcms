<?php

declare(strict_types=1);

namespace SoulCMS\Http;

/**
 * The Content Security Policy of the application.
 *
 * A fresh nonce is generated for every request, so an injected `<script>`
 * tag can never execute: the policy only allows the nonce the server issued
 * for this very response.
 *
 * `style-src` keeps `'unsafe-inline'` on purpose. The forum paints each
 * category with a colour derived from its name, which is passed to the
 * template as a custom property. Inline *styles* cannot execute script in
 * any current browser, whereas inline *scripts* can — those are locked down
 * with the nonce and `script-src-attr 'none'`.
 */
final class Csp
{
    private string $nonce;

    /** @var array<string,string> */
    private array $extra = [];

    public function __construct()
    {
        $this->nonce = base64_encode(random_bytes(18));
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    /**
     * Add a source to a directive, e.g. `csp->allow('img-src', 'https:')`.
     */
    public function allow(string $directive, string $source): self
    {
        $this->extra[$directive][] = $source;

        return $this;
    }

    /**
     * The policy as a header value.
     */
    public function build(bool $https = false): string
    {
        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'frame-src' => ["'none'"],
            'form-action' => ["'self'"],
            'script-src' => ["'self'", "'nonce-" . $this->nonce . "'"],
            'script-src-attr' => ["'none'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'media-src' => ["'self'"],
            'worker-src' => ["'self'", 'blob:'],
        ];

        foreach ($this->extra as $directive => $sources) {
            $directives[$directive] = array_merge($directives[$directive] ?? [], $sources);
        }

        if ($https) {
            $directives['upgrade-insecure-requests'] = [];
        }

        $parts = [];

        foreach ($directives as $directive => $sources) {
            $parts[] = $sources === [] ? $directive : $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $parts);
    }
}
