<?php

declare(strict_types=1);

namespace SoulCMS\Http;

/**
 * Immutable value object representing a request URI.
 */
final class Uri
{
    private string $scheme;
    private string $host;
    private ?int $port;
    private string $path;
    private string $query;
    private string $fragment;

    public function __construct(
        string $scheme = '',
        string $host = '',
        ?int $port = null,
        string $path = '/',
        string $query = '',
        string $fragment = '',
    ) {
        $this->scheme = strtolower($scheme);
        $this->host = strtolower($host);
        $this->port = $port;
        $this->path = $path === '' ? '/' : $path;
        $this->query = $query;
        $this->fragment = $fragment;
    }

    public static function fromString(string $uri): self
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            return new self();
        }

        $query = $parts['query'] ?? '';
        $fragment = $parts['fragment'] ?? '';

        if (isset($parts['path']) && str_contains($parts['path'], '?')) {
            [$path, $inlineQuery] = explode('?', $parts['path'], 2);
            $query = $inlineQuery !== '' ? $inlineQuery : $query;
        }

        return new self(
            $parts['scheme'] ?? '',
            $parts['host'] ?? '',
            isset($parts['port']) ? (int) $parts['port'] : null,
            $parts['path'] ?? '/',
            $query,
            $fragment,
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * Path relative to the application base path (e.g. "/cms").
     */
    public function withBasePath(string $basePath): self
    {
        if ($basePath === '' || $basePath === '/') {
            return $this;
        }

        $basePath = '/' . trim($basePath, '/');

        if ($this->path === $basePath) {
            return new self($this->scheme, $this->host, $this->port, '/', $this->query, $this->fragment);
        }

        if (str_starts_with($this->path, $basePath . '/')) {
            return new self(
                $this->scheme,
                $this->host,
                $this->port,
                substr($this->path, strlen($basePath)),
                $this->query,
                $this->fragment
            );
        }

        return $this;
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . '://';
        }

        if ($this->host !== '') {
            $uri .= $this->host;

            if ($this->port !== null && !$this->isDefaultPort()) {
                $uri .= ':' . $this->port;
            }
        }

        $uri .= $this->path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    private function isDefaultPort(): bool
    {
        return ($this->scheme === 'http' && $this->port === 80)
            || ($this->scheme === 'https' && $this->port === 443);
    }
}
