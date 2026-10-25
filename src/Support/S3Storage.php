<?php

declare(strict_types=1);

namespace SoulCMS\Support;

use Closure;
use SoulCMS\Foundation\Config;

/** Minimal path-style or virtual-host S3 client using AWS Signature Version 4. */
final class S3Storage
{
    /** @var Closure(string,string,array<string,string>,string):int */
    private Closure $transport;

    /**
     * @param null|callable(string,string,array<string,string>,string):int $transport
     */
    public function __construct(private Config $config, ?callable $transport = null)
    {
        $this->transport = $transport === null
            ? self::sendRequest(...)
            : Closure::fromCallable($transport);
    }

    public function isConfigured(): bool
    {
        return $this->settings() !== null;
    }

    public function putFile(string $source, string $key, string $contentType): bool
    {
        $settings = $this->settings();
        if ($settings === null || !$this->validKey($key) || !is_file($source) || !is_readable($source)) {
            return false;
        }

        $body = file_get_contents($source);
        if (!is_string($body)) {
            return false;
        }

        $url = $this->objectUrl($settings, $key);
        $headers = $this->signedHeaders('PUT', $url, $settings, $body, [
            'content-type' => $contentType,
        ]);

        try {
            $status = ($this->transport)('PUT', $url, $headers, $body);

            return $status >= 200 && $status < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    public function presignedGetUrl(string $key, int $expires, string $downloadName, string $extension = '', bool $inline = false): ?string
    {
        $settings = $this->settings();
        if ($settings === null || !$this->validKey($key) || $expires < 1 || $expires > 604800) {
            return null;
        }

        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $scope = $date . '/' . $settings['region'] . '/s3/aws4_request';
        $url = $this->objectUrl($settings, $key);
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return null;
        }

        $credential = $settings['access_key'] . '/' . $scope;
        $downloadName = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $downloadName) ?? 'download';
        $downloadName = trim($downloadName, ' .');
        $extension = strtolower(trim($extension, '.'));
        if ($extension !== '' && preg_match('/^[a-z0-9]{1,12}$/', $extension) === 1) {
            $downloadName = pathinfo($downloadName, PATHINFO_FILENAME) . '.' . $extension;
        }
        $downloadName = $downloadName === '' ? 'download' : $downloadName;
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $credential,
            'X-Amz-Date' => $now,
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
            'response-content-disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\\", "\r", "\n"], '', $downloadName) . '"',
        ];
        ksort($query);
        $canonicalQuery = $this->canonicalQuery($query);
        $host = (string) $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $canonicalRequest = implode("\n", [
            'GET',
            $this->canonicalPath((string) $parts['path']),
            $canonicalQuery,
            'host:' . $host . "\n",
            'host',
            'UNSIGNED-PAYLOAD',
        ]);
        $stringToSign = "AWS4-HMAC-SHA256\n" . $now . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($settings['secret_key'], $date, $settings['region']));

        return $url . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    /** @return array<string,string>|null */
    private function settings(): ?array
    {
        $settings = [
            'endpoint' => rtrim((string) $this->config->get('app.storage.s3.endpoint', ''), '/'),
            'bucket' => trim((string) $this->config->get('app.storage.s3.bucket', '')),
            'region' => trim((string) $this->config->get('app.storage.s3.region', '')),
            'access_key' => trim((string) $this->config->get('app.storage.s3.access_key', '')),
            'secret_key' => (string) $this->config->get('app.storage.s3.secret_key', ''),
        ];

        foreach ($settings as $value) {
            if ($value === '') {
                return null;
            }
        }

        $endpoint = parse_url($settings['endpoint']);
        if (!is_array($endpoint) || !isset($endpoint['scheme'], $endpoint['host']) || strtolower($endpoint['scheme']) !== 'https') {
            return null;
        }

        $settings['path_style'] = (bool) $this->config->get('app.storage.s3.path_style', true);
        if (!$settings['path_style'] && filter_var($settings['bucket'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        return $settings;
    }

    /** @param array<string,mixed> $settings */
    private function objectUrl(array $settings, string $key): string
    {
        $endpoint = parse_url($settings['endpoint']);
        $scheme = (string) $endpoint['scheme'];
        $host = (string) $endpoint['host'];
        $port = isset($endpoint['port']) ? ':' . (int) $endpoint['port'] : '';
        $basePath = '/' . trim((string) ($endpoint['path'] ?? ''), '/');
        $basePath = $basePath === '/' ? '' : $basePath;
        $pathStyle = (bool) $settings['path_style'];

        return $scheme . '://' . ($pathStyle ? $host : $settings['bucket'] . '.' . $host) . $port
            . $basePath . ($pathStyle ? '/' . rawurlencode($settings['bucket']) : '')
            . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    /** @param array<string,mixed> $settings @param array<string,string> $extra */
    private function signedHeaders(string $method, string $url, array $settings, string $body, array $extra = []): array
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $payloadHash = hash('sha256', $body);
        $headers = array_merge($extra, [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ]);
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= strtolower($name) . ':' . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));
        $canonicalRequest = $method . "\n"
            . $this->canonicalPath((string) ($parts['path'] ?? '/')) . "\n"
            . "\n"
            . $canonicalHeaders
            . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;
        $scope = $date . '/' . $settings['region'] . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $now . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($settings['secret_key'], $date, $settings['region']));
        $headers['Authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $settings['access_key'] . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        return $headers;
    }

    private function signingKey(string $secret, string $date, string $region): string
    {
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);

        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }

    /** @param array<string,string> $query */
    private function canonicalQuery(array $query): string
    {
        $items = [];
        foreach ($query as $name => $value) {
            $items[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
        }
        sort($items, SORT_STRING);

        return implode('&', $items);
    }

    private function canonicalPath(string $path): string
    {
        return implode('/', array_map('rawurlencode', array_map('rawurldecode', explode('/', $path))));
    }

    private function validKey(string $key): bool
    {
        if ($key === '' || str_starts_with($key, '/') || str_contains($key, "\\")) {
            return false;
        }

        foreach (explode('/', $key) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,string> $headers */
    private static function sendRequest(string $method, string $url, array $headers, string $body): int
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        return preg_match('/\s(\d{3})\s/', $statusLine, $match) === 1 ? (int) $match[1] : ($response === false ? 0 : 200);
    }
}
