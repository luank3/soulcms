<?php

declare(strict_types=1);

namespace SoulCMS\Http;

/**
 * Immutable server request.
 *
 * The object is created once per request and then decorated immutably by the
 * middleware pipeline (attributes carry route parameters, the user context and
 * anything else a middleware wants to hand downstream).
 */
final class Request
{
    /** @var array<string,string> */
    private array $headers;

    /** @var array<string,mixed> */
    private array $attributes = [];

    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $cookies;

    /** @var array<string,mixed> */
    private array $parsedBody;

    /** @var array<string,array<string,mixed>> */
    private array $files;

    /** @var array<string,mixed> */
    private array $server;

    private string $method;
    private Uri $uri;
    private string $protocol;
    private ?string $body = null;

    /** @var string|null */
    private ?string $id = null;

    /**
     * @param array<string,string>            $headers
     * @param array<string,mixed>             $query
     * @param array<string,mixed>             $cookies
     * @param array<string,mixed>             $parsedBody
     * @param array<string,array<string,mixed>> $files
     * @param array<string,mixed>             $server
     */
    public function __construct(
        string $method,
        Uri $uri,
        array $headers = [],
        array $query = [],
        array $cookies = [],
        array $parsedBody = [],
        array $files = [],
        array $server = [],
        string $protocol = '1.1',
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->headers = $headers;
        $this->query = $query;
        $this->cookies = $cookies;
        $this->parsedBody = $parsedBody;
        $this->files = $files;
        $this->server = $server;
        $this->protocol = $protocol;
    }

    /**
     * Build a request from the PHP superglobals.
     */
    public static function createFromGlobals(): self
    {
        $server = $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? 'GET';

        // Method override support (used by HTML forms that emulate PUT/DELETE).
        if ($method === 'POST' && isset($server['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $override = strtoupper((string) $server['HTTP_X_HTTP_METHOD_OVERRIDE']);

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $target = $server['REQUEST_URI'] ?? '/';
        $uri = Uri::fromString($target);

        $query = [];
        parse_str($uri->getQuery(), $query);

        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $server['CONTENT_TYPE'];
        }

        if (isset($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string) $server['CONTENT_LENGTH'];
        }

        $body = [];
        $contentType = $headers['Content-Type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');

            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $body = is_array($decoded) ? $decoded : [];
            }
        } elseif ($method !== 'GET') {
            $body = $_POST;
        }

        $protocol = str_starts_with((string) ($server['SERVER_PROTOCOL'] ?? 'HTTP/1.1'), 'HTTP/1.0')
            ? '1.0'
            : '1.1';

        return new self(
            $method,
            $uri,
            $headers,
            $query,
            $_COOKIE,
            $body,
            self::normalizeFiles($_FILES),
            $server,
            $protocol
        );
    }

    /**
     * @param array<string,mixed> $files
     * @return array<string,array<string,mixed>>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $file) {
            if (!is_array($file) || !isset($file['tmp_name'])) {
                continue;
            }

            if (is_array($file['tmp_name'])) {
                $normalized[$field] = [];

                foreach (array_keys($file['tmp_name']) as $index) {
                    $normalized[$field][$index] = [
                        'name' => $file['name'][$index] ?? '',
                        'type' => $file['type'][$index] ?? '',
                        'tmp_name' => $file['tmp_name'][$index] ?? '',
                        'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $file['size'][$index] ?? 0,
                    ];
                }

                continue;
            }

            $normalized[$field] = $file;
        }

        return $normalized;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * A correlation id for this request.
     *
     * The id is echoed back in the `X-Request-Id` header and written to the
     * log, so a report from a user can be traced to the exact request that
     * produced it.
     */
    public function getId(): string
    {
        if ($this->id === null) {
            $header = $this->getHeader('X-Request-Id');

            $this->id = is_string($header) && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $header) === 1
                ? $header
                : bin2hex(random_bytes(8));
        }

        return $this->id;
    }

    public function getUri(): Uri
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->uri->getPath();
    }

    public function getQueryString(): string
    {
        return $this->uri->getQuery();
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * @return array<string,mixed>
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function getCookieParams(): array
    {
        return $this->cookies;
    }

    public function getCookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getUploadedFiles(): array
    {
        return $this->files;
    }

    /**
     * @return array<string,mixed>
     */
    public function getServerParams(): array
    {
        return $this->server;
    }

    public function getServerParam(string $name, mixed $default = null): mixed
    {
        return $this->server[$name] ?? $default;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        $clone = clone $this;
        $clone->attributes = array_merge($clone->attributes, $attributes);

        return $clone;
    }

    public function withoutAttribute(string $name): self
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /**
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function withMethod(string $method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    public function withUri(Uri $uri): self
    {
        $clone = clone $this;
        $clone->uri = $uri;

        return $clone;
    }

    public function withQueryParam(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->query[$name] = $value;

        return $clone;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return $https !== '' && $https !== 'off';
    }

    public function isAjax(): bool
    {
        return strtolower($this->getHeader('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = $this->getHeader('Accept') ?? '';

        return str_contains($accept, 'application/json')
            || str_contains($accept, '+json')
            || $this->isAjax();
    }

    /**
     * Best-effort client IP resolution, honouring a single trusted proxy hop.
     */
    public function getClientIp(): string
    {
        $remote = $this->server['REMOTE_ADDR'] ?? '127.0.0.1';

        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            return '127.0.0.1';
        }

        $forwarded = $this->getHeader('X-Forwarded-For');

        if ($forwarded !== null) {
            foreach (explode(',', $forwarded) as $candidate) {
                $candidate = trim($candidate);

                if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP)) {
                    continue;
                }

                if ($this->isPrivateAddress($candidate)) {
                    continue;
                }

                return $candidate;
            }
        }

        return $remote;
    }

    private function isPrivateAddress(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    public function getUserAgent(): string
    {
        return mb_substr((string) $this->getHeader('User-Agent'), 0, 255);
    }
}
