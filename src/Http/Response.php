<?php

declare(strict_types=1);

namespace SoulCMS\Http;

/**
 * Immutable HTTP response.
 */
final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    private string $body = '';

    public function __construct(
        private int $status = 200,
        array $headers = [],
        string $body = '',
        private string $protocol = '1.1',
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = (string) $value;
        }

        $this->body = $body;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=UTF-8'], $body);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            $encoded === false ? '{}' : $encoded
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self($status, ['Location' => $url]);
    }

    public static function noContent(int $status = 204): self
    {
        return new self($status);
    }

    public function getStatusCode(): int
    {
        return $this->status;
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

    public function getBody(): string
    {
        return $this->body;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

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

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /**
     * Emit the response to the SAPI.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            header(sprintf('HTTP/%s %d %s', $this->protocol, $this->status, self::reason($this->status)), true, $this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }

    public static function reason(int $status): string
    {
        return self::REASONS[$status] ?? 'Unknown Status';
    }

    private const REASONS = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        413 => 'Payload Too Large',
        415 => 'Unsupported Media Type',
        418 => "I'm a teapot",
        419 => 'Page Expired',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];
}
